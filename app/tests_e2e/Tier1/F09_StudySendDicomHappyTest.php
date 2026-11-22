<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F9: Send Study to Active Remote Node (C-STORE) happy paths.
 */
final class F09_StudySendDicomHappyTest extends E2ETestCase
{
    public function testAuthGroupsDefinesStudiesSendPermissionGrantedToAdmin(): void
    {
        $config = new \Config\AuthGroups();
        $this->assertArrayHasKey('studies.send', $config->permissions);

        $admin = $this->createUser('admin');
        $this->assertTrue($admin->can('studies.send'));
    }

    public function testAdminCanSendStudyFromDbAndOrthancToActiveRemoteCenter(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.10.9001');
        $seeded = $this->seedMedicalCenter([
            'name'             => 'Clinica Destino 1',
            'ae_title'         => 'CTR_DEST1',
            'active'           => 1,
            'remote_node_name' => 'Destino Uno',
            'remote_ae_title'  => 'DEST_UNO',
            'remote_host'      => '10.7.7.1',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $res = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => $centerId,
        ]);
        $res->assertStatus(200);

        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertTrue($json['ok']);
        $this->assertGreaterThan(0, (int) $json['instances_count']);
        $this->assertSame(0, (int) $json['failed_count']);

        $storeReqs = array_values(array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => $r['path'] === '/modalities/center_' . $centerId . '/store'
        ));
        $this->assertCount(1, $storeReqs);
        $sentBody = json_decode((string) $storeReqs[0]['body'], true);
        $this->assertSame([$study['ID']], $sentBody['Resources']);
        $this->assertTrue($sentBody['Synchronous']);
    }

    public function testSendStudyResolvesOrthancIdViaFallbackFindStudyByUid(): void
    {
        $this->loginAsRole('admin');
        // Seed only in Orthanc, NOT in SQLite studies table
        $studyUid = '1.2.826.0.1.3680043.10.9002';
        $study    = $this->orthanc->seedStudy(['StudyInstanceUID' => $studyUid]);

        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_DEST2',
            'active'           => 1,
            'remote_node_name' => 'Destino Dos',
            'remote_ae_title'  => 'DEST_DOS',
            'remote_host'      => '10.7.7.2',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res = $this->ajaxPost('studies/send/' . $studyUid, [
            'center_id' => (int) $seeded['center']['id'],
        ]);
        $res->assertStatus(200);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertTrue($json['ok']);

        $storeReqs = array_values(array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with($r['path'], '/store')
        ));
        $this->assertCount(1, $storeReqs);
        $sentBody = json_decode((string) $storeReqs[0]['body'], true);
        $this->assertSame([$study['ID']], $sentBody['Resources']);
    }

    public function testStudiesIndexRendersSendStudyModalAndActiveCenterOptions(): void
    {
        $this->loginAsRole('admin');
        $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.10.9003');
        $this->seedMedicalCenter([
            'name'             => 'Centro Activo Lista',
            'ae_title'         => 'CTR_ACT_LIST',
            'active'           => 1,
            'remote_node_name' => 'Nodo Lista Activo',
            'remote_ae_title'  => 'LIST_AET',
            'remote_host'      => '10.7.7.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res  = $this->getPage('studies');
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();

        $this->assertStringContainsString('id="sendStudyModal"', $body);
        $this->assertStringContainsString('LIST_AET', $body);
        $this->assertStringContainsString('studies/send/', $body);
    }

    public function testStudyCaseViewRendersSendStudyButtonAndModalForAdmin(): void
    {
        $this->loginAsRole('admin');
        $studyUid = '1.2.826.0.1.3680043.10.9004';
        $this->seedStudyInOrthancAndDb($studyUid);
        $this->seedMedicalCenter([
            'name'             => 'Centro Caso Activo',
            'ae_title'         => 'CTR_CASE_ACT',
            'active'           => 1,
            'remote_node_name' => 'Nodo Caso Activo',
            'remote_ae_title'  => 'CASE_AET',
            'remote_host'      => '10.7.7.4',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res  = $this->getPage('studies/case/' . $studyUid);
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();

        $this->assertStringContainsString('id="sendStudyModal"', $body);
        $this->assertStringContainsString('CASE_AET', $body);
        $this->assertStringContainsString('studies/send/', $body);
    }

    public function testSendStudyRecordsAuditLogWithStudyUidAndDestination(): void
    {
        $this->loginAsRole('admin');
        $studyUid = '1.2.826.0.1.3680043.10.9005';
        $this->seedStudyInOrthancAndDb($studyUid);
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_SEND_AUD',
            'active'           => 1,
            'remote_node_name' => 'Nodo Send Audit',
            'remote_ae_title'  => 'SEND_AUD_AET',
            'remote_host'      => '10.7.7.5',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $this->ajaxPost('studies/send/' . $studyUid, [
            'center_id' => (int) $seeded['center']['id'],
        ])->assertStatus(200);

        $logs = $this->getAuditLogsByAction('study.sent_dicom');
        $this->assertNotEmpty($logs);
        $this->assertSame($studyUid, $logs[0]['study_instance_uid']);
        $this->assertStringContainsString('SEND_AUD_AET', (string) $logs[0]['details']);
    }
}
