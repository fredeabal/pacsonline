<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ReportPdfService;
use App\Models\AuditLogModel;
use App\Models\StudyModel;
use App\Models\StudyShareModel;
use CodeIgniter\HTTP\RedirectResponse;

/////////////////////////////////////////////////////////////////////////////////
// Enlaces compartidos con expiración y PIN para acceso externo a estudios.    //
/////////////////////////////////////////////////////////////////////////////////
class ShareController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista los enlaces compartidos activos e históricos de un estudio.       //
    /////////////////////////////////////////////////////////////////////////////
    public function manage(string $studyUid): string|RedirectResponse
    {
        if (! $this->canManageStudy($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para compartir.');
        }

        $study  = model(StudyModel::class)->findByStudyUid($studyUid);
        $shares = model(StudyShareModel::class)
            ->where('study_instance_uid', $studyUid)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        foreach ($shares as &$s) {
            $s['_qr'] = ReportPdfService::generateQrDataUri(base_url('share/' . $s['token']));
        }
        unset($s);

        return view('shares/manage', [
            'title'    => 'Compartir Estudio | PACS Online',
            'studyUid' => $studyUid,
            'study'    => $study,
            'shares'   => $shares,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario para generar un nuevo enlace compartido.          //
    /////////////////////////////////////////////////////////////////////////////
    public function createForm(string $studyUid): string|RedirectResponse
    {
        if (! $this->canManageStudy($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para compartir.');
        }

        $study = model(StudyModel::class)->findByStudyUid($studyUid);

        return view('shares/create', [
            'title'    => 'Nuevo Enlace Seguro | PACS Online',
            'studyUid' => $studyUid,
            'study'    => $study,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Crea un nuevo enlace compartido con token aleatorio, PIN y expiración.  //
    /////////////////////////////////////////////////////////////////////////////
    public function create(): RedirectResponse
    {
        $rules = [
            'study_instance_uid' => [
                'label' => 'Estudio',
                'rules' => 'required|max_length[128]',
            ],
            'recipient_label' => [
                'label' => 'Destinatario',
                'rules' => 'permit_empty|max_length[150]',
            ],
            'pin' => [
                'label' => 'PIN',
                'rules' => 'permit_empty|min_length[4]|max_length[12]',
            ],
            'expires_days' => [
                'label' => 'Expiración',
                'rules' => 'required|in_list[1,7,30,0]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data     = $this->validator->getValidated();
        $studyUid = $data['study_instance_uid'];

        if (! $this->canManageStudy($studyUid)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para compartir.');
        }

        $days      = (int) $data['expires_days'];
        $expiresAt = $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;
        $pinHash   = ! empty($data['pin']) ? password_hash($data['pin'], PASSWORD_DEFAULT) : null;
        $token     = bin2hex(random_bytes(24));

        $shareId = model(StudyShareModel::class)->insert([
            'study_instance_uid' => $studyUid,
            'created_by_user_id' => auth()->id(),
            'token'              => $token,
            'recipient_label'    => $data['recipient_label'] ?: null,
            'pin_hash'           => $pinHash,
            'allow_download'     => $this->request->getPost('allow_download') ? 1 : 0,
            'expires_at'         => $expiresAt,
        ], true);

        AuditLogModel::record('share.created', $studyUid, (int) $shareId, 'Enlace creado', $this->request);

        return redirect()->to('/studies/share/' . urlencode($studyUid))
            ->with('message', 'Enlace generado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Revoca manualmente un enlace compartido antes de su caducidad.          //
    /////////////////////////////////////////////////////////////////////////////
    public function revoke(int $shareId): RedirectResponse
    {
        $shareModel = model(StudyShareModel::class);
        $share      = $shareModel->find($shareId);

        if ($share === null) {
            return redirect()->back()->with('error', 'Enlace no encontrado.');
        }

        if (! $this->canManageStudy($share['study_instance_uid'])) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para revocar.');
        }

        $shareModel->update($shareId, ['revoked_at' => date('Y-m-d H:i:s')]);
        AuditLogModel::record('share.revoked', $share['study_instance_uid'], $shareId, 'Enlace revocado', $this->request);

        return redirect()->back()->with('message', 'Acceso revocado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Vista pública de un enlace compartido (solicita PIN o muestra visor).   //
    /////////////////////////////////////////////////////////////////////////////
    public function publicView(string $token): string
    {
        $shareModel = model(StudyShareModel::class);
        $share      = $shareModel->findActiveByToken($token);

        if ($share === null) {
            return view('shares/expired');
        }

        $sessionAccess = session()->get('share_access');
        $isUnlocked    = is_array($sessionAccess)
            && ($sessionAccess['share_id'] ?? null) === (int) $share['id']
            && ($sessionAccess['study_uid'] ?? null) === $share['study_instance_uid'];

        if (! empty($share['pin_hash']) && ! $isUnlocked) {
            return view('shares/pin', [
                'token' => $token,
                'share' => $share,
            ]);
        }

        if (! $isUnlocked) {
            session()->set('share_access', [
                'share_id'  => (int) $share['id'],
                'study_uid' => $share['study_instance_uid'],
            ]);
        }

        $shareModel->update((int) $share['id'], [
            'views_count' => (int) $share['views_count'] + 1,
        ]);

        AuditLogModel::record(
            'share.accessed',
            $share['study_instance_uid'],
            (int) $share['id'],
            'Acceso de invitado al estudio',
            $this->request
        );

        $study = model(StudyModel::class)->findByStudyUid($share['study_instance_uid']);

        return view('shares/view', [
            'share' => $share,
            'study' => $study,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Verifica el PIN introducido y habilita la sesión temporal del invitado. //
    /////////////////////////////////////////////////////////////////////////////
    public function verifyPin(string $token): RedirectResponse
    {
        $share = model(StudyShareModel::class)->findActiveByToken($token);
        if ($share === null) {
            return redirect()->to('/share/' . urlencode($token));
        }

        $ip  = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $this->request->getIPAddress());
        $key = 'share_pin_' . md5($token . '_' . $ip);
        if (service('throttler')->check($key, 5, 60) === false) {
            AuditLogModel::record('share.pin_throttled', $share['study_instance_uid'], (int) $share['id'], 'Demasiados intentos de PIN', $this->request);
            return redirect()->back()->with('error', 'Demasiados intentos fallidos. Espera 1 minuto.');
        }

        $pin = (string) $this->request->getPost('pin');
        if (empty($share['pin_hash']) || ! password_verify($pin, $share['pin_hash'])) {
            AuditLogModel::record('share.pin_failed', $share['study_instance_uid'], (int) $share['id'], 'PIN incorrecto', $this->request);
            return redirect()->back()->with('error', 'PIN incorrecto.');
        }

        session()->set('share_access', [
            'share_id'  => (int) $share['id'],
            'study_uid' => $share['study_instance_uid'],
        ]);

        return redirect()->to('/share/' . urlencode($token));
    }

    private function canManageStudy(string $studyUid): bool
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('studies.share')) {
            return false;
        }

        if ($user->can('studies.view_all')) {
            return true;
        }

        $study = model(StudyModel::class)->findByStudyUid($studyUid);
        return $study !== null && (int) $study['owner_user_id'] === (int) $user->id;
    }
}
