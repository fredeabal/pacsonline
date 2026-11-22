<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\AuditLogModel;
use Config\AuthGroups;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F10: Audit & zero-regression boundary tests.
 */
final class F10_AuditAndRegressionBoundaryTest extends E2ETestCase
{
    public function testIngestRejectsMissingInvalidAndInactiveBearerTokens(): void
    {
        $this->resetFeatureRequestState();
        $this->post('api/ingest/dicom')->assertStatus(401);

        $this->resetFeatureRequestState();
        $this->withHeaders(['Authorization' => 'Bearer pacs_nonexistent'])->post('api/ingest/dicom')->assertStatus(401);

        $inactive = $this->seedMedicalCenter(['ae_title' => 'CTR_ING_OFF', 'active' => 0]);
        $this->ingestDicomViaApi($inactive['plain_token'], '1.2.826.0.1.3680043.20.1001')->assertStatus(401);
    }

    public function testIngestRejectsEmptyPayloadAndOrthancRejectedDicom(): void
    {
        $seeded = $this->seedMedicalCenter(['ae_title' => 'CTR_ING_BAD', 'active' => 1]);

        $this->resetFeatureRequestState();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $seeded['plain_token'],
            'Content-Type'  => 'application/dicom',
        ])->withBody('')->post('api/ingest/dicom')->assertStatus(400);

        $this->resetFeatureRequestState();
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $seeded['plain_token'],
            'Content-Type'  => 'application/dicom',
        ])->withBody('INVALID_DICOM_PAYLOAD')->post('api/ingest/dicom')->assertStatus(422);
    }

    public function testMedicoAndPacienteAreDeniedAuditLogPage(): void
    {
        $this->loginAsRole('medico');
        $this->getPage('audit')->assertRedirectTo('/');
        $this->assertSame('Sin permiso.', session('error'));

        $this->loginAsRole('paciente');
        $this->getPage('audit')->assertRedirectTo('/');
        $this->assertSame('Sin permiso.', session('error'));
    }

    public function testAuditLogTruncatesLongUserAgentDuringEchoAndSend(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_UA_TRUNC',
            'active'           => 1,
            'remote_node_name' => 'Nodo UA',
            'remote_ae_title'  => 'UA_AET',
            'remote_host'      => '10.5.5.10',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $longUa = str_repeat('PacsClient/1.0 ', 25);
        $res    = $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id'], [], true, [
            'User-Agent' => $longUa,
        ]);
        $res->assertStatus(200);

        $logs = model(AuditLogModel::class)->where('action', 'center.dicom_echo')->findAll();
        $this->assertNotEmpty($logs);
        $this->assertLessThanOrEqual(250, strlen((string) $logs[0]['user_agent']));
    }

    public function testAuthGroupsMatrixPreservesLeastPrivilegeForMedicoAndPaciente(): void
    {
        $config = new AuthGroups();
        $this->assertCount(3, $config->groups);

        $medico   = $this->createUser('medico');
        $paciente = $this->createUser('paciente');

        $this->assertFalse($medico->can('settings.manage'));
        $this->assertFalse($medico->can('studies.upload'));
        $this->assertFalse($medico->can('studies.send'));

        $this->assertFalse($paciente->can('settings.manage'));
        $this->assertFalse($paciente->can('studies.view_all'));
        $this->assertFalse($paciente->can('studies.send'));
    }
}
