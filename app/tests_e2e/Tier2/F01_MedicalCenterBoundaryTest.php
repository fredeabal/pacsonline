<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F1: Medical center schema & active-node boundary conditions.
 */
final class F01_MedicalCenterBoundaryTest extends E2ETestCase
{
    public function testInactiveCenterWithActiveRemoteFlagIsNotUsableForSendOrEcho(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.101');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_INACT1',
            'active'           => 0,
            'remote_node_name' => 'Nodo Center Off',
            'remote_ae_title'  => 'OFF_CTR',
            'remote_host'      => '10.1.1.10',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $echoRes = $this->ajaxPost('centers/test-connection/' . $centerId);
        $echoRes->assertStatus(422);

        $sendRes = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ]);
        $sendRes->assertStatus(422);
    }

    public function testActiveCenterWithDisabledRemoteFlagIsNotUsableForSendOrEcho(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.102');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_ROFF1',
            'active'           => 1,
            'remote_node_name' => 'Nodo Remote Off',
            'remote_ae_title'  => 'OFF_REM',
            'remote_host'      => '10.1.1.11',
            'remote_port'      => 4242,
            'remote_active'    => 0,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(422);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(422);
    }

    public function testCenterMissingAnyRemoteFieldIsNotUsableEvenIfFlagsAreOne(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.103');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_PART1',
            'active'           => 1,
            'remote_node_name' => 'Nodo Incompleto',
            'remote_ae_title'  => 'PART_AET',
            'remote_host'      => null,
            'remote_port'      => null,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(422);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(422);
    }

    public function testDuplicateGatewayAeTitleIsRejectedOnCreateButAllowedOnSelfUpdate(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter(['name' => 'Orig Name', 'ae_title' => 'DUP_AET']);

        $dupRes = $this->formPost('centers', [
            'name'     => 'Otra Clinica',
            'ae_title' => 'DUP_AET',
        ]);
        $dupRes->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('ae_title', $errors);

        $selfUpdate = $this->formPost('centers/update/' . (int) $seeded['center']['id'], [
            'name'     => 'Orig Name Updated',
            'ae_title' => 'DUP_AET',
            'active'   => '1',
        ]);
        $selfUpdate->assertRedirectTo('/centers');
        $this->assertSame('Orig Name Updated', model(MedicalCenterModel::class)->find((int) $seeded['center']['id'])['name']);
    }

    public function testMultipleCentersCanShareSameRemoteAeTitleWithDistinctOrthancModalityIds(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Sede Norte',
            'ae_title'         => 'SEDE_NORTE',
            'remote_node_name' => 'PACS Corporativo',
            'remote_ae_title'  => 'CORP_PACS',
            'remote_host'      => '10.9.9.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->formPost('centers', [
            'name'             => 'Sede Sur',
            'ae_title'         => 'SEDE_SUR',
            'remote_node_name' => 'PACS Corporativo',
            'remote_ae_title'  => 'CORP_PACS',
            'remote_host'      => '10.9.9.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $norte = model(MedicalCenterModel::class)->where('ae_title', 'SEDE_NORTE')->first();
        $sur   = model(MedicalCenterModel::class)->where('ae_title', 'SEDE_SUR')->first();

        $modalities = $this->orthanc->getModalities();
        $this->assertArrayHasKey('center_' . (int) $norte['id'], $modalities);
        $this->assertArrayHasKey('center_' . (int) $sur['id'], $modalities);
    }
}
