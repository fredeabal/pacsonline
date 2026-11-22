<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\ReportPdfService;
use App\Models\StudyShareModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class ReportPdfServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testGeneratesQrDataUriAsPng(): void
    {
        $uri = ReportPdfService::generateQrDataUri('https://pacs.example.com/share/abc');
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function testRendersValidPdfWithAndWithoutActiveShareQr(): void
    {
        $uid     = '1.2.826.0.1.3680043.88.1';
        $service = new ReportPdfService();

        $study = [
            'patient_name'      => 'Paciente Prueba',
            'study_description' => 'TC Torax',
            'study_date'        => '20261117',
        ];
        $report = [
            'findings'    => 'Sin hallazgos agudos.',
            'impression'  => 'Normal.',
            'reported_at' => '2026-11-17 00:50:00',
        ];

        $pdfWithoutQr = $service->renderBytes($uid, $study, $report);
        $this->assertStringStartsWith('%PDF-', $pdfWithoutQr);

        model(StudyShareModel::class)->insert([
            'study_instance_uid' => $uid,
            'created_by_user_id' => 1,
            'token'              => 'tok_qr_pdf_123',
            'expires_at'         => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $pdfWithQr = $service->renderBytes($uid, $study, $report);
        $this->assertStringStartsWith('%PDF-', $pdfWithQr);
        $this->assertGreaterThan(strlen($pdfWithoutQr), strlen($pdfWithQr));
    }
}
