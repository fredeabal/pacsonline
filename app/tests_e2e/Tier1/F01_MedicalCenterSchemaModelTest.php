<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F1: Extend medical_centers schema & model persistence.
 */
final class F01_MedicalCenterSchemaModelTest extends E2ETestCase
{
    public function testMedicalCentersTableHasAllRemoteAndSyncColumns(): void
    {
        $db     = \Config\Database::connect('tests');
        $fields = $db->getFieldNames('medical_centers');

        $expected = [
            'id',
            'name',
            'ae_title',
            'api_token_hash',
            'active',
            'studies_count',
            'last_seen_at',
            'remote_node_name',
            'remote_ae_title',
            'remote_host',
            'remote_port',
            'remote_active',
            'orthanc_modality_id',
            'orthanc_synced_at',
            'orthanc_sync_status',
            'orthanc_sync_error',
        ];

        foreach ($expected as $column) {
            $this->assertContains($column, $fields, "Missing column {$column} on medical_centers");
        }
    }

    public function testMinimalInsertWithoutRemoteFieldsDefaultsCleanly(): void
    {
        $model = model(MedicalCenterModel::class);
        $id    = $model->insert([
            'name'           => 'Clinica Minimal',
            'ae_title'       => 'CLIN_MIN',
            'api_token_hash' => hash('sha256', 'pacs_min_token'),
            'active'         => 1,
        ], true);

        $row = $model->find((int) $id);
        $this->assertIsArray($row);
        $this->assertSame('Clinica Minimal', $row['name']);
        $this->assertNull($row['remote_node_name']);
        $this->assertNull($row['remote_ae_title']);
        $this->assertNull($row['remote_host']);
        $this->assertNull($row['remote_port']);
        $this->assertSame(0, (int) $row['remote_active']);
    }

    public function testRemoteDicomNodeFieldsPersistThroughModel(): void
    {
        $model = model(MedicalCenterModel::class);
        $id    = $model->insert([
            'name'                => 'Hospital Central',
            'ae_title'            => 'HOSP_CENTRAL',
            'api_token_hash'      => hash('sha256', 'pacs_hosp_token'),
            'active'              => 1,
            'remote_node_name'    => 'PACS Central Remoto',
            'remote_ae_title'     => 'PACS_REMOTO',
            'remote_host'         => '10.10.20.30',
            'remote_port'         => 4242,
            'remote_active'       => 1,
            'orthanc_modality_id' => 'center_1',
            'orthanc_sync_status' => 'synced',
        ], true);

        $row = $model->find((int) $id);
        $this->assertIsArray($row);
        $this->assertSame('PACS Central Remoto', $row['remote_node_name']);
        $this->assertSame('PACS_REMOTO', $row['remote_ae_title']);
        $this->assertSame('10.10.20.30', $row['remote_host']);
        $this->assertSame(4242, (int) $row['remote_port']);
        $this->assertSame(1, (int) $row['remote_active']);
        $this->assertSame('center_1', $row['orthanc_modality_id']);
        $this->assertSame('synced', $row['orthanc_sync_status']);
    }

    public function testCreateAndReloadCenterPersistsRemoteParametersAcrossHttpRequests(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Persistente',
            'ae_title'         => 'CLIN_PERSIST',
            'remote_node_name' => 'Nodo Persistente',
            'remote_ae_title'  => 'NODO_PERSIST',
            'remote_host'      => '192.168.10.50',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ]);
        $res->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_PERSIST')->first();
        $this->assertIsArray($center);

        $editPage = $this->getPage('centers/edit/' . (int) $center['id']);
        $editPage->assertStatus(200);
        $body = (string) $editPage->response()->getBody();
        $this->assertStringContainsString('Nodo Persistente', $body);
        $this->assertStringContainsString('NODO_PERSIST', $body);
        $this->assertStringContainsString('192.168.10.50', $body);
        $this->assertStringContainsString('11112', $body);
    }

    public function testDeterministicModalityIdFormatMatchesCenterId(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Clinica Modality ID',
            'ae_title'         => 'CLIN_MODID',
            'remote_node_name' => 'Nodo ID',
            'remote_ae_title'  => 'NODO_ID',
            'remote_host'      => '10.0.0.25',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_MODID')->first();
        $this->assertIsArray($center);
        $expectedModalityId = 'center_' . (int) $center['id'];
        $this->assertSame($expectedModalityId, $center['orthanc_modality_id']);
        $this->assertArrayHasKey($expectedModalityId, $this->orthanc->getModalities());
    }

    public function testSyncTimestampAndErrorFieldsCanBePersistedAndCleared(): void
    {
        $model = model(MedicalCenterModel::class);
        $id    = $model->insert([
            'name'                => 'Clinica Sync Meta',
            'ae_title'            => 'CLIN_META',
            'api_token_hash'      => hash('sha256', 'pacs_meta_token'),
            'active'              => 1,
            'remote_node_name'    => 'Nodo Meta',
            'remote_ae_title'     => 'NODO_META',
            'remote_host'         => '10.1.2.3',
            'remote_port'         => 104,
            'remote_active'       => 1,
            'orthanc_sync_status' => 'error',
            'orthanc_sync_error'  => 'Timeout conectando con Orthanc',
        ], true);

        $row = $model->find((int) $id);
        $this->assertSame('error', $row['orthanc_sync_status']);
        $this->assertSame('Timeout conectando con Orthanc', $row['orthanc_sync_error']);

        $now = date('Y-m-d H:i:s');
        $model->update((int) $id, [
            'orthanc_synced_at'   => $now,
            'orthanc_sync_status' => 'synced',
            'orthanc_sync_error'  => null,
        ]);

        $updated = $model->find((int) $id);
        $this->assertSame('synced', $updated['orthanc_sync_status']);
        $this->assertSame($now, $updated['orthanc_synced_at']);
        $this->assertNull($updated['orthanc_sync_error']);
    }
}
