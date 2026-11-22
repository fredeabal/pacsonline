<?php

declare(strict_types=1);

namespace Tests\E2E\Infra;

use PHPUnit\Framework\TestCase;
use Tests\E2E\Support\OrthancTestServer;

/**
 * Self-checks for the stateful local Orthanc HTTP simulator.
 * Must pass 100% independently of M1-M3 application implementation.
 */
final class SimulatorSanityTest extends TestCase
{
    private ?OrthancTestServer $server = null;
    private ?string $tempStateFile     = null;

    protected function setUp(): void
    {
        parent::setUp();
        $tmp = tempnam(sys_get_temp_dir(), 'orthanc_sanity_');
        $this->assertNotFalse($tmp);
        $this->tempStateFile = $tmp;
        $this->server        = new OrthancTestServer($this->tempStateFile, true);
        $this->server->start();
        $this->server->reset();
    }

    protected function tearDown(): void
    {
        if ($this->server !== null) {
            $this->server->stop();
            $this->server = null;
        }
        if ($this->tempStateFile !== null && is_file($this->tempStateFile)) {
            @unlink($this->tempStateFile);
            $this->tempStateFile = null;
        }
        parent::tearDown();
    }

    public function testServerStartsRespondsToSystemAndStopsCleanly(): void
    {
        $this->assertNotNull($this->server);
        $this->assertTrue($this->server->isRunning());

        $sys = $this->server->httpRequest('GET', '/system');
        $this->assertSame(200, $sys['status']);
        $data = json_decode($sys['body'], true);
        $this->assertIsArray($data);
        $this->assertSame('PACSONLINE', $data['DicomAet']);

        $this->server->stop();
        $this->assertFalse($this->server->isRunning());
    }

    public function testModalitiesExpandReturnsEmptyObjectWhenEmptyAndCrudPersistsAcrossRestart(): void
    {
        $this->assertNotNull($this->server);

        $emptyExpand = $this->server->httpRequest('GET', '/modalities?expand');
        $this->assertSame(200, $emptyExpand['status']);
        $this->assertSame('{}', trim($emptyExpand['body']));

        $put = $this->server->httpRequest(
            'PUT',
            '/modalities/center_1',
            (string) json_encode([
                'AET'          => 'DEST_CLINIC',
                'Host'         => '10.20.30.40',
                'Port'         => 4242,
                'Manufacturer' => 'Generic',
                'AllowEcho'    => true,
                'AllowStore'   => true,
            ]),
            ['Content-Type: application/json']
        );
        $this->assertSame(200, $put['status']);

        $this->server->restart();
        $this->assertTrue($this->server->isRunning());

        $getAfterRestart = $this->server->httpRequest('GET', '/modalities/center_1');
        $this->assertSame(200, $getAfterRestart['status']);
        $mod = json_decode($getAfterRestart['body'], true);
        $this->assertSame('DEST_CLINIC', $mod['AET']);
        $this->assertSame('10.20.30.40', $mod['Host']);
        $this->assertSame(4242, $mod['Port']);

        $del = $this->server->httpRequest('DELETE', '/modalities/center_1');
        $this->assertSame(200, $del['status']);

        $getMissing = $this->server->httpRequest('GET', '/modalities/center_1');
        $this->assertSame(404, $getMissing['status']);
    }

    public function testEchoEnforcesNonEmptyJsonObjectBodyAndFaultModes(): void
    {
        $this->assertNotNull($this->server);

        $this->server->httpRequest(
            'PUT',
            '/modalities/center_2',
            (string) json_encode(['AET' => 'ECHO_AET', 'Host' => '127.0.0.1', 'Port' => 11112]),
            ['Content-Type: application/json']
        );

        // Orthanc >= 1.7.0 rejects empty body or [] with 400
        $emptyEcho = $this->server->httpRequest('POST', '/modalities/center_2/echo', '', ['Content-Type: application/json']);
        $this->assertSame(400, $emptyEcho['status']);

        $arrayEcho = $this->server->httpRequest('POST', '/modalities/center_2/echo', '[]', ['Content-Type: application/json']);
        $this->assertSame(400, $arrayEcho['status']);

        $okEmptyObj = $this->server->httpRequest('POST', '/modalities/center_2/echo', '{}', ['Content-Type: application/json']);
        $this->assertSame(200, $okEmptyObj['status']);

        $okTimeoutObj = $this->server->httpRequest('POST', '/modalities/center_2/echo', '{"Timeout":10}', ['Content-Type: application/json']);
        $this->assertSame(200, $okTimeoutObj['status']);

        // Fault injection: echo 500
        $this->server->setFaults(['echo' => 'fail_500']);
        $failEcho = $this->server->httpRequest('POST', '/modalities/center_2/echo', '{}', ['Content-Type: application/json']);
        $this->assertSame(500, $failEcho['status']);
    }

