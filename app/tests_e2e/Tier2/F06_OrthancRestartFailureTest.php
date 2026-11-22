<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\MedicalCenterModel;
use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F6: Orthanc outage, restart, and empty-state edge cases.
 */
final class F06_OrthancRestartFailureTest extends E2ETestCase
{
    public function testIsAliveReturnsFalseWhenOrthancStopsOrReturns503(): void
    {
        $client = Services::orthanc();
        $this->assertTrue($client->isAlive());

        $this->orthanc->setFaults(['system' => 503]);
        $this->assertFalse($client->isAlive());

        $this->orthanc->stop();
        $this->assertFalse($client->isAlive());
    }

    public function testEmptyModalitiesListExpandReturnsEmptyPhpArrayWithoutTypeError(): void
    {
        $res = Services::orthanc()->listModalities(true);
        $this->assertTrue($res['ok']);
        $this->assertSame([], $res['data']);
    }

    public function testCreatingConfiguredCenterWhileOrthancDownFlagsSyncErrorInDb(): void
    {
        $this->loginAsRole('admin');
        $this->orthanc->stop();

        $res = $this->formPost('centers', [
            'name'             => 'Centro Orthanc Down',
            'ae_title'         => 'CTR_DOWN',
            'remote_node_name' => 'Nodo Down',
            'remote_ae_title'  => 'DOWN_AET',
            'remote_host'      => '10.0.0.99',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);
        $res->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CTR_DOWN')->first();
        $this->assertIsArray($center);
        $this->assertNotSame('synced', $center['orthanc_sync_status']);
        $this->assertNotEmpty($center['orthanc_sync_error']);
    }

    public function testTestConnectionFailsExplicitlyWhenOrthancIsStopped(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_STOP_ECHO',
            'active'           => 1,
            'remote_node_name' => 'Nodo Stop',
            'remote_ae_title'  => 'STOP_AET',
            'remote_host'      => '10.0.0.88',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $this->orthanc->stop();

        $res = $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $this->assertGreaterThanOrEqual(400, $res->response()->getStatusCode());
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertFalse($json['ok']);
        $this->assertNotEmpty($json['message']);
    }

    public function testCenterSavedDuringOutageRecoversSyncOnTestConnectionAfterRestart(): void
    {
        $this->loginAsRole('admin');
        $this->orthanc->stop();

        $this->formPost('centers', [
            'name'             => 'Centro Recover',
            'ae_title'         => 'CTR_RECOV',
            'remote_node_name' => 'Nodo Recov',
            'remote_ae_title'  => 'RECOV_AET',
            'remote_host'      => '10.0.0.77',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_RECOV')->first();
        $centerId = (int) $center['id'];
        $this->assertNotSame('synced', $center['orthanc_sync_status']);

        $this->orthanc->start();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $echo = $this->ajaxPost('centers/test-connection/' . $centerId);
        $echo->assertStatus(200);
        $json = json_decode((string) $echo->response()->getBody(), true);
        $this->assertTrue($json['ok']);
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }
}
