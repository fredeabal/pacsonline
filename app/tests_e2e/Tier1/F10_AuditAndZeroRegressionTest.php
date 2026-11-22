<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F10: Audit trail & zero-regression (ingest, token rotation, audit UI).
 */
final class F10_AuditAndZeroRegressionTest extends E2ETestCase
{
    public function testCenterCreationAndUpdateRecordAuditEvents(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Centro Audit Create',
            'ae_title'         => 'CTR_AUD_C',
            'remote_node_name' => 'Nodo Aud',
            'remote_ae_title'  => 'AUD_AET',
            'remote_host'      => '10.8.8.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $createdLogs = $this->getAuditLogsByAction('center.created');
        $this->assertNotEmpty($createdLogs);

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CTR_AUD_C')->first();
        $this->formPost('centers/update/' . (int) $center['id'], [
            'name'             => 'Centro Audit Updated',
            'ae_title'         => 'CTR_AUD_C',
            'active'           => '1',
            'remote_node_name' => 'Nodo Aud V2',
            'remote_ae_title'  => 'AUD2_AET',
            'remote_host'      => '10.8.8.2',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $allLogs = model(\App\Models\AuditLogModel::class)->findAll();
        $actions = array_column($allLogs, 'action');
        $otherCenterActions = array_filter($actions, static fn (string $a): bool => str_starts_with($a, 'center.') && $a !== 'center.created');
        $this->assertNotEmpty($otherCenterActions);
    }

    public function testBearerIngestDicomStillWorksWithExtendedCenterSchema(): void
    {
        $seeded = $this->seedMedicalCenter([
            'name'             => 'Centro Ingest Regresion',
            'ae_title'         => 'CTR_ING_REG',
            'active'           => 1,
            'remote_node_name' => 'Nodo Ingest',
            'remote_ae_title'  => 'ING_AET',
            'remote_host'      => '10.8.8.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $studyUid = '1.2.826.0.1.3680043.10.10001';
        $res      = $this->ingestDicomViaApi($seeded['plain_token'], $studyUid, 'Pedro^Ingest', 'PAC-ING-99');
        $res->assertStatus(200);

        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertTrue($json['ok']);
        $this->assertSame('CTR_ING_REG', $json['center']);
        $this->assertSame($studyUid, $json['study_instance_uid']);

        $updatedCenter = model(MedicalCenterModel::class)->find((int) $seeded['center']['id']);
        $this->assertSame(1, (int) $updatedCenter['studies_count']);
        $this->assertNotEmpty($this->getAuditLogsByAction('ingest.dicom_received'));
    }

    public function testTokenRotationPreservesRemoteNodeConfigAndUpdatesGatewayAuth(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_ROT_REG',
            'active'           => 1,
            'remote_node_name' => 'Nodo Rot Reg',
            'remote_ae_title'  => 'ROT_AET',
            'remote_host'      => '10.8.8.4',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->formPost('centers/rotate-token/' . $centerId)->assertRedirectTo('/centers');
        $newToken = session('generated_token');
        $this->assertIsString($newToken);

        $center = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame('ROT_AET', $center['remote_ae_title']);
        $this->assertSame('10.8.8.4', $center['remote_host']);

        $this->ingestDicomViaApi($seeded['plain_token'], '1.2.826.0.1.3680043.10.10002')->assertStatus(401);
        $this->ingestDicomViaApi($newToken, '1.2.826.0.1.3680043.10.10003')->assertStatus(200);
    }

    public function testAuditIndexDisplaysEchoAndSendEventsToAdmin(): void
    {
        $this->loginAsRole('admin');
        $studyUid = '1.2.826.0.1.3680043.10.10004';
        $this->seedStudyInOrthancAndDb($studyUid);
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_AUD_VIEW',
            'active'           => 1,
            'remote_node_name' => 'Nodo Aud View',
            'remote_ae_title'  => 'AUDVIEW_AET',
            'remote_host'      => '10.8.8.5',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);
        $this->ajaxPost('studies/send/' . $studyUid, ['center_id' => $centerId])->assertStatus(200);

        $auditRes = $this->getPage('audit');
        $auditRes->assertStatus(200);
        $body = (string) $auditRes->response()->getBody();
        $this->assertStringContainsString('center.dicom_echo', $body);
        $this->assertStringContainsString('study.sent_dicom', $body);
    }

    public function testStudiesIndexPreservesUploadAndSearchFeatures(): void
    {
        $this->loginAsRole('admin');
        $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.10.10005', 'Carlos Mendoza', 'PAC-777');

        $res = $this->getPage('studies', ['q' => 'Mendoza']);
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('Carlos Mendoza', $body);
        $this->assertStringContainsString('studies/upload', $body);
    }
}
