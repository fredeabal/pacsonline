<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\ReportTemplateModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ReportTemplateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testTemplatesAreIsolatedPerDoctor(): void
    {
        $model = model(ReportTemplateModel::class);

        $model->insert([
            'user_id'    => 10,
            'name'       => 'Torax Dr. A',
            'modality'   => 'CR',
            'findings'   => 'Pulmones limpios.',
            'impression' => 'Normal.',
        ]);

        $model->insert([
            'user_id'    => 20,
            'name'       => 'Craneal Dr. B',
            'modality'   => 'CT',
            'findings'   => 'Sin lesiones.',
            'impression' => 'Normal.',
        ]);

        $doctorATemplates = $model->forUser(10);
        $doctorBTemplates = $model->forUser(20);

        $this->assertCount(1, $doctorATemplates);
        $this->assertSame('Torax Dr. A', $doctorATemplates[0]['name']);

        $this->assertCount(1, $doctorBTemplates);
        $this->assertSame('Craneal Dr. B', $doctorBTemplates[0]['name']);
    }

    public function testSeedsDefaultCatalogOncePerDoctor(): void
    {
        $model = model(ReportTemplateModel::class);

        $model->seedDefaultsForUser(50);
        $first = $model->forUser(50);
        $this->assertCount(count(ReportTemplateModel::defaultCatalog()), $first);

        // Llamar una segunda vez no debe duplicar plantillas
        $model->seedDefaultsForUser(50);
        $second = $model->forUser(50);
        $this->assertCount(count(ReportTemplateModel::defaultCatalog()), $second);

        // Otro médico sigue aislado
        $this->assertCount(0, $model->forUser(60));
    }
}
