<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F8: DICOM Connection Test (C-ECHO) happy paths.
 */
final class F08_DicomEchoHappyTest extends E2ETestCase
{
    public function testTestConnectionReturnsSuccessJsonWithLatencyMs(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica Echo OK',
            'ae_title'         => 'CLIN_ECHO1',
            'remote_node_name' => 'Nodo Echo 1',
            'remote_ae_title'  => 'ECHO1_AET',
            'remote_host'      => '10.6.6.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center = model(\App\Models\MedicalCenterModel::class)->where('ae_title', 'CLIN_ECHO1')->first();
        $res    = $this->ajaxPost('centers/test-connection/' . (int) $center['id']);
        $res->assertStatus(200);

        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertTrue($json['ok']);
        $this->assertNotEmpty($json['message']);
        $this->assertArrayHasKey('latency_ms', $json);
        $this->assertIsInt($json['latency_ms']);
    }

    public function testTestConnectionAutoSyncsMissingModalityBeforeEcho(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'name'                => 'Clinica AutoSync Echo',
            'ae_title'            => 'CLIN_ASE',
            'active'              => 1,
            'remote_node_name'    => 'Nodo ASE',
            'remote_ae_title'     => 'ASE_AET',
            'remote_host'         => '10.6.6.2',
            'remote_port'         => 4242,
            'remote_active'       => 1,
            'orthanc_sync_status' => null,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());

        $res = $this->ajaxPost('centers/test-connection/' . $centerId);
        $res->assertStatus(200);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertTrue($json['ok']);
        $this->assertArrayHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }

    public function testTestConnectionSendsValidJsonObjectBodyToOrthanc(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CLIN_EJSON',
            'active'           => 1,
            'remote_node_name' => 'Nodo JSON',
            'remote_ae_title'  => 'EJSON_AET',
            'remote_host'      => '10.6.6.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);

        $echoReqs = array_values(array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => $r['path'] === '/modalities/center_' . $centerId . '/echo'
        ));
        $this->assertCount(1, $echoReqs);
        $body = trim((string) $echoReqs[0]['body']);
        $this->assertStringStartsWith('{', $body);
    }

    public function testTestConnectionRecordsAuditLogEvent(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CLIN_EAUDIT',
            'active'           => 1,
            'remote_node_name' => 'Nodo Audit',
            'remote_ae_title'  => 'EAUDIT_AET',
            'remote_host'      => '10.6.6.4',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(200);

        $logs = $this->getAuditLogsByAction('center.dicom_echo');
        $this->assertNotEmpty($logs);
        $this->assertStringContainsString('EAUDIT_AET', (string) $logs[0]['details']);
    }

    public function testCentersViewsIncludeJavascriptOrFormTriggerForTestConnection(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CLIN_EUI',
            'active'           => 1,
            'remote_node_name' => 'Nodo EUI',
            'remote_ae_title'  => 'EUI_AET',
            'remote_host'      => '10.6.6.5',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $indexBody = (string) $this->getPage('centers')->response()->getBody();
        $editBody  = (string) $this->getPage('centers/edit/' . $centerId)->response()->getBody();

        $this->assertStringContainsString('centers/test-connection/', $indexBody);
        $this->assertStringContainsString('centers/test-connection/' . $centerId, $editBody);
    }
}
