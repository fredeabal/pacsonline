<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F5: OrthancClient edge & fault-injection behavior.
 */
final class F05_OrthancClientEdgeAndFaultTest extends E2ETestCase
{
    public function testWrongBasicAuthCredentialsReturn401AndAliveFalse(): void
    {
        $this->orthanc->setAuth('real_user', 'real_pass');
        $this->setOrthancEnv($this->orthanc->getUrl(), 'wrong_user', 'wrong_pass');

        $client = Services::orthanc();
        $this->assertFalse($client->isAlive());

        $mod = $client->getModality('center_1');
        $this->assertFalse($mod['ok']);
        $this->assertSame(401, $mod['status']);
    }

    public function testEchoModalityHandles500And404Cleanly(): void
    {
        $client = Services::orthanc();

        $missing = $client->echoModality('nonexistent_mod');
        $this->assertFalse($missing['ok']);
        $this->assertSame(404, $missing['status']);
        $this->assertNotEmpty($missing['error']);

        $client->putModality('center_50', ['AET' => 'FAIL500', 'Host' => '10.0.0.50', 'Port' => 4242]);
        $this->orthanc->setFaults(['echo' => 'fail_500']);

        $err = $client->echoModality('center_50');
        $this->assertFalse($err['ok']);
        $this->assertSame(500, $err['status']);
        $this->assertNotEmpty($err['error']);
    }

    public function testStoreToModalityFailsOnHttp200WithFailedInstancesCountPositive(): void
    {
        $study  = $this->orthanc->seedStudy(['StudyInstanceUID' => '1.2.826.0.1.3680043.20.501']);
        $client = Services::orthanc();
        $client->putModality('center_51', ['AET' => 'PARTIAL_AET', 'Host' => '10.0.0.51', 'Port' => 4242]);
        $this->orthanc->setFaults(['store' => 'partial_fail']);

        $res = $client->storeToModality('center_51', [$study['ID']], true);
        $this->assertFalse($res['ok']);
        $this->assertSame(200, $res['status']);
        $this->assertGreaterThan(0, $res['failed_count']);
        $this->assertNotEmpty($res['error']);
    }

    public function testStoreToModalityFailsOnHttp200WithZeroInstances(): void
    {
        $study  = $this->orthanc->seedStudy(['StudyInstanceUID' => '1.2.826.0.1.3680043.20.502']);
        $client = Services::orthanc();
        $client->putModality('center_52', ['AET' => 'ZERO_AET', 'Host' => '10.0.0.52', 'Port' => 4242]);
        $this->orthanc->setFaults(['store' => 'zero_instances']);

        $res = $client->storeToModality('center_52', [$study['ID']], true);
        $this->assertFalse($res['ok']);
        $this->assertSame(200, $res['status']);
        $this->assertSame(0, $res['instances_count']);
        $this->assertNotEmpty($res['error']);
    }

    public function testDeleteModalityIsIdempotentWhenAlreadyAbsent(): void
    {
        $client = Services::orthanc();
        $del    = $client->deleteModality('never_existed_999');
        $this->assertTrue($del['ok']);
        $this->assertSame(404, $del['status']);
    }

    public function testStoreToModalityReturnsErrorWhenOrthancIsUnreachable(): void
    {
        $client = Services::orthanc();
        $this->orthanc->stop();

        $res = $client->storeToModality('center_1', ['nonexistent'], true);
        $this->assertFalse($res['ok']);
        $this->assertSame(0, $res['status']);
        $this->assertNotEmpty($res['error']);
    }
}
