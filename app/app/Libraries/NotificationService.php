<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\AuditLogModel;
use CodeIgniter\Shield\Entities\User;
use Config\Services;

class NotificationService
{
    public function sendReportPublishedEmail(string $studyUid, ?array $study, array $report): bool
    {
        $settings = service('settings');
        if (! (bool) $settings->get('Smtp.enabled')) {
            return false;
        }

        if ($study === null || empty($study['owner_user_id'])) {
            return false;
        }

        $patient = auth()->getProvider()->findById((int) $study['owner_user_id']);
        if ($patient === null || empty($patient->email)) {
            return false;
        }

        $email = $this->configuredEmail();
        $email->setTo($patient->email);
        $email->setSubject('Tu informe médico está disponible — PACSLine');

        $portalUrl = base_url('studies');
        $body      = view('emails/report_published', [
            'patientName'      => $patient->full_name ?: $patient->username,
            'studyDescription' => $study['study_description'] ?? 'Estudio DICOM',
            'reportedAt'       => format_date($report['reported_at'] ?? date('Y-m-d')),
            'portalUrl'        => $portalUrl,
        ]);

        $email->setMessage($body);

        try {
            $pdfBytes = (new ReportPdfService())->renderBytes($studyUid, $study, $report);
            $email->attach($pdfBytes, 'attachment', 'informe-medico.pdf', 'application/pdf');
        } catch (\Throwable) {
            // Si falla el adjunto, se envía igualmente el aviso con enlace al portal
        }

        if ($email->send()) {
            AuditLogModel::record('notification.email_sent', $studyUid, null, 'Aviso enviado a ' . $patient->email);
            return true;
        }

        AuditLogModel::record('notification.email_failed', $studyUid, null, 'Fallo al enviar a ' . $patient->email);
        return false;
    }

    public function sendPatientWelcomeEmail(User $patient, string $tempPassword): bool
    {
        $settings = service('settings');
        if (! (bool) $settings->get('Smtp.enabled') || empty($patient->email)) {
            return false;
        }

        $email = $this->configuredEmail();
        $email->setTo($patient->email);
        $email->setSubject('Tus credenciales de acceso — PACSLine');
        $email->setMessage(view('emails/patient_welcome', [
            'patientName'  => $patient->full_name ?: $patient->username,
            'email'        => $patient->email,
            'tempPassword' => $tempPassword,
            'loginUrl'     => base_url('login'),
        ]));

        if ($email->send()) {
            AuditLogModel::record('notification.welcome_sent', null, null, 'Credenciales enviadas a ' . $patient->email);
            return true;
        }

        AuditLogModel::record('notification.welcome_failed', null, null, 'Fallo al enviar bienvenida a ' . $patient->email);
        return false;
    }

    public function sendStudyAssignedEmail(string $studyUid, array $study, int $doctorId): bool
    {
        $settings = service('settings');
        $host     = trim((string) ($settings->get('Smtp.host') ?? ''));
        if ($host === '') {
            return false;
        }

        $doctor = auth()->getProvider()->findById($doctorId);
        if ($doctor === null || empty($doctor->email)) {
            return false;
        }

        $isUrgent = ($study['priority'] ?? 'normal') === 'urgent';
        $subject  = ($isUrgent ? '[URGENTE] ' : '') . 'Nuevo estudio asignado — PACSLine';

        $email = $this->configuredEmail();
        $email->setTo($doctor->email);
        $email->setSubject($subject);

        $logoCid  = '';
        $logoPath = FCPATH . 'assets/images/logos/pacs_line_logo_pdf.png';
        if (is_file($logoPath)) {
            $email->attach($logoPath, 'inline');
            $cid = $email->setAttachmentCID($logoPath);
            if (is_string($cid) && $cid !== '') {
                $logoCid = $cid;
            }
        }

        $email->setMessage(view('emails/study_assigned', [
            'logoCid'          => $logoCid,
            'doctorName'       => $doctor->full_name ?: $doctor->username,
            'patientName'      => format_patient_name($study['patient_name'] ?? null, 'Paciente'),
            'studyDescription' => format_study_description($study['study_description'] ?? null, 'Estudio DICOM'),
            'isUrgent'         => $isUrgent,
            'clinicalNotes'    => $study['clinical_notes'] ?? '',
            'reportUrl'        => base_url('reports/edit/' . urlencode($studyUid)),
        ]));

        if ($email->send()) {
            AuditLogModel::record('notification.doctor_assigned_sent', $studyUid, null, 'Aviso enviado a ' . $doctor->email);
            return true;
        }

        AuditLogModel::record('notification.doctor_assigned_failed', $studyUid, null, 'Fallo al enviar aviso a ' . $doctor->email);
        return false;
    }

    public function sendPatientMagicLinkEmail(
        string $toEmail,
        string $patientName,
        string $studyDescription,
        string $magicUrl,
        string $expiresAt,
        string $studyUid
    ): bool {
        $settings = service('settings');
        $host     = trim((string) ($settings->get('Smtp.host') ?? ''));
        if ($host === '' || trim($toEmail) === '') {
            return false;
        }

        $email = $this->configuredEmail();
        $email->setTo($toEmail);
        $email->setSubject('Acceso seguro a tu estudio médico — PACSLine');
        $email->setMessage(view('emails/patient_magic_link', [
            'patientName'      => $patientName,
            'studyDescription' => $studyDescription,
            'magicUrl'         => $magicUrl,
            'expiresAt'        => format_date($expiresAt),
        ]));

        if ($email->send()) {
            AuditLogModel::record('patient_access.email_sent', $studyUid, null, 'Magic Link enviado a ' . $toEmail);
            return true;
        }

        AuditLogModel::record('patient_access.email_failed', $studyUid, null, 'Fallo al enviar Magic Link a ' . $toEmail);
        return false;
    }

    public function sendTestEmail(string $toEmail): array
    {
        $email = $this->configuredEmail();
        $email->setTo($toEmail);
        $email->setSubject('Prueba SMTP — PACSLine');
        $email->setMessage('<p>La configuración SMTP de <strong>PACSLine</strong> funciona correctamente.</p>');

        if ($email->send()) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => $email->printDebugger(['headers'])];
    }

    private function configuredEmail(): \CodeIgniter\Email\Email
    {
        $settings = service('settings');
        $config   = [
            'protocol'   => 'smtp',
            'SMTPHost'   => (string) ($settings->get('Smtp.host') ?? ''),
            'SMTPPort'   => (int) ($settings->get('Smtp.port') ?? 587),
            'SMTPUser'   => (string) ($settings->get('Smtp.user') ?? ''),
            'SMTPPass'   => (string) ($settings->get('Smtp.pass') ?? ''),
            'SMTPCrypto' => (string) ($settings->get('Smtp.crypto') ?? 'tls'),
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
            'newline'    => "\r\n",
        ];

        $email = Services::email();
        $email->initialize($config);

        $fromEmail = (string) ($settings->get('Smtp.fromEmail') ?? 'no-reply@pacs.local');
        $fromName  = (string) ($settings->get('Smtp.fromName') ?? 'PACSLine');
        $email->setFrom($fromEmail, $fromName);

        return $email;
    }
}
