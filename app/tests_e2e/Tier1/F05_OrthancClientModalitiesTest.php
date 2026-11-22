<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F5: Orthanc REST client modality management & Basic Auth.
 */
final class F05_OrthancClientModalitiesTest extends E2ETestCase
{
    public function testServicesOrthancReturnsSharedInstanceAndRespectsEnv(): void
    {
        $a = Services::orthanc();
        $b = Services::orthanc();
        $this->assertSame($a, $b);
        $this->assertTrue($a->isAlive());
    }

    public function testPutAndGetModalityViaRealHttpRoundTrip(): void
    {
        $client = Services::orthanc();

        $put = $client->putModality('center_10', [
            'AET'  => 'RADIOLOGY_AET',
            'Host' => '10.10.10.10',
            'Port' => 4242,
        ]);
        $this->assertTrue($put['ok']);
        $this->assertContains($put['status'], [200, 201]);

        $get = $client->getModality('center_10');
        $this->assertTrue($get['ok']);
        $this->assertSame('RADIOLOGY_AET', $get['data']['AET']);
        $this->assertSame('10.10.10.10', $get['data']['Host']);
        $this->assertSame(4242, $get['data']['Port']);
        $this->assertTrue($get['data']['AllowEcho']);
        $this->assertTrue($get['data']['AllowStore']);
    }

    public function testListModalitiesSupportsExpandAndCompactModes(): void
    {
        $client = Services::orthanc();
        $client->putModality('center_1', ['AET' => 'AET_ONE', 'Host' => '10.0.0.1', 'Port' => 4242]);
        $client->putModality('center_2', ['AET' => 'AET_TWO', 'Host' => '10.0.0.2', 'Port' => 11112]);

        $compact = $client->listModalities(false);
        $this->assertTrue($compact['ok']);
        $this->assertContains('center_1', $compact['data']);
        $this->assertContains('center_2', $compact['data']);

        $expanded = $client->listModalities(true);
        $this->assertTrue($expanded['ok']);
        $this->assertArrayHasKey('center_1', $expanded['data']);
        $this->assertSame('AET_TWO', $expanded['data']['center_2']['AET']);
    }

    public function testDeleteModalityRemovesFromOrthanc(): void
    {
        $client = Services::orthanc();
        $client->putModality('center_20', ['AET' => 'DEL_AET', 'Host' => '10.0.0.20', 'Port' => 4242]);

        $del = $client->deleteModality('center_20');
        $this->assertTrue($del['ok']);

        $get = $client->getModality('center_20');
        $this->assertFalse($get['ok']);
        $this->assertSame(404, $get['status']);
    }

    public function testEchoModalitySendsNonEmptyJsonObjectAndReturnsLatency(): void
    {
        $client = Services::orthanc();
        $client->putModality('center_30', ['AET' => 'ECHO_AET', 'Host' => '10.0.0.30', 'Port' => 4242]);

        $echo = $client->echoModality('center_30', 10);
        $this->assertTrue($echo['ok']);
        $this->assertSame(200, $echo['status']);
        $this->assertIsInt($echo['latency_ms']);
        $this->assertGreaterThanOrEqual(0, $echo['latency_ms']);

        $requests = array_values(array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => $r['path'] === '/modalities/center_30/echo'
        ));
        $this->assertNotEmpty($requests);
        $lastEchoBody = trim((string) end($requests)['body']);
        $this->assertStringStartsWith('{', $lastEchoBody);
    }

    public function testStoreToModalityAndBasicAuthWorkEndToEnd(): void
    {
        $this->orthanc->setAuth('orthanc_admin', 'orthanc_secret');
        $this->setOrthancEnv($this->orthanc->getUrl(), 'orthanc_admin', 'orthanc_secret');

        $study  = $this->orthanc->seedStudy(['StudyInstanceUID' => '1.2.826.0.1.3680043.10.777']);
        $client = Services::orthanc();

        $this->assertTrue($client->isAlive());
        $client->putModality('center_40', ['AET' => 'STORE_AET', 'Host' => '10.0.0.40', 'Port' => 4242]);

        $store = $client->storeToModality('center_40', [$study['ID']], true);
        $this->assertTrue($store['ok']);
        $this->assertSame(200, $store['status']);
        $this->assertGreaterThan(0, $store['instances_count']);
        $this->assertSame(0, $store['failed_count']);
    }
}
