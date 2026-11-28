<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Gestión del perfil del usuario autenticado (datos, contraseña y firma).     //
/////////////////////////////////////////////////////////////////////////////////
class ProfileController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de perfil del usuario conectado.                  //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        return view('profile/index', [
            'title' => 'Mi Perfil | PACSLine',
            'user'  => $user,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza datos personales, datos médicos y firma digital del usuario.  //
    /////////////////////////////////////////////////////////////////////////////
    public function update(): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $userId = (int) $user->id;
        $rules  = [
            'username' => [
                'label' => 'Usuario',
                'rules' => "required|min_length[3]|max_length[30]|is_unique[users.username,id,{$userId}]",
            ],
            'email' => [
                'label' => 'Correo electrónico',
                'rules' => "required|valid_email|max_length[254]|is_unique[auth_identities.secret,user_id,{$userId}]",
            ],
            'full_name' => [
                'label' => 'Nombre completo',
                'rules' => 'permit_empty|max_length[150]',
            ],
            'specialty' => [
                'label' => 'Especialidad',
                'rules' => 'permit_empty|max_length[120]',
            ],
            'license_number' => [
                'label' => 'Nº de colegiado',
                'rules' => 'permit_empty|max_length[64]',
            ],
        ];

        $file = $this->request->getFile('signature');
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $rules['signature'] = [
                'label' => 'Firma',
                'rules' => 'uploaded[signature]|ext_in[signature,png,jpg,jpeg]|max_size[signature,2048]',
            ];
        }

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->validator->getValidated();
        $user->username  = $data['username'];
        $user->email     = $data['email'];
        $user->full_name = ($data['full_name'] ?? '') ?: null;

        if ($user->inGroup('medico')) {
            $user->specialty      = ($data['specialty'] ?? '') ?: null;
            $user->license_number = ($data['license_number'] ?? '') ?: null;
        }

        if ($user->inGroup('medico') && $file !== null && $file->isValid()) {
            $dir = WRITEPATH . 'uploads/signatures';
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            if (! empty($user->signature_file)) {
                @unlink($dir . '/' . basename((string) $user->signature_file));
            }

            $storedName = $file->getRandomName();
            $file->move($dir, $storedName);
            $user->signature_file = $storedName;
        }

        auth()->getProvider()->save($user);
        AuditLogModel::record('profile.updated', null, null, 'Perfil actualizado', $this->request);

        return redirect()->to('/profile')->with('message', 'Perfil actualizado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Cambia la contraseña del usuario autenticado previa validación actual.  //
    /////////////////////////////////////////////////////////////////////////////
    public function updatePassword(): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $rules = [
            'current_password' => [
                'label' => 'Contraseña actual',
                'rules' => 'required',
            ],
            'new_password' => [
                'label' => 'Nueva contraseña',
                'rules' => 'required|min_length[8]|max_length[64]',
            ],
            'confirm_password' => [
                'label'  => 'Confirmar contraseña',
                'rules'  => 'required|matches[new_password]',
                'errors' => [
                    'matches' => 'Las contraseñas no coinciden.',
                ],
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $data  = $this->validator->getValidated();
        $check = auth()->check([
            'email'    => $user->email,
            'password' => $data['current_password'],
        ]);

        if (! $check->isOK()) {
            return redirect()->back()->with('error', 'Contraseña actual incorrecta.');
        }

        $user->fill(['password' => $data['new_password']]);
        auth()->getProvider()->save($user);

        AuditLogModel::record('profile.password_changed', null, null, 'Contraseña actualizada', $this->request);

        return redirect()->to('/profile')->with('message', 'Contraseña actualizada.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Sirve de forma segura la imagen de la firma médica del usuario.          //
    /////////////////////////////////////////////////////////////////////////////
    public function signature(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null || empty($user->signature_file)) {
            return $this->response->setStatusCode(404);
        }

        $path = WRITEPATH . 'uploads/signatures/' . basename((string) $user->signature_file);
        if (! is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setBody((string) file_get_contents($path));
    }
}
