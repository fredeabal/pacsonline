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

        $tempBytes = 0;
        foreach (['cache', 'logs', 'session', 'debugbar'] as $dir) {
            $tempBytes += $this->writableDirSize(WRITEPATH . $dir);
        }
        $tempSizeFormatted = $this->formatBytes($tempBytes);

        $pacsAet  = (string) (getenv('ORTHANC_AET') ?: 'PACSONLINE');
        $pacsPort = (int) (getenv('ORTHANC_DICOM_PORT') ?: getenv('DICOM_PORT') ?: 4242);

        $auditFilters = [
            'category' => (string) ($this->request->getGet('category') ?? 'all'),
            'date'     => (string) ($this->request->getGet('date') ?? 'all'),
            'q'        => (string) ($this->request->getGet('q') ?? ''),
        ];
        $auditPage = max(1, (int) ($this->request->getGet('page') ?? 1));
        $auditData = model(AuditLogModel::class)->paginatedWithUsers($auditFilters, 50, $auditPage);

        return view('settings/index', [
            'title'             => 'Configuración | PACS Online',
            'currentTab'        => $currentTab,
            'orthancAlive'      => $orthanc->isAlive(),
            'pacsAet'           => $pacsAet,
            'pacsPort'          => $pacsPort,
            'tempSizeFormatted' => $tempSizeFormatted,
            'logs'              => $auditData['logs'],
            'auditData'         => $auditData,
            'auditFilters'      => $auditFilters,
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
    // Limpia logs, sesiones antiguas, caché y debugbar de CodeIgniter 4.      //
    /////////////////////////////////////////////////////////////////////////////
    public function clearTemp(): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        cache()->clean();

        $activeSessionFile = '';
        $sid               = session_id();
        if ($sid !== '') {
            $activeSessionFile = config('Session')->cookieName . $sid;
        }

        $targets = [
            'cache'    => [],
            'logs'     => [],
            'debugbar' => [],
            'session'  => $activeSessionFile !== '' ? [$activeSessionFile] : [],
        ];

        $totalFiles = 0;
        $totalBytes = 0;

        foreach ($targets as $dir => $exclude) {
            $stats       = $this->clearWritableDirectory(WRITEPATH . $dir, $exclude);
            $totalFiles += $stats['files'];
            $totalBytes += $stats['bytes'];
        }

        $freedFormatted = $this->formatBytes($totalBytes);
        AuditLogModel::record(
            'settings.temp_cleared',
            null,
            null,
            "Limpieza CI4: {$totalFiles} archivos eliminados ({$freedFormatted})",
            $this->request
        );

        return redirect()->to('/settings?tab=maintenance')
            ->with('message', "Limpieza completada: {$totalFiles} archivo(s) eliminado(s), {$freedFormatted} liberados (sesión activa conservada).");
    }

    /**
     * Elimina archivos temporales de un subdirectorio de writable/ conservando archivos base.
     *
     * @param list<string> $exclude
     *
     * @return array{files: int, bytes: int}
     */
    private function clearWritableDirectory(string $dir, array $exclude = []): array
    {
        if (! is_dir($dir)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $protected = array_merge(['.', '..', 'index.html', '.htaccess', '.gitkeep'], $exclude);
        $files     = 0;
        $bytes     = 0;

        $entries = scandir($dir);
        if ($entries === false) {
            return ['files' => 0, 'bytes' => 0];
        }

        foreach ($entries as $entry) {
            if (in_array($entry, $protected, true)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) {
                continue;
            }

            $size = (int) @filesize($path);
            if (@unlink($path)) {
                $files++;
                $bytes += $size;
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    /**
     * Calcula el tamaño en bytes de los archivos limpiables en un subdirectorio de writable/.
     */
    private function writableDirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }

        $protected = ['.', '..', 'index.html', '.htaccess', '.gitkeep'];
        $entries   = scandir($dir);
        if ($entries === false) {
            return 0;
        }

        $bytes = 0;
        foreach ($entries as $entry) {
            if (in_array($entry, $protected, true)) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $bytes += (int) @filesize($path);
            }
        }

        return $bytes;
    }

    /**
     * Formatea un tamaño en bytes a KB o MB legibles.
     */
    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 2) . ' MB'
            : number_format($bytes / 1024, 1) . ' KB';
    }
}
