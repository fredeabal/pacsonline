<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OrthancClient;
use App\Models\AuditLogModel;
use App\Models\MedicalCenterModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Gestión de centros médicos remitentes y nodos DICOM autorizados en Orthanc. //
/////////////////////////////////////////////////////////////////////////////////
class CenterController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista todos los centros médicos y nodos DICOM configurados.             //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $centers = model(MedicalCenterModel::class)->orderBy('id', 'DESC')->findAll();

        return view('centers/index', [
            'title'   => 'Centros Médicos | PACS Online',
            'centers' => $centers,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de alta de un nuevo centro o nodo DICOM.          //
    /////////////////////////////////////////////////////////////////////////////
    public function create(): string|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        return view('centers/create', [
            'title' => 'Nuevo Centro Médico | PACS Online',
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Registra el centro, genera su token de gateway y sincroniza Orthanc.    //
    /////////////////////////////////////////////////////////////////////////////
    public function store(): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $parsed = $this->validateCenterRequest(null);
        if (isset($parsed['redirect'])) {
            return $parsed['redirect'];
        }

        $plainToken = 'pacs_' . bin2hex(random_bytes(24));
        $model      = model(MedicalCenterModel::class);
        $centerId   = (int) $model->insert([
            'name'           => $parsed['name'],
            'ae_title'       => $parsed['ae_title'],
            'api_token_hash' => hash('sha256', $plainToken),
            'active'         => 1,
            'can_receive'    => $parsed['can_receive'],
            'can_send'       => $parsed['can_send'],
            'dicom_host'     => $parsed['dicom_host'],
            'dicom_port'     => $parsed['dicom_port'],
        ], true);

        $center = $model->find($centerId);
        $sync   = $center !== null ? $this->syncOrthancModality($center) : ['ok' => true, 'error' => null];

        AuditLogModel::record(
            'center.created',
            null,
            null,
            "Centro {$parsed['name']} ({$parsed['ae_title']}) recibir={$parsed['can_receive']} enviar={$parsed['can_send']}",
            $this->request
        );

        $redirect = redirect()->to('/centers');
        if ($parsed['can_receive'] === 1) {
            $redirect = $redirect->with('generated_token', $plainToken);
        }

        if (! $sync['ok']) {
            return $redirect->with('warning', 'Centro registrado, pero no se pudo sincronizar con Orthanc: ' . $sync['error']);
        }

        return $redirect->with(
            'message',
            $parsed['can_receive'] === 1
                ? 'Centro registrado. Copia el token ahora.'
                : 'Nodo destino registrado correctamente.'
        );
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de edición de un centro médico existente.         //
    /////////////////////////////////////////////////////////////////////////////
    public function edit(int $id): string|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $center = model(MedicalCenterModel::class)->find($id);
        if ($center === null) {
            return redirect()->to('/centers')->with('error', 'Centro no encontrado.');
        }

        return view('centers/edit', [
            'title'  => 'Editar Centro Médico | PACS Online',
            'center' => $center,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza datos del centro y sincroniza su modalidad en Orthanc.        //
    /////////////////////////////////////////////////////////////////////////////
    public function update(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model  = model(MedicalCenterModel::class);
        $center = $model->find($id);
        if ($center === null) {
            return redirect()->to('/centers')->with('error', 'Centro no encontrado.');
        }

        $parsed = $this->validateCenterRequest($id);
        if (isset($parsed['redirect'])) {
            return $parsed['redirect'];
        }

        $model->update($id, [
            'name'        => $parsed['name'],
            'ae_title'    => $parsed['ae_title'],
            'active'      => $this->request->getPost('active') ? 1 : 0,
            'can_receive' => $parsed['can_receive'],
            'can_send'    => $parsed['can_send'],
            'dicom_host'  => $parsed['dicom_host'],
            'dicom_port'  => $parsed['dicom_port'],
        ]);

        $updated = $model->find($id);
        $sync    = $updated !== null ? $this->syncOrthancModality($updated) : ['ok' => true, 'error' => null];

        AuditLogModel::record(
            'center.updated',
            null,
            null,
            "Centro {$parsed['name']} ({$parsed['ae_title']}) recibir={$parsed['can_receive']} enviar={$parsed['can_send']}",
            $this->request
        );

        if (! $sync['ok']) {
            return redirect()->to('/centers')->with('warning', 'Centro actualizado, pero no se pudo sincronizar con Orthanc: ' . $sync['error']);
        }

        return redirect()->to('/centers')->with('message', 'Centro actualizado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Ejecuta un C-ECHO de prueba hacia el nodo DICOM configurado (AJAX).     //
    /////////////////////////////////////////////////////////////////////////////
    public function testConnection(int $id): ResponseInterface|RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON(['ok' => false, 'message' => 'Sin permiso.']);
            }
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $center = model(MedicalCenterModel::class)->find($id);
        if ($center === null) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'message' => 'Centro no encontrado.']);
        }

        if (empty($center['can_send']) || empty($center['dicom_host']) || empty($center['dicom_port'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'ok'      => false,
                'message' => 'El centro no tiene habilitado el envío DICOM o le falta IP/Puerto.',
            ]);
        }

        $sync = $this->syncOrthancModality($center);
        if (! $sync['ok']) {
            return $this->response->setStatusCode(502)->setJSON([
                'ok'      => false,
                'message' => 'No se pudo registrar la modalidad en Orthanc: ' . $sync['error'],
            ]);
        }

        $orthanc = new OrthancClient();
        $res     = $orthanc->echoModality(MedicalCenterModel::orthancModalityId($center));

        AuditLogModel::record(
            ! empty($res['ok']) ? 'center.echo_ok' : 'center.echo_failed',
            null,
            null,
            "C-Echo {$center['ae_title']} ({$center['dicom_host']}:{$center['dicom_port']}): " . ($res['ok'] ? 'OK' : ($res['error'] ?? 'Error')),
            $this->request
        );

        if (! empty($res['ok'])) {
            return $this->response->setJSON([
                'ok'      => true,
                'message' => "C-Echo exitoso con {$center['ae_title']} ({$center['dicom_host']}:{$center['dicom_port']}).",
            ]);
        }

        return $this->response->setStatusCode(502)->setJSON([
            'ok'      => false,
            'message' => 'C-Echo falló: ' . ($res['error'] ?? 'Sin respuesta del nodo DICOM.'),
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Regenera el token Bearer del gateway para el centro seleccionado.       //
    /////////////////////////////////////////////////////////////////////////////
    public function rotateToken(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model  = model(MedicalCenterModel::class);
        $center = $model->find($id);
        if ($center === null) {
            return redirect()->to('/centers')->with('error', 'Centro no encontrado.');
        }

        $plainToken = 'pacs_' . bin2hex(random_bytes(24));
        $model->update($id, ['api_token_hash' => hash('sha256', $plainToken)]);

        AuditLogModel::record('center.token_rotated', null, null, "Token rotado para {$center['ae_title']}", $this->request);

        return redirect()->to('/centers')
            ->with('generated_token', $plainToken)
            ->with('message', 'Token renovado. Actualiza el centro.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina el centro de SQLite y su modalidad asociada en Orthanc.         //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('settings.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $model  = model(MedicalCenterModel::class);
        $center = $model->find($id);
        if ($center === null) {
            return redirect()->to('/centers')->with('error', 'Centro no encontrado.');
        }

        (new OrthancClient())->deleteModality(MedicalCenterModel::orthancModalityId($center));
        model(\App\Models\StudyModel::class)->where('center_id', $id)->set(['center_id' => null])->update();
        $model->delete($id);

        AuditLogModel::record(
            'center.deleted',
            null,
            null,
            "Centro eliminado: {$center['name']} ({$center['ae_title']})",
            $this->request
        );

        return redirect()->to('/centers')->with('message', 'Centro / nodo eliminado.');
    }

    /**
     * Valida y normaliza los campos comunes de creación/edición de un centro/nodo DICOM.
     */
    private function validateCenterRequest(?int $ignoreId): array
    {
        $canReceive = $this->request->getPost('can_receive') ? 1 : 0;
        $canSend    = $this->request->getPost('can_send') ? 1 : 0;

        $uniqueRule = $ignoreId !== null
            ? "is_unique[medical_centers.ae_title,id,{$ignoreId}]"
            : 'is_unique[medical_centers.ae_title]';

        $rules = [
            'name' => [
                'label' => 'Nombre',
                'rules' => 'required|min_length[3]|max_length[150]',
            ],
            'ae_title' => [
                'label' => 'AE Title',
                'rules' => "required|alpha_dash|min_length[2]|max_length[32]|{$uniqueRule}",
            ],
        ];

        if ($canSend === 1) {
            $rules['dicom_host'] = [
                'label' => 'IP / Host DICOM',
                'rules' => 'required|max_length[255]',
            ];
            $rules['dicom_port'] = [
                'label' => 'Puerto DICOM',
                'rules' => 'required|integer|greater_than[0]|less_than_equal_to[65535]',
            ];
        }

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return ['redirect' => redirect()->back()->withInput()->with('errors', $this->validator->getErrors())];
        }

        if ($canReceive === 0 && $canSend === 0) {
            return [
                'redirect' => redirect()->back()->withInput()->with('errors', [
                    'direction' => 'Debes habilitar al menos una función: Recibir estudios o Enviar estudios.',
                ]),
            ];
        }

        $data = $this->validator->getValidated();

        return [
            'name'        => trim((string) $data['name']),
            'ae_title'    => strtoupper(trim((string) $data['ae_title'])),
            'can_receive' => $canReceive,
            'can_send'    => $canSend,
            'dicom_host'  => $canSend === 1 ? trim((string) ($data['dicom_host'] ?? '')) : null,
            'dicom_port'  => $canSend === 1 ? (int) ($data['dicom_port'] ?? 0) : null,
        ];
    }

    /**
     * Sincroniza la modalidad DICOM del centro en Orthanc según su estado activo, can_send y can_receive.
     */
    private function syncOrthancModality(array $center): array
    {
        $orthanc    = new OrthancClient();
        $modalityId = MedicalCenterModel::orthancModalityId($center);
        $isActive   = ! empty($center['active']);
        $canReceive = ! empty($center['can_receive']);
        $canSend    = ! empty($center['can_send']) && ! empty($center['dicom_host']) && ! empty($center['dicom_port']);

        if ($isActive && $canSend) {
            $res = $orthanc->putModality($modalityId, [
                'AET'        => (string) $center['ae_title'],
                'Host'       => (string) $center['dicom_host'],
                'Port'       => (int) $center['dicom_port'],
                'AllowStore' => $canReceive,
                'AllowEcho'  => true,
                'AllowFind'  => true,
                'AllowGet'   => true,
                'AllowMove'  => true,
            ]);

            return ['ok' => ! empty($res['ok']), 'error' => $res['error'] ?? null];
        }

        if ($isActive && $canReceive) {
            // Centro habilitado solo para enviar estudios hacia nuestro PACS (sin destino C-STORE saliente)
            $res = $orthanc->putModality($modalityId, [
                'AET'        => (string) $center['ae_title'],
                'Host'       => '0.0.0.0',
                'Port'       => 104,
                'AllowStore' => true,
                'AllowEcho'  => false,
                'AllowFind'  => false,
                'AllowGet'   => false,
                'AllowMove'  => false,
            ]);

            return ['ok' => ! empty($res['ok']), 'error' => $res['error'] ?? null];
        }

        $res = $orthanc->deleteModality($modalityId);

        return ['ok' => ! empty($res['ok']), 'error' => $res['error'] ?? null];
    }
}
