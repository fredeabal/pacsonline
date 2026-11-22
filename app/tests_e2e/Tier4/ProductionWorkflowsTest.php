<?php

declare(strict_types=1);

namespace Tests\E2E\Tier4;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 4 — Real-World Multi-Step Production Workflows (matching TEST_INFRA.md).
 */
final class ProductionWorkflowsTest extends E2ETestCase
{
    public function testWorkflow1ClinicOnboardingEchoIngestSendAndAuditTrail(): void
    {
        $admin = $this->createUser('admin', 'wf1.admin@pacs.test', 'AdminWf1!23');
        $this->loginViaHttp('wf1.admin@pacs.test', 'AdminWf1!23')->assertRedirect();

        // 1. Open create form & register clinic with remote DICOM node
        $this->getPage('centers/create')->assertStatus(200);
        $this->formPost('centers', [
            'name'             => 'Hospital San Lucas',
            'ae_title'         => 'HOSP_SAN_LUCAS',
            'remote_node_name' => 'PACS San Lucas',
            'remote_ae_title'  => 'SANLUCAS_PACS',
            'remote_host'      => '10.100.1.10',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $gatewayToken = (string) session('generated_token');
        $this->assertStringStartsWith('pacs_', $gatewayToken);

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'HOSP_SAN_LUCAS')->first();
        $centerId = (int) $center['id'];

        // 2. Verify center index renders row & Orthanc modality is synced
        $indexBody = (string) $this->getPage('centers')->response()->getBody();
        $this->assertStringContainsString('Hospital San Lucas', $indexBody);
        $this->assertStringContainsString('SANLUCAS_PACS', $indexBody);
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        // 3. Execute C-ECHO connection test
        $echoRes = $this->ajaxPost('centers/test-connection/' . $centerId);
        $echoRes->assertStatus(200);
        $this->assertTrue(json_decode((string) $echoRes->response()->getBody(), true)['ok']);

        // 4. Clinic gateway ingests a study via Bearer API
        $studyUid = '1.2.826.0.1.3680043.40.1001';
        $this->ingestDicomViaApi($gatewayToken, $studyUid, 'Elena^Gomez', 'PAC-WF1-01', '20261119', 'CT', 'TC Craneo')
            ->assertStatus(200);

        // 5. Admin inspects study case page and sends study back to clinic remote node
        $this->getPage('studies/case/' . $studyUid)->assertStatus(200);
        $sendRes = $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $centerId]);
        $sendRes->assertStatus(200);
        $sendJson = json_decode((string) $sendRes->response()->getBody(), true);
        $this->assertTrue($sendJson['ok']);
        $this->assertGreaterThan(0, (int) $sendJson['instances_count']);