    public function testStoreSupportsSuccessAndFaultModes(): void
    {
        $this->assertNotNull($this->server);

        $study = $this->server->seedStudy([
            'StudyInstanceUID' => '1.2.826.0.1.3680043.10.555',
            'PatientName'      => 'Maria Lopez',
            'PatientID'        => 'PAC-555',
            'StudyDate'        => '20261119',
            'Modality'         => 'MR',
        ]);
        $this->server->httpRequest(
            'PUT',
            '/modalities/center_3',
            (string) json_encode(['AET' => 'DEST_MR', 'Host' => '10.0.0.12', 'Port' => 4242]),
            ['Content-Type: application/json']
        );

        $payload = (string) json_encode(['Resources' => [$study['ID']], 'Synchronous' => true]);

        $okStore = $this->server->httpRequest('POST', '/modalities/center_3/store', $payload, ['Content-Type: application/json']);
        $this->assertSame(200, $okStore['status']);
        $okJson = json_decode($okStore['body'], true);
        $this->assertSame(0, $okJson['FailedInstancesCount']);
        $this->assertGreaterThan(0, $okJson['InstancesCount']);
        $this->assertSame('DEST_MR', $okJson['RemoteAet']);

        $this->server->setFaults(['store' => 'partial_fail']);
        $partial = $this->server->httpRequest('POST', '/modalities/center_3/store', $payload, ['Content-Type: application/json']);
        $this->assertSame(200, $partial['status']);
        $partialJson = json_decode($partial['body'], true);
        $this->assertGreaterThan(0, $partialJson['FailedInstancesCount']);

        $this->server->setFaults(['store' => 'zero_instances']);
        $zero = $this->server->httpRequest('POST', '/modalities/center_3/store', $payload, ['Content-Type: application/json']);
        $this->assertSame(200, $zero['status']);
        $zeroJson = json_decode($zero['body'], true);
        $this->assertSame(0, $zeroJson['InstancesCount']);

        $this->server->setFaults(['store' => 'fail_500']);
        $err = $this->server->httpRequest('POST', '/modalities/center_3/store', $payload, ['Content-Type: application/json']);
        $this->assertSame(500, $err['status']);
    }

    public function testBasicAuthAndToolsFindPaginationWorkCorrectly(): void
    {
        $this->assertNotNull($this->server);

        $this->server->seedStudy(['StudyInstanceUID' => '1.2.3.1', 'PatientName' => 'Ana Uno', 'StudyDate' => '20261110']);
        $this->server->seedStudy(['StudyInstanceUID' => '1.2.3.2', 'PatientName' => 'Beto Dos', 'StudyDate' => '20261115']);
        $this->server->seedStudy(['StudyInstanceUID' => '1.2.3.3', 'PatientName' => 'Carla Tres', 'StudyDate' => '20261119']);

        $page1 = $this->server->httpRequest(
            'POST',
            '/tools/find',
            (string) json_encode([
                'Level'   => 'Study',
                'Expand'  => true,
                'Since'   => 0,
                'Limit'   => 2,
                'Query'   => new \stdClass(),
                'OrderBy' => [['Type' => 'DicomTag', 'Key' => 'StudyDate', 'Direction' => 'DESC']],
            ]),
            ['Content-Type: application/json']
        );
        $this->assertSame(200, $page1['status']);
        $items1 = json_decode($page1['body'], true);
        $this->assertCount(2, $items1);
        $this->assertSame('1.2.3.3', $items1[0]['MainDicomTags']['StudyInstanceUID']);
        $this->assertSame('1.2.3.2', $items1[1]['MainDicomTags']['StudyInstanceUID']);

        $page2 = $this->server->httpRequest(
            'POST',
            '/tools/find',
            (string) json_encode([
                'Level'   => 'Study',
                'Expand'  => true,
                'Since'   => 2,
                'Limit'   => 2,
                'Query'   => new \stdClass(),
                'OrderBy' => [['Type' => 'DicomTag', 'Key' => 'StudyDate', 'Direction' => 'DESC']],
            ]),
            ['Content-Type: application/json']
        );
        $items2 = json_decode($page2['body'], true);
        $this->assertCount(1, $items2);
        $this->assertSame('1.2.3.1', $items2[0]['MainDicomTags']['StudyInstanceUID']);

        // Basic Auth enforcement
        $this->server->setAuth('orthanc_u', 'orthanc_p');
        $unauth = $this->server->httpRequest('GET', '/system');
        $this->assertSame(401, $unauth['status']);

        $token  = base64_encode('orthanc_u:orthanc_p');
        $authed = $this->server->httpRequest('GET', '/system', null, ['Authorization: Basic ' . $token]);
        $this->assertSame(200, $authed['status']);
    }
}
