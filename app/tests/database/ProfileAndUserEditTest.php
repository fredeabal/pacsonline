<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\ReportPdfService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ProfileAndUserEditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testPacsUserModelSavesMedicalFieldsAndRendersInPdf(): void
    {
        $users = auth()->getProvider();
        $user  = new User([
            'username'       => 'dr_martinez',
            'email'          => 'dr.martinez@pacs.test',
            'password'       => 'SecurePass123!',
            'full_name'      => 'Dra. Laura Martinez',
            'specialty'      => 'Radiodiagnostico',
            'license_number' => 'COL-284512',
        ]);

        $users->save($user);
        $doctorId = (int) $users->getInsertID();

        $saved = $users->findById($doctorId);
        $this->assertNotNull($saved);
        $this->assertSame('Dra. Laura Martinez', $saved->full_name);
        $this->assertSame('Radiodiagnostico', $saved->specialty);
        $this->assertSame('COL-284512', $saved->license_number);

        $pdfBytes = (new ReportPdfService())->renderBytes('1.2.826.0.1.3680043.55.1', [
            'patient_name'      => 'Paciente Test',
            'study_description' => 'RM Craneo',
            'study_date'        => '20261117',
        ], [
            'doctor_user_id' => $doctorId,
            'findings'       => 'Sin hallazgos.',
            'impression'     => 'Normal.',
            'reported_at'    => '2026-11-17 01:00:00',
        ]);

        $this->assertStringStartsWith('%PDF-', $pdfBytes);
    }
}
