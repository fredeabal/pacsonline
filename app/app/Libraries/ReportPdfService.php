<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\PatientAccessLinkModel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportPdfService
{
    public static function generateQrDataUri(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'scale'           => 5,
            'imageBase64'     => true,
        ]);

        return (new QRCode($options))->render($url);
    }

    public function renderBytes(string $studyUid, ?array $study, array $report): string
    {
        if (
            $study === null
            || empty($study['patient_name'])
            || empty($study['study_date'])
            || empty($study['study_description'])
        ) {
            $orthancStudy = (new OrthancClient())->findStudyByUid($studyUid);
            if ($orthancStudy !== null) {
                $tags    = $orthancStudy['MainDicomTags'] ?? [];
                $patient = $orthancStudy['PatientMainDicomTags'] ?? [];
                $study   = $study ?? [];
                $study['patient_name']      = ! empty($study['patient_name']) ? $study['patient_name'] : ($patient['PatientName'] ?? null);
                $study['study_date']        = ! empty($study['study_date']) ? $study['study_date'] : ($tags['StudyDate'] ?? null);
                $study['study_description'] = ! empty($study['study_description']) ? $study['study_description'] : ($tags['StudyDescription'] ?? null);
            }
        }

        $doctor = ! empty($report['doctor_user_id'])
            ? auth()->getProvider()->findById((int) $report['doctor_user_id'])
            : null;

        $activeLink = model(PatientAccessLinkModel::class)
            ->where('study_instance_uid', $studyUid)
            ->where('revoked_at', null)
            ->where('locked_at', null)
            ->orderBy('created_at', 'DESC')
            ->first();

        $qrDataUri = null;
        if ($activeLink !== null && (empty($activeLink['expires_at']) || strtotime((string) $activeLink['expires_at']) >= time())) {
            $qrDataUri = self::generateQrDataUri(base_url('paciente/' . $activeLink['token']));
        }

        $signatureDataUri = null;
        if ($doctor !== null && ! empty($doctor->signature_file)) {
            $sigPath = WRITEPATH . 'uploads/signatures/' . basename((string) $doctor->signature_file);
            if (is_file($sigPath)) {
                $mime = mime_content_type($sigPath) ?: 'image/png';
                $raw  = file_get_contents($sigPath);
                if ($raw !== false) {
                    $signatureDataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }
            }
        }

        $letterheadDataUri = null;
        $letterheadPath    = FCPATH . 'assets/images/carema_letterhead.png';
        if (is_file($letterheadPath)) {
            $rawLetterhead = file_get_contents($letterheadPath);
            if ($rawLetterhead !== false) {
                $letterheadDataUri = 'data:image/png;base64,' . base64_encode($rawLetterhead);
            }
        }

        $html = view('reports/pdf', [
            'studyUid'          => $studyUid,
            'study'             => $study,
            'report'            => $report,
            'doctor'            => $doctor,
            'qrDataUri'         => $qrDataUri,
            'signatureDataUri'  => $signatureDataUri,
            'letterheadDataUri' => $letterheadDataUri,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
