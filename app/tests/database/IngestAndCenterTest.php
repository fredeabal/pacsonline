<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\MedicalCenterModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class IngestAndCenterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testCenterTokenLookupRespectsActiveFlag(): void
    {
        $model      = model(MedicalCenterModel::class);
        $plainToken = 'pacs_test_token_123456';

        $id = $model->insert([
            'name'           => 'Clinica Test',
            'ae_title'       => 'CLINICA_TEST',
            'api_token_hash' => hash('sha256', $plainToken),
            'active'         => 1,
        ], true);

        $found = $model->findActiveByToken($plainToken);
        $this->assertNotNull($found);
        $this->assertSame('CLINICA_TEST', $found['ae_title']);

        $model->update((int) $id, ['active' => 0]);
        $this->assertNull($model->findActiveByToken($plainToken));
    }

    public function testIngestEndpointRejectsMissingOrInvalidBearerToken(): void
    {
        $resMissing = $this->post('api/ingest/dicom');
        $resMissing->assertStatus(401);

        $resInvalid = $this->withHeaders([
            'Authorization' => 'Bearer pacs_invalid_token',
        ])->post('api/ingest/dicom');
        $resInvalid->assertStatus(401);
    }
}