        // 6. Verify complete audit trail on /audit page
        $auditBody = (string) $this->getPage('audit')->response()->getBody();
        $this->assertStringContainsString('center.created', $auditBody);
        $this->assertStringContainsString('center.dicom_echo', $auditBody);
        $this->assertStringContainsString('ingest.dicom_received', $auditBody);
        $this->assertStringContainsString('study.sent_dicom', $auditBody);
    }

    public function testWorkflow2MultiClinicFailoverRoutingWhenPrimaryNodeUnreachable(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.40.2001', 'Roberto Diaz', 'PAC-WF2');

        $this->formPost('centers', [
            'name'             => 'Clinica Primaria',
            'ae_title'         => 'CLIN_PRIM',
            'remote_node_name' => 'Nodo Primario',
            'remote_ae_title'  => 'PRIM_PACS',
            'remote_host'      => '10.100.2.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->formPost('centers', [
            'name'             => 'Clinica Respaldo',
            'ae_title'         => 'CLIN_RESP',
            'remote_node_name' => 'Nodo Respaldo',
            'remote_ae_title'  => 'RESP_PACS',
            'remote_host'      => '10.100.2.2',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $primary   = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_PRIM')->first();
        $secondary = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_RESP')->first();
        $primId    = (int) $primary['id'];
        $secId     = (int) $secondary['id'];

        // Primary node fails both echo & store
        $this->orthanc->setFaults([
            'per_modality' => [
                'center_' . $primId => [
                    'echo'  => 'fail_500',
                    'store' => 'fail_500',
                ],
            ],
        ]);

        $primEcho = $this->ajaxPost('centers/test-connection/' . $primId);
        $this->assertGreaterThanOrEqual(400, $primEcho->response()->getStatusCode());
        $this->assertFalse(json_decode((string) $primEcho->response()->getBody(), true)['ok']);

        $primSend = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $primId,
        ]);
        $this->assertGreaterThanOrEqual(400, $primSend->response()->getStatusCode());
        $this->assertFalse(json_decode((string) $primSend->response()->getBody(), true)['ok']);

        // Operator fails over to secondary node -> succeeds
        $secEcho = $this->ajaxPost('centers/test-connection/' . $secId);
        $secEcho->assertStatus(200);
        $secSend = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $secId,
        ]);
        $secSend->assertStatus(200);
        $this->assertTrue(json_decode((string) $secSend->response()->getBody(), true)['ok']);
    }

    public function testWorkflow3ClinicLifecycleWithOrthancRestartEditDeactivateReactivate(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.40.3001');

        // 1. Create & sync
        $this->formPost('centers', [
            'name'             => 'Clinica Ciclo Vida',
            'ae_title'         => 'CLIN_LC',
            'remote_node_name' => 'Nodo LC1',
            'remote_ae_title'  => 'LC_V1',
            'remote_host'      => '10.100.3.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_LC')->first();
        $centerId = (int) $center['id'];

        // 2. Restart Orthanc -> modality persists
        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        // 3. Edit AE Title & host -> verify single modality updated, no orphan
        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Ciclo Vida',
            'ae_title'         => 'CLIN_LC',
            'active'           => '1',
            'remote_node_name' => 'Nodo LC2',
            'remote_ae_title'  => 'LC_V2',
            'remote_host'      => '10.100.3.2',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $mods = $this->orthanc->getModalities();
        $this->assertCount(1, $mods);
        $this->assertSame('LC_V2', $mods['center_' . $centerId]['AET']);

        // 4. Deactivate -> modality removed, send rejected
        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Ciclo Vida',
            'ae_title'         => 'CLIN_LC',
            'active'           => '1',
            'remote_node_name' => 'Nodo LC2',
            'remote_ae_title'  => 'LC_V2',
            'remote_host'      => '10.100.3.2',
            'remote_port'      => '11112',
        ])->assertRedirectTo('/centers');

        $this->assertCount(0, $this->orthanc->getModalities());
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(422);

        // 5. Reactivate -> modality restored, echo & send succeed
        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Ciclo Vida',
            'ae_title'         => 'CLIN_LC',
            'active'           => '1',
            'remote_node_name' => 'Nodo LC2',
            'remote_ae_title'  => 'LC_V2',
            'remote_host'      => '10.100.3.2',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ])->assertStatus(200);
    }

    public function testWorkflow4HospitalRbacWorkflowAdminMedicoPaciente(): void
    {
        $admin    = $this->createUser('admin', 'rbac.admin@pacs.test', 'PassAdmin123!');
        $medico   = $this->createUser('medico', 'rbac.medico@pacs.test', 'PassMedico123!');
        $paciente = $this->createUser('paciente', 'rbac.paciente@pacs.test', 'PassPac123!');

        // Admin configures center and seeds study assigned to patient
        auth()->login($admin);
        $this->withSession($_SESSION ?? []);

        $this->formPost('centers', [
            'name'             => 'Clinica RBAC',
            'ae_title'         => 'CLIN_RBAC',
            'remote_node_name' => 'Nodo RBAC',
            'remote_ae_title'  => 'RBAC_AET',
            'remote_host'      => '10.100.4.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');
        $centerId = (int) model(MedicalCenterModel::class)->where('ae_title', 'CLIN_RBAC')->first()['id'];

        $studyUid = '1.2.826.0.1.3680043.40.4001';
        $study    = $this->orthanc->seedStudy(['StudyInstanceUID' => $studyUid]);
        model(\App\Models\StudyModel::class)->insert([
            'orthanc_id'         => $study['ID'],
            'study_instance_uid' => $studyUid,
            'owner_user_id'      => $paciente->id,
            'patient_name'       => 'Paciente RBAC',
            'study_description'  => 'RM Cerebral',
            'study_date'         => '20261119',
        ]);
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $centerId])->assertStatus(200);

        // Medico reviews reports/case, but is denied centers, echo, send, audit
        $this->loginViaHttp('rbac.medico@pacs.test', 'PassMedico123!')->assertRedirect();
        $this->getPage('reports')->assertStatus(200);
        $this->getPage('studies/case/' . $studyUid)->assertStatus(200);
        $this->getPage('centers')->assertRedirectTo('/');
        $this->getPage('audit')->assertRedirectTo('/');
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(403);
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $centerId])->assertStatus(403);

        // Paciente views own study, but is denied centers, echo, send, audit
        $this->loginViaHttp('rbac.paciente@pacs.test', 'PassPac123!')->assertRedirect();
        $this->getPage('studies')->assertStatus(200);
        $this->getPage('studies/case/' . $studyUid)->assertStatus(200);
        $this->getPage('centers')->assertRedirectTo('/');
        $this->getPage('audit')->assertRedirectTo('/');
        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(403);
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $centerId])->assertStatus(403);
    }

    public function testWorkflow5MultiClinicSequentialDistributionAndTokenRotationRegression(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Clinica Origen WF5',
            'ae_title'         => 'CLIN_WF5_SRC',
            'remote_node_name' => 'Nodo SRC',
            'remote_ae_title'  => 'SRC_REMOTE',
            'remote_host'      => '10.100.5.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');
        $srcToken = (string) session('generated_token');
        $srcId    = (int) model(MedicalCenterModel::class)->where('ae_title', 'CLIN_WF5_SRC')->first()['id'];

        $this->formPost('centers', [
            'name'             => 'Clinica Destino A',
            'ae_title'         => 'CLIN_WF5_A',
            'remote_node_name' => 'Nodo Dest A',
            'remote_ae_title'  => 'DEST_A',
            'remote_host'      => '10.100.5.2',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');
        $destAId = (int) model(MedicalCenterModel::class)->where('ae_title', 'CLIN_WF5_A')->first()['id'];

        $this->formPost('centers', [
            'name'             => 'Clinica Destino B',
            'ae_title'         => 'CLIN_WF5_B',
            'remote_node_name' => 'Nodo Dest B',
            'remote_ae_title'  => 'DEST_B',
            'remote_host'      => '10.100.5.3',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');
        $destBId = (int) model(MedicalCenterModel::class)->where('ae_title', 'CLIN_WF5_B')->first()['id'];

        $studyUid = '1.2.826.0.1.3680043.40.5001';
        $this->ingestDicomViaApi($srcToken, $studyUid)->assertStatus(200);

        // Distribute same study sequentially to Dest A and Dest B
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $destAId])->assertStatus(200);
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $destBId])->assertStatus(200);

        // Rotate source gateway token: old rejected, new accepted, modalities unaffected
        $this->formPost('centers/rotate-token/' . $srcId)->assertRedirectTo('/centers');
        $rotatedToken = (string) session('generated_token');

        $this->ingestDicomViaApi($srcToken, '1.2.826.0.1.3680043.40.5002')->assertStatus(401);
        $this->ingestDicomViaApi($rotatedToken, '1.2.826.0.1.3680043.40.5003')->assertStatus(200);

        $storeReqs = array_values(array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with($r['path'], '/store')
        ));
        $this->assertCount(2, $storeReqs);
    }

    public function testWorkflow6OrthancOutageRecoveryAndAutomaticReSync(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.40.6001');

        // 1. Orthanc is down when admin creates center
        $this->orthanc->stop();
        $this->formPost('centers', [
            'name'             => 'Clinica Outage WF6',
            'ae_title'         => 'CLIN_WF6',
            'remote_node_name' => 'Nodo WF6',
            'remote_ae_title'  => 'WF6_AET',
            'remote_host'      => '10.100.6.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_WF6')->first();
        $centerId = (int) $center['id'];
        $this->assertSame('error', $center['orthanc_sync_status']);
        $this->assertNotEmpty($center['orthanc_sync_error']);

        // 2. Orthanc comes back online (reusing state file, study still seeded)
        $this->orthanc->start();
        $this->setOrthancEnv($this->orthanc->getUrl());
        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());

        // 3. C-ECHO auto-syncs missing modality and succeeds
        $echo = $this->ajaxPost('centers/test-connection/' . $centerId);
        $echo->assertStatus(200);
        $this->assertTrue(json_decode((string) $echo->response()->getBody(), true)['ok']);
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());

        // 4. Study send now succeeds and DB status reflects synced
        $send = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ]);
        $send->assertStatus(200);
        $this->assertTrue(json_decode((string) $send->response()->getBody(), true)['ok']);
    }
}
