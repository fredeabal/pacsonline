<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\NotificationService;
use App\Libraries\OrthancClient;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\RedirectResponse;

/////////////////////////////////////////////////////////////////////////////////
// Configuración general de la plataforma, correo/SMTP y estado de Orthanc.    //
/////////////////////////////////////////////////////////////////////////////////
class SettingController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Muestra la pantalla de ajustes (SMTP, estado PACS y respaldos).         //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $settings   = service('settings');
        $orthanc    = new OrthancClient();
        $currentTab = (string) ($this->request->getGet('tab') ?? 'pacs');
        if (! in_array($currentTab, ['pacs', 'smtp', 'audit', 'maintenance'], true)) {
            $currentTab = 'pacs';
        }

        $dbPath          = WRITEPATH . 'pacs.sqlite';
        $dbBytes         = is_file($dbPath) ? (int) filesize($dbPath) : 0;
        $dbSizeFormatted = $dbBytes >= 1048576
            ? number_format($dbBytes / 1048576, 2) . ' MB'
            : number_format($dbBytes / 1024, 1) . ' KB';

        $pacsAet  = (string) (getenv('ORTHANC_AET') ?: 'PACSONLINE');
        $pacsPort = (int) (getenv('ORTHANC_DICOM_PORT') ?: getenv('DICOM_PORT') ?: 4242);

        return view('settings/index', [
            'title'           => 'Configuración | PACS Online',
            'currentTab'      => $currentTab,
            'orthancAlive'    => $orthanc->isAlive(),
            'pacsAet'         => $pacsAet,
            'pacsPort'        => $pacsPort,
            'dbSizeFormatted' => $dbSizeFormatted,
            'logs'            => model(AuditLogModel::class)->orderBy('created_at', 'DESC')->findAll(200),
            'smtp'         => [
                'enabled'   => (bool) $settings->get('Smtp.enabled'),
                'host'      => (string) ($settings->get('Smtp.host') ?? ''),
                'port'      => (int) ($settings->get('Smtp.port') ?? 587),
                'user'      => (string) ($settings->get('Smtp.user') ?? ''),
                'hasPass'   => ! empty($settings->get('Smtp.pass')),
                'crypto'    => (string) ($settings->get('Smtp.crypto') ?? 'tls'),
                'fromEmail' => (string) ($settings->get('Smtp.fromEmail') ?? ''),
                'fromName'  => (string) ($settings->get('Smtp.fromName') ?? 'PACS Online'),
            ],
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Guarda la configuración del servidor SMTP para notificaciones.          //
    /////////////////////////////////////////////////////////////////////////////
    public function saveSmtp(): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $rules = [
            'host' => [
                'label' => 'Servidor SMTP',
                'rules' => 'required|max_length[255]',
            ],
            'port' => [
                'label' => 'Puerto',
                'rules' => 'required|integer|greater_than[0]|less_than_equal_to[65535]',
            ],
            'user' => [
                'label' => 'Usuario',
                'rules' => 'permit_empty|max_length[255]',
            ],
            'pass' => [
                'label' => 'Contraseña',
                'rules' => 'permit_empty|max_length[255]',
            ],
            'crypto' => [
                'label' => 'Cifrado',
                'rules' => 'required|in_list[tls,ssl,none]',
            ],
            'from_email' => [
                'label' => 'Correo remitente',
                'rules' => 'required|valid_email',
            ],
            'from_name' => [
                'label' => 'Nombre remitente',
                'rules' => 'required|max_length[100]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data     = $this->validator->getValidated();
        $settings = service('settings');

        $settings->set('Smtp.enabled', (bool) $this->request->getPost('enabled'));
        $settings->set('Smtp.host', $data['host']);
        $settings->set('Smtp.port', (int) $data['port']);
        $settings->set('Smtp.user', $data['user']);
        if (! empty($data['pass'])) {
            $settings->set('Smtp.pass', $data['pass']);
        }
        $settings->set('Smtp.crypto', $data['crypto'] === 'none' ? '' : $data['crypto']);
        $settings->set('Smtp.fromEmail', $data['from_email']);
        $settings->set('Smtp.fromName', $data['from_name']);

        AuditLogModel::record('settings.smtp_updated', null, null, 'Configuración SMTP actualizada', $this->request);

        return redirect()->to('/settings?tab=smtp')->with('message', 'Configuración SMTP guardada.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Envía un correo de prueba para verificar los ajustes SMTP guardados.    //
    /////////////////////////////////////////////////////////////////////////////
    public function testEmail(): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $rules = [
            'test_email' => [
                'label' => 'Correo de prueba',
                'rules' => 'required|valid_email',
            ],
        ];
        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $to  = (string) $this->validator->getValidated()['test_email'];
        $res = (new NotificationService())->sendTestEmail($to);

        if ($res['ok']) {
            return redirect()->to('/settings?tab=smtp')->with('message', 'Correo enviado a ' . $to . '.');
        }

        return redirect()->to('/settings?tab=smtp')->with('error', 'Error al enviar correo. Revisa el SMTP.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Genera y descarga una copia de seguridad de la base de datos SQLite.    //
    /////////////////////////////////////////////////////////////////////////////
    public function downloadBackup(): \CodeIgniter\HTTP\ResponseInterface|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $dbPath = WRITEPATH . 'pacs.sqlite';
        if (! is_file($dbPath)) {
            return redirect()->to('/settings?tab=maintenance')->with('error', 'Base de datos no encontrada.');
        }

        $tmpFile = WRITEPATH . 'cache/backup_' . date('Ymd_His') . '.sqlite';
        $src     = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $dst     = new \SQLite3($tmpFile);
        $src->backup($dst);
        $src->close();
        $dst->close();

        $bytes = file_get_contents($tmpFile);
        @unlink($tmpFile);

        if ($bytes === false) {
            return redirect()->to('/settings?tab=maintenance')->with('error', 'No se pudo generar la copia.');
        }

        AuditLogModel::record('settings.backup_downloaded', null, null, 'Backup SQLite descargado', $this->request);

        return $this->response
            ->setHeader('Content-Type', 'application/octet-stream')
            ->setHeader('Content-Disposition', 'attachment; filename="pacs-db-' . date('Ymd-His') . '.sqlite"')
            ->setBody($bytes);
    }
}
