<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F7: Orthanc synchronization failure & edge scenarios.
 */
final class F07_OrthancSyncFailureAndEdgeTest extends E2ETestCase
{
    public function testModalityPut503RecordsSyncErrorAndNeverFailsSilently(): void
    {
        $this->loginAsRole('admin');
        $this->orthanc->setFaults(['modality_put' => 503]);

        $res = $this->formPost('centers', [
            'name'             => 'Centro Sync 503',
            'ae_title'         => 'CTR_PUT503',
            'remote_node_name' => 'Nodo 503',
            'remote_ae_title'  => 'PUT503_AET',
            'remote_host'      => '10.2.2.10',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);
        $res->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CTR_PUT503')->first();
        $this->assertSame('error', $center['orthanc_sync_status']);
        $this->assertNotEmpty($center['orthanc_sync_error']);

        // Flash must communicate warning/error rather than clean silent success
        $hasWarningOrError = session('error') !== null || session('warning') !== null || str_contains((string) session('message'), 'Orthanc');
        $this->assertTrue($hasWarningOrError, 'Sync failure must be surfaced in flashdata, never silent.');
    }

    public function testModalityDelete503OnDeactivationRecordsSyncError(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Centro Del 503',
            'ae_title'         => 'CTR_DEL503',
            'remote_node_name' => 'Nodo Del 503',
            'remote_ae_title'  => 'DEL503_AET',
            'remote_host'      => '10.2.2.11',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_DEL503')->first();
        $centerId = (int) $center['id'];

        $this->orthanc->setFaults(['modality_delete' => 503]);
        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Centro Del 503',
            'ae_title'         => 'CTR_DEL503',
            'active'           => '1',
            'remote_node_name' => 'Nodo Del 503',
            'remote_ae_title'  => 'DEL503_AET',
            'remote_host'      => '10.2.2.11',
            'remote_port'      => '4242',
            // remote_active omitted -> triggers DELETE
        ])->assertRedirectTo('/centers');

        $updated = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame('error', $updated['orthanc_sync_status']);
        $this->assertNotEmpty($updated['orthanc_sync_error']);
    }

    public function testCreatingCenterWithRemoteActiveZeroDoesNotRegisterModality(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Centro Remoto Inactivo',
            'ae_title'         => 'CTR_RINACT',
            'remote_node_name' => 'Nodo Inactivo',
            'remote_ae_title'  => 'RINACT_AET',
            'remote_host'      => '10.2.2.12',
            'remote_port'      => '4242',
            // remote_active omitted
        ])->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CTR_RINACT')->first();
        $this->assertArrayNotHasKey('center_' . (int) $center['id'], $this->orthanc->getModalities());
    }

    public function testClearingRemoteFieldsOnUpdateRemovesSyncedModality(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Centro Clear Remote',
            'ae_title'         => 'CTR_CLEAR',
            'remote_node_name' => 'Nodo Clear',
            'remote_ae_title'  => 'CLEAR_AET',
            'remote_host'      => '10.2.2.13',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_CLEAR')->first();
        $centerId = (int) $center['id'];
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Centro Clear Remote',
            'ae_title'         => 'CTR_CLEAR',
            'active'           => '1',
            'remote_node_name' => '',
            'remote_ae_title'  => '',
            'remote_host'      => '',
            'remote_port'      => '',
        ])->assertRedirectTo('/centers');

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }

    public function testInactiveCenterWithRemoteActiveOneKeepsModalityRemoved(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter(['ae_title' => 'CTR_INACT_SYNC', 'active' => 1]);
        $centerId = (int) $seeded['center']['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Centro Inactivo',
            'ae_title'         => 'CTR_INACT_SYNC',
            // active omitted => 0
            'remote_node_name' => 'Nodo Keep Off',
            'remote_ae_title'  => 'KEEPOFF_AET',
            'remote_host'      => '10.2.2.14',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }
}
