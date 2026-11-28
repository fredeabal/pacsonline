<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\NotificationService;
use App\Libraries\OrthancClient;
use App\Libraries\ReportPdfService;
use App\Models\AuditLogModel;
use App\Models\MedicalReportModel;
use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Acceso seguro para pacientes mediante Magic Link + Documento + F. Nacimiento//
/////////////////////////////////////////////////////////////////////////////////
class PatientAccessController extends BaseController
{
    public const SESSION_TTL_SECONDS = 1800; // 30 minutos de inactividad

    /////////////////////////////////////////////////////////////////////////////
    // Lista los enlaces de acceso generados para el paciente de un estudio.   //
    /////////////////////////////////////////////////////////////////////////////
    public function manage(string $studyUid): string|RedirectResponse
    {
        if (! $this->canManagePatientAccess($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para gestionar accesos de pacientes.');
        }

        $study = $this->resolveStudyInfo($studyUid);
        $links = model(PatientAccessLinkModel::class)
            ->where('study_instance_uid', $studyUid)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        foreach ($links as &$link) {
            $link['_qr'] = ReportPdfService::generateQrDataUri(base_url('paciente/' . $link['token']));
        }
        unset($link);

        return view('patient_access/manage', [
            'title'    => 'Acceso Paciente | PACSLine',
            'studyUid' => $studyUid,
            'study'    => $study,
            'links'    => $links,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Formulario para emitir un nuevo Magic Link de acceso para el paciente.  //
    /////////////////////////////////////////////////////////////////////////////
    public function createForm(string $studyUid): string|RedirectResponse
    {
        if (! $this->canManagePatientAccess($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para generar accesos de pacientes.');
        }

        $study      = $this->resolveStudyInfo($studyUid);
        $orthancRef = $study['_orthanc'] ?? null;
        $pTags      = is_array($orthancRef['PatientMainDicomTags'] ?? null) ? $orthancRef['PatientMainDicomTags'] : [];

        $dicomPatientId = trim((string) ($pTags['PatientID'] ?? ''));
        $dicomBirthDate = PatientAccessLinkModel::normalizeBirthDate((string) ($pTags['PatientBirthDate'] ?? '')) ?? '';

        return view('patient_access/create', [
            'title'          => 'Nuevo Acceso Paciente | PACSLine',
            'studyUid'       => $studyUid,
            'study'          => $study,
            'dicomPatientId' => $dicomPatientId,
            'dicomBirthDate' => $dicomBirthDate,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Crea un Magic Link con hashes de Documento + Fecha de Nacimiento.       //
    /////////////////////////////////////////////////////////////////////////////
    public function create(): RedirectResponse
    {
        if (! $this->canManagePatientAccess()) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para generar accesos de pacientes.');
        }

        $rules = [
            'study_instance_uid' => [
                'label' => 'Estudio',
                'rules' => 'required|max_length[128]',
            ],
            'patient_id' => [
                'label' => 'Número de identificación',
                'rules' => 'required|max_length[64]',
            ],
            'patient_birth_date' => [
                'label' => 'Fecha de nacimiento',
                'rules' => 'required|max_length[20]',
            ],
            'expires_days' => [
                'label' => 'Caducidad',
                'rules' => 'required|in_list[7,15,30,60]',
            ],
            'patient_email' => [
                'label' => 'Correo electrónico del paciente',
                'rules' => 'permit_empty|valid_email|max_length[190]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data       = $this->validator->getValidated();
        $studyUid   = (string) $data['study_instance_uid'];
        if (! $this->canManagePatientAccess($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para generar accesos de pacientes para este estudio.');
        }
        $normId     = PatientAccessLinkModel::normalizePatientId((string) $data['patient_id']);
        $normBirth  = PatientAccessLinkModel::normalizeBirthDate((string) $data['patient_birth_date']);

        if (strlen($normId) < 3) {
            return redirect()->back()->withInput()->with('error', 'El número de identificación debe tener al menos 3 caracteres alfanuméricos.');
        }

        if ($normBirth === null) {
            return redirect()->back()->withInput()->with('error', 'La fecha de nacimiento no tiene un formato válido.');
        }

        $days          = (int) $data['expires_days'];
        $expiresAt     = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $token         = bin2hex(random_bytes(24));
        $patientEmail  = trim((string) ($data['patient_email'] ?? ''));
        $allowDownload = $this->request->getPost('allow_download') ? 1 : 0;

        $linkId = model(PatientAccessLinkModel::class)->insert([
            'study_instance_uid'      => $studyUid,
            'created_by_user_id'      => auth()->id(),
            'token'                   => $token,
            'patient_id_hash'         => password_hash($normId, PASSWORD_DEFAULT),
            'patient_birth_date_hash' => password_hash($normBirth, PASSWORD_DEFAULT),
            'patient_email'           => $patientEmail !== '' ? $patientEmail : null,
            'allow_download'          => $allowDownload,
            'expires_at'              => $expiresAt,
        ], true);

        AuditLogModel::record(
            'patient_access.created',
            $studyUid,
            (int) $linkId,
            'Magic Link de paciente creado (caduca: ' . $expiresAt . ')',
            $this->request
        );

        $message = 'Enlace de acceso para el paciente generado.';
        if ($patientEmail !== '') {
            $study       = $this->resolveStudyInfo($studyUid);
            $patientName = format_patient_name($study['patient_name'] ?? null, 'Paciente');
            $studyDesc   = format_study_description($study['study_description'] ?? null, 'Estudio DICOM');
            $magicUrl    = base_url('paciente/' . $token);

            $sent = (new NotificationService())->sendPatientMagicLinkEmail(
                $patientEmail,
                $patientName,
                $studyDesc,
                $magicUrl,
                $expiresAt,
                $studyUid
            );

            $message = $sent
                ? 'Enlace de paciente generado y enviado a ' . $patientEmail . '.'
                : 'Enlace generado, pero no se pudo enviar el correo (verifica la configuración SMTP o compártelo manualmente).';
        }

        return redirect()->to('/studies/patient-access/' . urlencode($studyUid))
            ->with('message', $message);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario para editar un enlace de paciente existente.      //
    /////////////////////////////////////////////////////////////////////////////
    public function editForm(int $linkId): string|RedirectResponse
    {
        if (! $this->canManagePatientAccess()) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar accesos de pacientes.');
        }

        $link = model(PatientAccessLinkModel::class)->find($linkId);
        if ($link === null) {
            return redirect()->to('/studies')->with('error', 'Enlace no encontrado.');
        }

        if (! $this->canManagePatientAccess((string) $link['study_instance_uid'])) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar enlaces de otro centro.');
        }

        if (! empty($link['revoked_at'])) {
            return redirect()->to('/studies/patient-access/' . urlencode((string) $link['study_instance_uid']))
                ->with('error', 'No se puede editar un enlace revocado.');
        }

        $studyUid = (string) $link['study_instance_uid'];
        $study    = $this->resolveStudyInfo($studyUid);

        return view('patient_access/edit', [
            'title'    => 'Editar Acceso Paciente | PACSLine',
            'studyUid' => $studyUid,
            'study'    => $study,
            'link'     => $link,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza caducidad, email, descarga ZIP y datos opcionales del enlace. //
    /////////////////////////////////////////////////////////////////////////////
    public function update(int $linkId): RedirectResponse
    {
        if (! $this->canManagePatientAccess()) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar accesos de pacientes.');
        }

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->find($linkId);
        if ($link === null) {
            return redirect()->to('/studies')->with('error', 'Enlace no encontrado.');
        }

        if (! $this->canManagePatientAccess((string) $link['study_instance_uid'])) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar enlaces de otro centro.');
        }

        if (! empty($link['revoked_at'])) {
            return redirect()->to('/studies/patient-access/' . urlencode((string) $link['study_instance_uid']))
                ->with('error', 'No se puede editar un enlace revocado.');
        }

        $rules = [
            'expires_date' => [
                'label' => 'Fecha de caducidad',
                'rules' => 'required|regex_match[/^(?:\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})$/]',
            ],
            'patient_email' => [
                'label' => 'Correo electrónico del paciente',
                'rules' => 'permit_empty|valid_email|max_length[190]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data        = $this->validator->getValidated();
        $expiresIso  = normalize_date_filter((string) $data['expires_date']);
        if ($expiresIso === '') {
            return redirect()->back()->withInput()->with('error', 'La fecha de caducidad no tiene un formato válido.');
        }
        [$ey, $em, $ed] = array_map('intval', explode('-', $expiresIso));
        if (! checkdate($em, $ed, $ey)) {
            return redirect()->back()->withInput()->with('error', 'La fecha de caducidad no es válida.');
        }

        $patientEmail = trim((string) ($data['patient_email'] ?? ''));
        $update       = [
            'expires_at'     => $expiresIso . ' 23:59:59',
            'patient_email'  => $patientEmail !== '' ? $patientEmail : null,
            'allow_download' => $this->request->getPost('allow_download') ? 1 : 0,
        ];

        if (! empty($link['locked_at']) && $this->request->getPost('unlock_link')) {
            $update['locked_at']       = null;
            $update['failed_attempts'] = 0;
        }

        $model->update($linkId, $update);

        AuditLogModel::record(
            'patient_access.updated',
            (string) $link['study_instance_uid'],
            $linkId,
            'Enlace de paciente actualizado (caduca: ' . $update['expires_at'] . ')',
            $this->request
        );

        return redirect()->to('/studies/patient-access/' . urlencode((string) $link['study_instance_uid']))
            ->with('message', 'Enlace de paciente actualizado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Envía o reenvía el Magic Link por correo electrónico al paciente.       //
    /////////////////////////////////////////////////////////////////////////////
    public function resendEmail(int $linkId): RedirectResponse
    {
        if (! $this->canManagePatientAccess()) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para enviar correos.');
        }

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->find($linkId);

        if ($link === null || ! $model->isStillActive($linkId)) {
            return redirect()->back()->with('error', 'El enlace no existe o ya no está activo.');
        }

        if (! $this->canManagePatientAccess((string) $link['study_instance_uid'])) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para reenviar enlaces de otro centro.');
        }

        $email = trim((string) ($this->request->getPost('patient_email') ?? $link['patient_email'] ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Introduce un correo electrónico válido.');
        }

        if ($email !== ($link['patient_email'] ?? '')) {
            $model->update($linkId, ['patient_email' => $email]);
        }

        $studyUid    = (string) $link['study_instance_uid'];
        $study       = $this->resolveStudyInfo($studyUid);
        $patientName = format_patient_name($study['patient_name'] ?? null, 'Paciente');
        $studyDesc   = format_study_description($study['study_description'] ?? null, 'Estudio DICOM');
        $magicUrl    = base_url('paciente/' . $link['token']);

        $sent = (new NotificationService())->sendPatientMagicLinkEmail(
            $email,
            $patientName,
            $studyDesc,
            $magicUrl,
            (string) $link['expires_at'],
            $studyUid
        );

        if ($sent) {
            return redirect()->back()->with('message', 'Correo enviado correctamente a ' . $email . '.');
        }

        return redirect()->back()->with('error', 'No se pudo enviar el correo. Verifica la configuración SMTP en Ajustes.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Revoca manualmente un enlace de acceso de paciente antes de caducar.    //
    /////////////////////////////////////////////////////////////////////////////
    public function revoke(int $linkId): RedirectResponse
    {
        if (! auth()->user()?->can('studies.delete')) {
            return redirect()->to('/studies')->with('error', 'Solo un supervisor puede revocar accesos.');
        }

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->find($linkId);

        if ($link === null) {
            return redirect()->back()->with('error', 'Enlace no encontrado.');
        }

        $model->update($linkId, ['revoked_at' => date('Y-m-d H:i:s')]);
        AuditLogModel::record(
            'patient_access.revoked',
            (string) $link['study_instance_uid'],
            $linkId,
            'Enlace de paciente revocado',
            $this->request
        );

        return redirect()->back()->with('message', 'Acceso del paciente revocado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina un enlace de paciente revocado (solo supervisores).             //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(int $linkId): RedirectResponse
    {
        if (! auth()->user()?->can('studies.delete')) {
            return redirect()->to('/studies')->with('error', 'Solo los supervisores pueden eliminar enlaces.');
        }

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->find($linkId);

        if ($link === null) {
            return redirect()->back()->with('error', 'Enlace no encontrado.');
        }

        if (empty($link['revoked_at'])) {
            return redirect()->back()->with('error', 'Solo se pueden eliminar enlaces revocados.');
        }

        $model->delete($linkId);
        AuditLogModel::record(
            'patient_access.deleted',
            (string) $link['study_instance_uid'],
            $linkId,
            'Enlace de paciente revocado eliminado por administrador',
            $this->request
        );

        return redirect()->back()->with('message', 'Enlace eliminado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Vista pública del Magic Link: verificación de identidad o estudio.      //
    /////////////////////////////////////////////////////////////////////////////
    public function publicView(string $token): string
    {
        $this->applyPrivacyHeaders();

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->findActiveByToken($token);

        if ($link === null) {
            return view('patient_access/expired');
        }

        if (! $this->hasValidSessionAccess($link)) {
            return view('patient_access/verify', [
                'token' => $token,
                'link'  => $link,
            ]);
        }

        // Refresca la ventana de inactividad de la sesión del paciente
        session()->set('patient_access', [
            'link_id'     => (int) $link['id'],
            'study_uid'   => (string) $link['study_instance_uid'],
            'unlocked_at' => time(),
        ]);

        $model->update((int) $link['id'], [
            'views_count'      => (int) $link['views_count'] + 1,
            'last_accessed_at' => date('Y-m-d H:i:s'),
        ]);

        AuditLogModel::record(
            'patient_access.accessed',
            (string) $link['study_instance_uid'],
            (int) $link['id'],
            'Acceso verificado del paciente al estudio',
            $this->request
        );

        $studyUid  = (string) $link['study_instance_uid'];
        $study     = $this->resolveStudyInfo($studyUid);
        $report    = model(MedicalReportModel::class)->findByStudyUid($studyUid);
        $hasReport = $report !== null && ($report['status'] ?? '') === 'reported';

        return view('patient_access/view', [
            'token'     => $token,
            'link'      => $link,
            'study'     => $study,
            'report'    => $hasReport ? $report : null,
            'hasReport' => $hasReport,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Verifica Documento + Fecha de nacimiento y habilita la sesión temporal. //
    /////////////////////////////////////////////////////////////////////////////
    public function verify(string $token): RedirectResponse
    {
        $this->applyPrivacyHeaders();

        $model = model(PatientAccessLinkModel::class);
        $link  = $model->findActiveByToken($token);

        if ($link === null) {
            return redirect()->to('/paciente/' . urlencode($token));
        }

        $ip  = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $this->request->getIPAddress());
        $key = 'patient_verify_' . md5($token . '_' . $ip);
        if (service('throttler')->check($key, 5, 60) === false) {
            AuditLogModel::record(
                'patient_access.throttled',
                (string) $link['study_instance_uid'],
                (int) $link['id'],
                'Demasiados intentos de verificación de paciente',
                $this->request
            );
            return redirect()->back()->with('error', 'Demasiados intentos. Espera 1 minuto antes de volver a intentarlo.');
        }

        $normId    = PatientAccessLinkModel::normalizePatientId((string) $this->request->getPost('patient_id'));
        $normBirth = PatientAccessLinkModel::normalizeBirthDate((string) $this->request->getPost('patient_birth_date'));

        $idOk    = $normId !== '' && password_verify($normId, (string) $link['patient_id_hash']);
        $birthOk = $normBirth !== null && password_verify($normBirth, (string) $link['patient_birth_date_hash']);

        if (! ($idOk && $birthOk)) {
            $attempts = (int) $link['failed_attempts'] + 1;
            $update   = ['failed_attempts' => $attempts];

            if ($attempts >= PatientAccessLinkModel::MAX_FAILED_ATTEMPTS) {
                $update['locked_at'] = date('Y-m-d H:i:s');
                $model->update((int) $link['id'], $update);

                AuditLogModel::record(
                    'patient_access.locked',
                    (string) $link['study_instance_uid'],
                    (int) $link['id'],
                    'Enlace bloqueado tras ' . $attempts . ' intentos fallidos',
                    $this->request
                );

                return redirect()->to('/paciente/' . urlencode($token));
            }

            $model->update((int) $link['id'], $update);
            AuditLogModel::record(
                'patient_access.failed',
                (string) $link['study_instance_uid'],
                (int) $link['id'],
                'Verificación de identidad fallida (intento ' . $attempts . '/' . PatientAccessLinkModel::MAX_FAILED_ATTEMPTS . ')',
                $this->request
            );

            return redirect()->back()->with('error', 'Los datos introducidos no coinciden o el enlace no es válido.');
        }

        $model->update((int) $link['id'], ['failed_attempts' => 0]);

        session()->set('patient_access', [
            'link_id'     => (int) $link['id'],
            'study_uid'   => (string) $link['study_instance_uid'],
            'unlocked_at' => time(),
        ]);

        return redirect()->to('/paciente/' . urlencode($token));
    }

    /////////////////////////////////////////////////////////////////////////////
    // Sirve el informe médico en PDF al paciente verificado.                  //
    /////////////////////////////////////////////////////////////////////////////
    public function reportPdf(string $token): ResponseInterface|RedirectResponse
    {
        $this->applyPrivacyHeaders();

        $link = model(PatientAccessLinkModel::class)->findActiveByToken($token);
        if ($link === null || ! $this->hasValidSessionAccess($link)) {
            return redirect()->to('/paciente/' . urlencode($token));
        }

        $studyUid = (string) $link['study_instance_uid'];
        $report   = model(MedicalReportModel::class)->findByStudyUid($studyUid);
        if ($report === null || ($report['status'] ?? '') !== 'reported') {
            return redirect()->to('/paciente/' . urlencode($token))
                ->with('error', 'El informe médico todavía no está disponible.');
        }

        $study    = model(StudyModel::class)->findByStudyUid($studyUid);
        $pdfBytes = (new ReportPdfService())->renderBytes($studyUid, $study, $report);

        AuditLogModel::record(
            'patient_access.report_downloaded',
            $studyUid,
            (int) $link['id'],
            'Paciente descargó informe médico PDF',
            $this->request
        );

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="informe-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $studyUid) . '.pdf"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($pdfBytes);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Descarga el archivo ZIP DICOM del estudio para el paciente verificado.  //
    /////////////////////////////////////////////////////////////////////////////
    public function downloadZip(string $token): ResponseInterface|RedirectResponse
    {
        $this->applyPrivacyHeaders();

        $link = model(PatientAccessLinkModel::class)->findActiveByToken($token);
        if ($link === null || ! $this->hasValidSessionAccess($link)) {
            return redirect()->to('/paciente/' . urlencode($token));
        }

        if ((int) ($link['allow_download'] ?? 0) !== 1) {
            return redirect()->to('/paciente/' . urlencode($token))
                ->with('error', 'La descarga de archivos DICOM no está habilitada en este enlace.');
        }

        $studyUid  = (string) $link['study_instance_uid'];
        $study     = model(StudyModel::class)->findByStudyUid($studyUid);
        $orthancId = $study['orthanc_id'] ?? null;
        $orthanc   = new OrthancClient();

        if (empty($orthancId)) {
            $found     = $orthanc->findStudyByUid($studyUid);
            $orthancId = $found['ID'] ?? null;
        }

        if (empty($orthancId)) {
            return redirect()->to('/paciente/' . urlencode($token))
                ->with('error', 'Estudio no disponible para descarga en este momento.');
        }

        AuditLogModel::record(
            'patient_access.zip_downloaded',
            $studyUid,
            (int) $link['id'],
            'Paciente descargó estudio DICOM (.zip)',
            $this->request
        );

        $filename    = 'estudio-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $studyUid) . '.zip';
        $headersSent = false;

        $streamed = $orthanc->streamStudyArchive(
            (string) $orthancId,
            static function () use ($filename, &$headersSent): void {
                $headersSent = true;
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Referrer-Policy: no-referrer');
            },
            static function (string $chunk): int {
                echo $chunk;
                flush();
                return strlen($chunk);
            }
        );

        if ($headersSent) {
            exit;
        }

        if (! $streamed) {
            return redirect()->to('/paciente/' . urlencode($token))
                ->with('error', 'No se pudo generar el archivo ZIP del estudio.');
        }

        return $this->response->setStatusCode(200);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Cierra la sesión temporal del paciente (útil en equipos compartidos).   //
    /////////////////////////////////////////////////////////////////////////////
    public function logout(string $token): RedirectResponse
    {
        session()->remove('patient_access');
        return redirect()->to('/paciente/' . urlencode($token))
            ->with('message', 'Has cerrado el acceso al estudio.');
    }

    private function canManagePatientAccess(?string $studyUid = null): bool
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('studies.share')) {
            return false;
        }

        if ($studyUid !== null && user_center_id($user) !== null) {
            $study = model(StudyModel::class)->findByStudyUid($studyUid);
            return user_can_access_study($user, $study);
        }

        return true;
    }

    /**
     * Comprueba si la sesión temporal del paciente corresponde a este enlace y no expiró.
     *
     * @param array<string, mixed> $link
     */
    private function hasValidSessionAccess(array $link): bool
    {
        $access = session()->get('patient_access');
        if (! is_array($access)) {
            return false;
        }

        $unlockedAt = (int) ($access['unlocked_at'] ?? 0);
        if ($unlockedAt <= 0 || (time() - $unlockedAt) > self::SESSION_TTL_SECONDS) {
            session()->remove('patient_access');
            return false;
        }

        return ($access['link_id'] ?? null) === (int) $link['id']
            && ($access['study_uid'] ?? null) === (string) $link['study_instance_uid'];
    }

    private function applyPrivacyHeaders(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Obtiene los metadatos del estudio combinando la tabla local y Orthanc como respaldo.
     *
     * @return array<string, mixed>
     */
    private function resolveStudyInfo(string $studyUid): array
    {
        $study = model(StudyModel::class)->findByStudyUid($studyUid) ?? [];

        $orthancStudy = (new OrthancClient())->findStudyByUid($studyUid);
        if (is_array($orthancStudy)) {
            $tags    = is_array($orthancStudy['MainDicomTags'] ?? null) ? $orthancStudy['MainDicomTags'] : [];
            $patient = is_array($orthancStudy['PatientMainDicomTags'] ?? null) ? $orthancStudy['PatientMainDicomTags'] : [];

            $study['orthanc_id']        = ! empty($study['orthanc_id']) ? $study['orthanc_id'] : ($orthancStudy['ID'] ?? null);
            $study['patient_name']      = ! empty($study['patient_name']) ? $study['patient_name'] : ($patient['PatientName'] ?? null);
            $study['patient_id']        = $patient['PatientID'] ?? ($study['patient_id'] ?? null);
            $study['study_description'] = ! empty($study['study_description']) ? $study['study_description'] : ($tags['StudyDescription'] ?? null);
            $study['study_date']        = ! empty($study['study_date']) ? $study['study_date'] : ($tags['StudyDate'] ?? null);
            $study['_orthanc']          = $orthancStudy;
        }

        return $study;
    }
}
