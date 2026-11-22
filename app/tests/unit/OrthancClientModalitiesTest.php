<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\OrthancClient;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Tests\Support\HTTP\QueuedMockCURLRequest;

final class OrthancClientModalitiesTest extends CIUnitTestCase
{
    private const ENV_KEYS = ['ORTHANC_URL', 'ORTHANC_USER', 'ORTHANC_PASSWORD'];

    /**
     * @var array<string, array{env: mixed, server: mixed, getenv: string|false}>
     */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ENV_KEYS as $key) {
            $this->savedEnv[$key] = [
                'env'    => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
            $this->clearEnvVar($key);
        }
    }

    protected function tearDown(): void
    {
        Services::resetSingle('orthanc');

        foreach (self::ENV_KEYS as $key) {
            $this->clearEnvVar($key);
            $saved = $this->savedEnv[$key] ?? null;
            if ($saved === null) {
                continue;
            }
            if ($saved['env'] !== null) {
                $_ENV[$key] = $saved['env'];
            }
            if ($saved['server'] !== null) {
                $_SERVER[$key] = $saved['server'];
            }
            if ($saved['getenv'] !== false) {
                putenv($key . '=' . $saved['getenv']);
            }
        }

        parent::tearDown();
    }

    private function clearEnvVar(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    private function setEnvVar(string $key, string $value): void
    {
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    public function testServicesOrthancSupportsSharedInstanceAndInjectMock(): void
    {
        $first  = Services::orthanc();
        $second = Services::orthanc();
        $fresh  = Services::orthanc(false);

        $this->assertInstanceOf(OrthancClient::class, $first);
        $this->assertSame($first, $second);
        $this->assertNotSame($first, $fresh);

        $mockClient = new OrthancClient(new QueuedMockCURLRequest(), 'http://mock-orthanc:8042');
        Services::injectMock('orthanc', $mockClient);

        $this->assertSame($mockClient, Services::orthanc());

        Services::resetSingle('orthanc');
        $afterReset = Services::orthanc();
        $this->assertInstanceOf(OrthancClient::class, $afterReset);
        $this->assertNotSame($mockClient, $afterReset);
    }

    public function testListModalitiesWithoutAndWithExpand(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueJson(200, ['center_1', 'center_2'])
            ->queueJson(200, [
                'center_1' => ['AET' => 'DEST1', 'Host' => '10.0.0.10', 'Port' => 104],
            ]);

        $client = new OrthancClient($http, 'http://orthanc.local:8042/');

        $compact = $client->listModalities();
        $this->assertTrue($compact['ok']);
        $this->assertSame(200, $compact['status']);
        $this->assertSame(['center_1', 'center_2'], $compact['data']);
        $this->assertNull($compact['error']);
        $this->assertSame('GET', $http->recordedRequests[0]['method']);
        $this->assertSame('http://orthanc.local:8042/modalities', $http->recordedRequests[0]['url']);

        $expanded = $client->listModalities(true);
        $this->assertTrue($expanded['ok']);
        $this->assertSame(200, $expanded['status']);
        $this->assertArrayHasKey('center_1', $expanded['data']);
        $this->assertNull($expanded['error']);
        $this->assertSame('http://orthanc.local:8042/modalities?expand', $http->recordedRequests[1]['url']);
    }

    public function testListModalitiesErrorInvalidJsonAndException(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueJson(500, ['Message' => 'DB error', 'Details' => 'Connection lost'])
            ->queueRaw(200, 'not-json')
            ->queueException(new \RuntimeException('Socket timeout'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $httpErr = $client->listModalities();
        $this->assertFalse($httpErr['ok']);
        $this->assertSame(500, $httpErr['status']);
        $this->assertSame([], $httpErr['data']);
        $this->assertSame('DB error — Connection lost', $httpErr['error']);

        $badJson = $client->listModalities();
        $this->assertFalse($badJson['ok']);
        $this->assertSame(200, $badJson['status']);
        $this->assertSame([], $badJson['data']);
        $this->assertNotNull($badJson['error']);

        $exc = $client->listModalities();
        $this->assertFalse($exc['ok']);
        $this->assertSame(0, $exc['status']);
        $this->assertSame([], $exc['data']);
        $this->assertSame('Socket timeout', $exc['error']);
    }

    public function testGetModalitySuccessNotFoundEmptyIdAndException(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueJson(200, ['AET' => 'HOSPITAL_A', 'Host' => '192.168.1.20', 'Port' => 4242])
            ->queueJson(404, ['Message' => 'Inexistent item', 'Details' => 'Unknown modality'])
            ->queueException(new HTTPException('Connection refused'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $empty = $client->getModality('');
        $this->assertFalse($empty['ok']);
        $this->assertSame(0, $empty['status']);
        $this->assertNull($empty['data']);
        $this->assertCount(0, $http->recordedRequests);

        $found = $client->getModality('center_1');
        $this->assertTrue($found['ok']);
        $this->assertSame(200, $found['status']);
        $this->assertSame('HOSPITAL_A', $found['data']['AET']);
        $this->assertNull($found['error']);
        $this->assertSame('http://orthanc.local:8042/modalities/center_1', $http->recordedRequests[0]['url']);

        $missing = $client->getModality('center_99');
        $this->assertFalse($missing['ok']);
        $this->assertSame(404, $missing['status']);
        $this->assertNull($missing['data']);
        $this->assertSame('Inexistent item — Unknown modality', $missing['error']);

        $exc = $client->getModality('center_1');
        $this->assertFalse($exc['ok']);
        $this->assertSame(0, $exc['status']);
        $this->assertNull($exc['data']);
        $this->assertSame('Connection refused', $exc['error']);
    }

    public function testPutModalityNormalizesPayloadAndHandlesEmptyAndJsonBodies(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueRaw(200, '')
            ->queueJson(200, ['AET' => 'REMOTE_AET', 'Host' => '10.0.0.5', 'Port' => 11112])
            ->queueJson(400, ['Message' => 'Bad modality config'])
            ->queueException(new \RuntimeException('Network down'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $emptyId = $client->putModality(' ', ['AET' => 'X']);
        $this->assertFalse($emptyId['ok']);
        $this->assertSame(0, $emptyId['status']);

        $resEmptyBody = $client->putModality('center_1', [
            'AET'  => 'REMOTE_AET',
            'Host' => '10.0.0.5',
            'Port' => '104',
        ]);
        $this->assertTrue($resEmptyBody['ok']);
        $this->assertSame(200, $resEmptyBody['status']);
        $this->assertNull($resEmptyBody['data']);
        $this->assertNull($resEmptyBody['error']);

        $req0 = $http->recordedRequests[0];
        $this->assertSame('PUT', $req0['method']);
        $this->assertSame('http://orthanc.local:8042/modalities/center_1', $req0['url']);
        $this->assertSame('PUT', $req0['curl_options'][CURLOPT_CUSTOMREQUEST]);
        $this->assertContains('Content-Type: application/json', $req0['curl_options'][CURLOPT_HTTPHEADER]);
        $this->assertFalse($req0['curl_options'][CURLOPT_FAILONERROR]);

        $sentPayload = json_decode((string) $req0['curl_options'][CURLOPT_POSTFIELDS], true);
        $this->assertSame([
            'Manufacturer' => 'Generic',
            'AllowEcho'    => true,
            'AllowStore'   => true,
            'AET'          => 'REMOTE_AET',
            'Host'         => '10.0.0.5',
            'Port'         => 104,
        ], $sentPayload);
        $this->assertIsInt($sentPayload['Port']);

        $resJsonBody = $client->putModality('center_2', [
            'AET'          => 'REMOTE_AET',
            'Host'         => '10.0.0.5',
            'Port'         => 11112,
            'Manufacturer' => 'Dcm4Chee',
            'AllowEcho'    => false,
            'AllowStore'   => true,
        ]);
        $this->assertTrue($resJsonBody['ok']);
        $this->assertSame(200, $resJsonBody['status']);
        $this->assertSame(11112, $resJsonBody['data']['Port']);

        $resErr = $client->putModality('center_2', ['AET' => 'BAD', 'Host' => 'h', 'Port' => 104]);
        $this->assertFalse($resErr['ok']);
        $this->assertSame(400, $resErr['status']);
        $this->assertNull($resErr['data']);
        $this->assertSame('Bad modality config', $resErr['error']);

        $resExc = $client->putModality('center_2', ['AET' => 'X', 'Host' => 'h', 'Port' => 104]);
        $this->assertFalse($resExc['ok']);
        $this->assertSame(0, $resExc['status']);
        $this->assertSame('Network down', $resExc['error']);
    }

    public function testDeleteModalityIdempotencyAndErrors(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueRaw(200, '')
            ->queueRaw(204, '')
            ->queueJson(404, ['Message' => 'Unknown modality'])
            ->queueJson(500, ['Message' => 'Internal error'])
            ->queueException(new \RuntimeException('Reset by peer'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $empty = $client->deleteModality('');
        $this->assertFalse($empty['ok']);
        $this->assertSame(0, $empty['status']);

        foreach ([200, 204, 404] as $expectedStatus) {
            $res = $client->deleteModality('center_1');
            $this->assertTrue($res['ok'], "Expected status {$expectedStatus} to be treated as ok");
            $this->assertSame($expectedStatus, $res['status']);
            $this->assertNull($res['error']);
        }

        $this->assertSame('DELETE', $http->recordedRequests[0]['method']);
        $this->assertSame('http://orthanc.local:8042/modalities/center_1', $http->recordedRequests[0]['url']);

        $fail = $client->deleteModality('center_1');
        $this->assertFalse($fail['ok']);
        $this->assertSame(500, $fail['status']);
        $this->assertSame('Internal error', $fail['error']);

        $exc = $client->deleteModality('center_1');
        $this->assertFalse($exc['ok']);
        $this->assertSame(0, $exc['status']);
        $this->assertSame('Reset by peer', $exc['error']);
    }

    public function testEchoModalitySendsNonEmptyJsonObjectAndReportsLatencyAndErrors(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueJson(200, new \stdClass())
            ->queueJson(500, ['Message' => 'DICOM C-ECHO failed', 'Details' => 'TCP connection refused'])
            ->queueException(new HTTPException('cURL timeout'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $empty = $client->echoModality('');
        $this->assertFalse($empty['ok']);
        $this->assertSame(0, $empty['status']);
        $this->assertSame(0, $empty['latency_ms']);

        $ok = $client->echoModality('center_1', 12);
        $this->assertTrue($ok['ok']);
        $this->assertSame(200, $ok['status']);
        $this->assertGreaterThanOrEqual(0, $ok['latency_ms']);
        $this->assertNull($ok['error']);

        $req = $http->recordedRequests[0];
        $this->assertSame('POST', $req['method']);
        $this->assertSame('http://orthanc.local:8042/modalities/center_1/echo', $req['url']);
        $this->assertContains('Content-Type: application/json', $req['curl_options'][CURLOPT_HTTPHEADER]);
        $rawBody = (string) $req['curl_options'][CURLOPT_POSTFIELDS];
        $this->assertNotSame('', $rawBody);
        $this->assertNotSame('[]', $rawBody);
        $decodedObj = json_decode($rawBody, false);
        $this->assertInstanceOf(\stdClass::class, $decodedObj);
        $this->assertSame(12, $decodedObj->Timeout);

        $fail = $client->echoModality('center_1');
        $this->assertFalse($fail['ok']);
        $this->assertSame(500, $fail['status']);
        $this->assertGreaterThanOrEqual(0, $fail['latency_ms']);
        $this->assertSame('DICOM C-ECHO failed — TCP connection refused', $fail['error']);

        $exc = $client->echoModality('center_1');
        $this->assertFalse($exc['ok']);
        $this->assertSame(0, $exc['status']);
        $this->assertGreaterThanOrEqual(0, $exc['latency_ms']);
        $this->assertSame('cURL timeout', $exc['error']);
    }

    public function testStoreToModalitySuccessPartialFailureZeroInstancesAndHttpErrors(): void
    {
        $http = new QueuedMockCURLRequest();
        $http->queueJson(200, [
            'Description'          => 'REST API',
            'FailedInstancesCount' => 0,
            'InstancesCount'       => 42,
            'LocalAet'             => 'PACSONLINE',
            'RemoteAet'            => 'CLINIC_PACS',
        ])
            ->queueJson(200, [
                'FailedInstancesCount' => 2,
                'InstancesCount'       => 10,
                'Message'              => 'Some instances failed',
                'Details'              => 'SOP Class not supported',
            ])
            ->queueJson(200, [
                'FailedInstancesCount' => 0,
                'InstancesCount'       => 0,
            ])
            ->queueRaw(200, 'invalid-json')
            ->queueJson(502, [
                'Message' => 'Error in the network protocol',
                'Details' => 'Remote peer closed association',
            ])
            ->queueException(new \RuntimeException('cURL error 28: Operation timed out'));

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $noModality = $client->storeToModality('', ['study-1']);
        $this->assertFalse($noModality['ok']);
        $this->assertSame(0, $noModality['status']);

        $noResources = $client->storeToModality('center_1', ['', '   ']);
        $this->assertFalse($noResources['ok']);
        $this->assertSame(0, $noResources['status']);
        $this->assertCount(0, $http->recordedRequests);

        $success = $client->storeToModality('center_1', [2 => 'orthanc-study-abc'], true);
        $this->assertTrue($success['ok']);
        $this->assertSame(200, $success['status']);
        $this->assertSame(42, $success['instances_count']);
        $this->assertSame(0, $success['failed_count']);
        $this->assertNull($success['error']);
        $this->assertIsArray($success['data']);

        $req = $http->recordedRequests[0];
        $this->assertSame('POST', $req['method']);
        $this->assertSame('http://orthanc.local:8042/modalities/center_1/store', $req['url']);
        $this->assertSame([
            'Resources'   => ['orthanc-study-abc'],
            'Synchronous' => true,
        ], json_decode((string) $req['curl_options'][CURLOPT_POSTFIELDS], true));

        $partialFailure = $client->storeToModality('center_1', ['orthanc-study-abc']);
        $this->assertFalse($partialFailure['ok']);
        $this->assertSame(200, $partialFailure['status']);
        $this->assertSame(10, $partialFailure['instances_count']);
        $this->assertSame(2, $partialFailure['failed_count']);
        $this->assertSame('Some instances failed — SOP Class not supported', $partialFailure['error']);

        $zeroInstances = $client->storeToModality('center_1', ['orthanc-study-abc']);
        $this->assertFalse($zeroInstances['ok']);
        $this->assertSame(200, $zeroInstances['status']);
        $this->assertSame(0, $zeroInstances['instances_count']);
        $this->assertSame(0, $zeroInstances['failed_count']);
        $this->assertNotNull($zeroInstances['error']);

        $badJson = $client->storeToModality('center_1', ['orthanc-study-abc']);
        $this->assertFalse($badJson['ok']);
        $this->assertSame(200, $badJson['status']);
        $this->assertNull($badJson['data']);

        $httpErr = $client->storeToModality('center_1', ['orthanc-study-abc']);
        $this->assertFalse($httpErr['ok']);
        $this->assertSame(502, $httpErr['status']);
        $this->assertSame('Error in the network protocol — Remote peer closed association', $httpErr['error']);

        $exc = $client->storeToModality('center_1', ['orthanc-study-abc']);
        $this->assertFalse($exc['ok']);
        $this->assertSame(0, $exc['status']);
        $this->assertSame('cURL error 28: Operation timed out', $exc['error']);
    }

    public function testBasicAuthAppliedOnlyWhenBothCredentialsSetAndNotCoerced(): void
    {
        $this->setEnvVar('ORTHANC_URL', 'http://orthanc-env:8042/');
        $this->setEnvVar('ORTHANC_USER', 'orthanc_admin');
        $this->setEnvVar('ORTHANC_PASSWORD', 'true');

        $httpBoth = new QueuedMockCURLRequest();
        $httpBoth->queueJson(200, []);
        $clientBoth = new OrthancClient($httpBoth);
        $clientBoth->listModalities();

        $curlOptsBoth = $httpBoth->lastCurlOptions();
        $this->assertSame('http://orthanc-env:8042/modalities', $httpBoth->recordedRequests[0]['url']);
        $this->assertSame(CURLAUTH_BASIC, $curlOptsBoth[CURLOPT_HTTPAUTH]);
        $this->assertSame('orthanc_admin:true', $curlOptsBoth[CURLOPT_USERPWD]);
        $this->assertFalse($curlOptsBoth[CURLOPT_FAILONERROR]);

        // Password 'false' or 'null' also preserved literally without env() coercion
        $this->setEnvVar('ORTHANC_PASSWORD', 'false');
        $httpFalsePass = new QueuedMockCURLRequest();
        $httpFalsePass->queueJson(200, ['CountStudies' => 3]);
        $clientFalsePass = new OrthancClient($httpFalsePass);
        $this->assertSame(3, $clientFalsePass->countStudies());
        $this->assertSame('orthanc_admin:false', $httpFalsePass->lastCurlOptions()[CURLOPT_USERPWD]);

        // Only username set -> no auth header/option
        $this->clearEnvVar('ORTHANC_PASSWORD');
        $httpUserOnly = new QueuedMockCURLRequest();
        $httpUserOnly->queueJson(200, []);
        (new OrthancClient($httpUserOnly))->listModalities();
        $this->assertArrayNotHasKey(CURLOPT_USERPWD, $httpUserOnly->lastCurlOptions());

        // Only password set -> no auth header/option
        $this->clearEnvVar('ORTHANC_USER');
        $this->setEnvVar('ORTHANC_PASSWORD', 'secret');
        $httpPassOnly = new QueuedMockCURLRequest();
        $httpPassOnly->queueJson(200, []);
        (new OrthancClient($httpPassOnly))->listModalities();
        $this->assertArrayNotHasKey(CURLOPT_USERPWD, $httpPassOnly->lastCurlOptions());

        // Neither set -> no auth header/option
        $this->clearEnvVar('ORTHANC_PASSWORD');
        $httpNone = new QueuedMockCURLRequest();
        $httpNone->queueJson(200, []);
        (new OrthancClient($httpNone))->listModalities();
        $this->assertArrayNotHasKey(CURLOPT_USERPWD, $httpNone->lastCurlOptions());
    }

    public function testExistingOrthancClientMethodsRemainCompatibleWithAuthPropagation(): void
    {
        $this->setEnvVar('ORTHANC_USER', 'admin');
        $this->setEnvVar('ORTHANC_PASSWORD', 'null');

        $http = new QueuedMockCURLRequest();
        $http->queueJson(200, ['Name' => 'PACS_ONLINE'])
            ->queueJson(200, [['ID' => 'study-1', 'MainDicomTags' => ['StudyInstanceUID' => '1.2.3.4']]])
            ->queueJson(200, ['ID' => 'study-1'])
            ->queueRaw(204, '');

        $client = new OrthancClient($http, 'http://orthanc.local:8042');

        $this->assertTrue($client->isAlive());
        $this->assertSame('admin:null', $http->recordedCurlOptions[0][CURLOPT_USERPWD]);

        $study = $client->findStudyByUid('1.2.3.4');
        $this->assertIsArray($study);
        $this->assertSame('study-1', $study['ID']);
        $this->assertSame('admin:null', $http->recordedCurlOptions[1][CURLOPT_USERPWD]);

        $byId = $client->getStudy('study-1');
        $this->assertSame(['ID' => 'study-1'], $byId);

        $this->assertTrue($client->deleteStudy('study-1'));
    }
}
