<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F7: Orthanc lifecycle synchronization on store/update/activate/deactivate.
 */
final class F07_OrthancLifecycleSyncHappyTest extends E2ETestCase
{
    public function testCreatingActiveConfiguredCenterRegistersModalityInOrthanc(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Hospital Sync Create',
            'ae_title'         => 'HOSP_SC',
            'remote_node_name' => 'Nodo Sync Create',
            'remote_ae_title'  => 'SYNC_CREATE_AET',
            'remote_host'      => '10.11.12.13',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center     = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_SC')->first();
        $modalityId = 'center_' . (int) $center['id'];
        $modalities = $this->orthanc->getModalities();

        $this->assertArrayHasKey($modalityId, $modalities);
        $this->assertSame('SYNC_CREATE_AET', $modalities[$modalityId]['AET']);
        $this->assertSame('10.11.12.13', $modalities[$modalityId]['Host']);
        $this->assertSame(4242, (int) $modalities[$modalityId]['Port']);
        $this->assertSame('synced', $center['orthanc_sync_status']);
        $this->assertNotEmpty($center['orthanc_synced_at']);
    }

    public function testUpdatingRemoteParametersUpdatesExistingOrthancModality(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Hospital Sync Update',
            'ae_title'         => 'HOSP_SU',
            'remote_node_name' => 'Nodo V1',
            'remote_ae_title'  => 'V1_AET',
            'remote_host'      => '10.1.1.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_SU')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Hospital Sync Update',
            'ae_title'         => 'HOSP_SU',
            'active'           => '1',
            'remote_node_name' => 'Nodo V2',
            'remote_ae_title'  => 'V2_AET',
            'remote_host'      => '10.2.2.2',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $modality = $this->orthanc->getModalities()['center_' . $centerId] ?? null;
        $this->assertIsArray($modality);
        $this->assertSame('V2_AET', $modality['AET']);
        $this->assertSame('10.2.2.2', $modality['Host']);
        $this->assertSame(11112, (int) $modality['Port']);
    }

    public function testDeactivatingRemoteNodeDeletesModalityFromOrthanc(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Hospital Deactivate Remote',
            'ae_title'         => 'HOSP_DR',
            'remote_node_name' => 'Nodo DR',
            'remote_ae_title'  => 'DR_AET',
            'remote_host'      => '10.3.3.3',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_DR')->first();
        $centerId = (int) $center['id'];
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Hospital Deactivate Remote',
            'ae_title'         => 'HOSP_DR',
            'active'           => '1',
            'remote_node_name' => 'Nodo DR',
            'remote_ae_title'  => 'DR_AET',
            'remote_host'      => '10.3.3.3',
            'remote_port'      => '4242',
            // remote_active disabled
        ])->assertRedirectTo('/centers');

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }

    public function testDeactivatingCenterDeletesModalityFromOrthanc(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Hospital Deactivate Center',
            'ae_title'         => 'HOSP_DC',
            'remote_node_name' => 'Nodo DC',
            'remote_ae_title'  => 'DC_AET',
            'remote_host'      => '10.4.4.4',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_DC')->first();
        $centerId = (int) $center['id'];
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Hospital Deactivate Center',
            'ae_title'         => 'HOSP_DC',
            // active omitted => center active = 0
            'remote_node_name' => 'Nodo DC',
            'remote_ae_title'  => 'DC_AET',
            'remote_host'      => '10.4.4.4',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }

    public function testReactivatingCenterReRegistersModalityInOrthanc(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Hospital Reactivate',
            'ae_title'         => 'HOSP_RE',
            'remote_node_name' => 'Nodo RE',
            'remote_ae_title'  => 'RE_AET',
            'remote_host'      => '10.5.5.5',
            'remote_port'      => '4242',
            // initially inactive remote
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_RE')->first();
        $centerId = (int) $center['id'];
        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Hospital Reactivate',
            'ae_title'         => 'HOSP_RE',
            'active'           => '1',
            'remote_node_name' => 'Nodo RE',
            'remote_ae_title'  => 'RE_AET',
            'remote_host'      => '10.5.5.5',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }
}
