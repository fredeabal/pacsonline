<?php

declare(strict_types=1);

namespace Tests\E2E\Tier3;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 3 — Cross-Feature Pairwise Combination Tests (F1–F10).
 */
final class CrossFeaturePairwiseTest extends E2ETestCase
{
    public function testCreateSyncReloadAndVerifyOrthancModality(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Clinica Pairwise 1',
            'ae_title'         => 'CTR_PW1',
            'remote_node_name' => 'Nodo PW1',
            'remote_ae_title'  => 'pw1_aet',
            'remote_host'      => '10.10.1.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_PW1')->first();
        $centerId = (int) $center['id'];

        $editBody = (string) $this->getPage('centers/edit/' . $centerId)->response()->getBody();
        $this->assertStringContainsString('PW1_AET', $editBody);
        $this->assertStringContainsString('10.10.1.1', $editBody);

        $modalities = $this->orthanc->getModalities();
        $this->assertArrayHasKey('center_' . $centerId, $modalities);
        $this->assertSame('PW1_AET', $modalities['center_' . $centerId]['AET']);
    }

    public function testEditAndRenameRemoteAeTitleUpdatesSameModalityWithoutOrphan(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica Rename',
            'ae_title'         => 'CTR_RENAME',
            'remote_node_name' => 'Nodo Old',
            'remote_ae_title'  => 'OLD_AET',
            'remote_host'      => '10.10.1.2',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_RENAME')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Rename',
            'ae_title'         => 'CTR_RENAME',
            'active'           => '1',
            'remote_node_name' => 'Nodo New',
            'remote_ae_title'  => 'NEW_AET',
            'remote_host'      => '10.10.1.3',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $modalities = $this->orthanc->getModalities();
        $this->assertCount(1, $modalities);
        $this->assertArrayHasKey('center_' . $centerId, $modalities);
        $this->assertSame('NEW_AET', $modalities['center_' . $centerId]['AET']);
        $this->assertSame('10.10.1.3', $modalities['center_' . $centerId]['Host']);
        $this->assertSame(11112, (int) $modalities['center_' . $centerId]['Port']);
    }

    public function testDeactivateRemoteRemovesModalityAndBlocksBothEchoAndSend(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.103');

        $this->formPost('centers', [
            'name'             => 'Clinica Deact Both',
            'ae_title'         => 'CTR_DBOTH',
            'remote_node_name' => 'Nodo Both',
            'remote_ae_title'  => 'BOTH_AET',
            'remote_host'      => '10.10.1.4',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_DBOTH')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Deact Both',
            'ae_title'         => 'CTR_DBOTH',
            'active'           => '1',
            'remote_node_name' => 'Nodo Both',
            'remote_ae_title'  => 'BOTH_AET',
            'remote_host'      => '10.10.1.4',
            'remote_port'      => '4242',
        ])->assertRedirectTo('/centers');

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(422);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(422);
    }

    public function testReactivateRemoteReSyncsModalityAndRestoresEchoAndSend(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.104');

        $this->formPost('centers', [
            'name'             => 'Clinica React',
            'ae_title'         => 'CTR_REACT',
            'remote_node_name' => 'Nodo React',
            'remote_ae_title'  => 'REACT_AET',
            'remote_host'      => '10.10.1.5',
            'remote_port'      => '4242',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_REACT')->first();
        $centerId = (int) $center['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(422);

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica React',
            'ae_title'         => 'CTR_REACT',
            'active'           => '1',
            'remote_node_name' => 'Nodo React',
            'remote_ae_title'  => 'REACT_AET',
            'remote_host'      => '10.10.1.5',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
    }

    public function testGatewayIngestFollowedByDicomSendAndAuditVerification(): void
    {
        $source = $this->seedMedicalCenter(['name' => 'Clinica Origen', 'ae_title' => 'CTR_SRC']);
        $dest   = $this->seedMedicalCenter([
            'name'             => 'Clinica Destino',
            'ae_title'         => 'CTR_DST',
            'active'           => 1,
            'remote_node_name' => 'Destino Final',
            'remote_ae_title'  => 'DST_AET',
            'remote_host'      => '10.10.1.6',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $studyUid = '1.2.826.0.1.3680043.30.105';
        $ingest   = $this->ingestDicomViaApi($source['plain_token'], $studyUid, 'Luis^Gateway', 'PAC-GW-5');
        $ingest->assertStatus(200);

        $this->loginAsRole('admin');
        $send = $this->ajaxPost('studies/send/' . $studyUid, [
            'center_id' => (int) $dest['center']['id'],
        ]);
        $send->assertStatus(200);

        $this->assertNotEmpty($this->getAuditLogsByAction('ingest.dicom_received'));
        $sentLogs = $this->getAuditLogsByAction('study.sent_dicom');
        $this->assertNotEmpty($sentLogs);
        $this->assertSame($studyUid, $sentLogs[0]['study_instance_uid']);
    }

    public function testValidationFailureDuringEditPreservesExistingOrthancModality(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica Preserve',
            'ae_title'         => 'CTR_PRES',
            'remote_node_name' => 'Nodo Pres',
            'remote_ae_title'  => 'PRES_AET',
            'remote_host'      => '10.10.1.7',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_PRES')->first();
        $centerId = (int) $center['id'];

        $badUpdate = $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Preserve',
            'ae_title'         => 'CTR_PRES',
            'active'           => '1',
            'remote_node_name' => 'Nodo Pres',
            'remote_ae_title'  => 'PRES_AET',
            'remote_host'      => '10.10.1.7',
            'remote_port'      => '70000',
            'remote_active'    => '1',
        ]);
        $badUpdate->assertRedirect();
        $this->assertArrayHasKey('remote_port', session('errors'));

        $mod = $this->orthanc->getModalities()['center_' . $centerId] ?? null;
        $this->assertIsArray($mod);
        $this->assertSame(4242, (int) $mod['Port']);
    }

    public function testPermissionBoundaryAcrossCentersEchoAndStudySendForMedico(): void
    {
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.107');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_MED_PW',
            'active'           => 1,
            'remote_node_name' => 'Nodo Med PW',
            'remote_ae_title'  => 'MEDPW_AET',
            'remote_host'      => '10.10.1.8',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->loginAsRole('medico');
        $caseRes = $this->getPage('studies/case/' . $study['MainDicomTags']['StudyInstanceUID']);
        $caseRes->assertStatus(200);
        $this->assertStringNotContainsString('id="sendStudyModal"', (string) $caseRes->response()->getBody());

        $this->getPage('centers')->assertRedirectTo('/');
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(403);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(403);
    }

    public function testBasicAuthOrthancSyncEchoAndStoreCombined(): void
    {
        $this->orthanc->setAuth('pacs_orthanc', 's3cr3t_pw');
        $this->setOrthancEnv($this->orthanc->getUrl(), 'pacs_orthanc', 's3cr3t_pw');

        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.108');

        $this->formPost('centers', [
            'name'             => 'Clinica BasicAuth',
            'ae_title'         => 'CTR_BAUTH',
            'remote_node_name' => 'Nodo Auth',
            'remote_ae_title'  => 'BAUTH_AET',
            'remote_host'      => '10.10.1.9',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_BAUTH')->first();
        $centerId = (int) $center['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
    }

    public function testInactiveCenterExcludedFromStudiesSendSelectionAndRejectedOnPost(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.109');

        $active = $this->seedMedicalCenter([
            'name'             => 'Centro Activo Visible',
            'ae_title'         => 'CTR_VIS_ACT',
            'active'           => 1,
            'remote_node_name' => 'Nodo Visible',
            'remote_ae_title'  => 'VIS_ACT_AET',
            'remote_host'      => '10.10.1.10',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $inactive = $this->seedMedicalCenter([
            'name'             => 'Centro Oculto Inactivo',
            'ae_title'         => 'CTR_HID_INA',
            'active'           => 1,
            'remote_node_name' => 'Nodo Oculto',
            'remote_ae_title'  => 'HID_INA_AET',
            'remote_host'      => '10.10.1.11',
            'remote_port'      => 4242,
            'remote_active'    => 0,
        ]);

        $studiesBody = (string) $this->getPage('studies')->response()->getBody();
        $this->assertStringContainsString('VIS_ACT_AET', $studiesBody);
        $this->assertStringNotContainsString('HID_INA_AET', $studiesBody);

        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $inactive['center']['id'],
        ])->assertStatus(422);

        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $active['center']['id'],
        ])->assertStatus(200);
    }

    public function testOrthancRestartBetweenCenterConfigAndStudySendPreservesDelivery(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.110');

        $this->formPost('centers', [
            'name'             => 'Clinica Restart PW',
            'ae_title'         => 'CTR_RPW',
            'remote_node_name' => 'Nodo RPW',
            'remote_ae_title'  => 'RPW_AET',
            'remote_host'      => '10.10.1.12',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_RPW')->first();
        $centerId = (int) $center['id'];

        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
    }

    public function testTokenRotationDoesNotDisruptRemoteModalitySyncOrCStore(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.111');

        $this->formPost('centers', [
            'name'             => 'Clinica RotStore',
            'ae_title'         => 'CTR_ROTS',
            'remote_node_name' => 'Nodo RotS',
            'remote_ae_title'  => 'ROTS_AET',
            'remote_host'      => '10.10.1.13',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_ROTS')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/rotate-token/' . $centerId)->assertRedirectTo('/centers');
        $newToken = session('generated_token');

        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
        $this->ingestDicomViaApi((string) $newToken, '1.2.826.0.1.3680043.30.112')->assertStatus(200);
    }

    public function testSyncFailureOnCreateRecoveredBySubsequentEditUpdate(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.113');

        $this->orthanc->setFaults(['modality_put' => 503]);
        $this->formPost('centers', [
            'name'             => 'Clinica Outage Recover',
            'ae_title'         => 'CTR_OREC',
            'remote_node_name' => 'Nodo ORec',
            'remote_ae_title'  => 'OREC_AET',
            'remote_host'      => '10.10.1.14',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_OREC')->first();
        $centerId = (int) $center['id'];
        $this->assertSame('error', $center['orthanc_sync_status']);

        $this->orthanc->clearFaults();
        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Outage Recover',
            'ae_title'         => 'CTR_OREC',
            'active'           => '1',
            'remote_node_name' => 'Nodo ORec',
            'remote_ae_title'  => 'OREC_AET',
            'remote_host'      => '10.10.1.14',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $updated = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame('synced', $updated['orthanc_sync_status']);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
    }

    public function testPartialFailureOnCStoreDoesNotReportSuccessEvenWhenEchoPasses(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.30.114');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_PAR_ECHO',
            'active'           => 1,
            'remote_node_name' => 'Nodo Par Echo',
            'remote_ae_title'  => 'PARECHO_AET',
            'remote_host'      => '10.10.1.15',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);

        $this->orthanc->setFaults(['store' => 'partial_fail']);
        $send = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ]);
        $this->assertGreaterThanOrEqual(400, $send->response()->getStatusCode());
        $json = json_decode((string) $send->response()->getBody(), true);
        $this->assertFalse($json['ok']);
        $this->assertGreaterThan(0, (int) $json['failed_count']);
    }

    public function testDeactivateGatewayCenterPreservesRemoteConfigInDbWhileBlockingIngestAndOrthanc(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica FullOff',
            'ae_title'         => 'CTR_FULLOFF',
            'remote_node_name' => 'Nodo FullOff',
            'remote_ae_title'  => 'FULLOFF_AET',
            'remote_host'      => '10.10.1.16',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $token    = (string) session('generated_token');
        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CTR_FULLOFF')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica FullOff',
            'ae_title'         => 'CTR_FULLOFF',
            // active omitted => 0
            'remote_node_name' => 'Nodo FullOff',
            'remote_ae_title'  => 'FULLOFF_AET',
            'remote_host'      => '10.10.1.16',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $updated = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame(0, (int) $updated['active']);
        $this->assertSame('FULLOFF_AET', $updated['remote_ae_title']);
        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $this->ingestDicomViaApi($token, '1.2.826.0.1.3680043.30.115')->assertStatus(401);
    }
}
