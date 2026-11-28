<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use App\Models\MedicalCenterModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;

/////////////////////////////////////////////////////////////////////////////////
// Administración de usuarios, roles (supervisor, médico, usuario, observador).//
/////////////////////////////////////////////////////////////////////////////////
class UserController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista todos los usuarios registrados en la plataforma.                  //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $users       = auth()->getProvider()->orderBy('id', 'DESC')->findAll();
        $centersById = [];
        foreach (model(MedicalCenterModel::class)->orderBy('name', 'ASC')->findAll() as $center) {
            $centersById[(int) $center['id']] = (string) $center['name'];
        }

        return view('users/index', [
            'title'       => 'Usuarios y Roles | PACSLine',
            'users'       => $users,
            'centersById' => $centersById,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario para dar de alta un nuevo usuario.                //
    /////////////////////////////////////////////////////////////////////////////
    public function create(): string|RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $centers = model(MedicalCenterModel::class)->where('active', 1)->orderBy('name', 'ASC')->findAll();

        return view('users/create', [
            'title'   => 'Crear Usuario | PACSLine',
            'centers' => $centers,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Crea el usuario, asigna su rol en Shield y guarda datos médicos.        //
    /////////////////////////////////////////////////////////////////////////////
    public function store(): RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $rules = [
            'username' => [
                'label' => 'Usuario',
                'rules' => 'required|min_length[3]|max_length[30]|is_unique[users.username]',
            ],
            'full_name' => [
                'label' => 'Nombre completo',
                'rules' => 'permit_empty|max_length[150]',
            ],
            'email' => [
                'label' => 'Correo electrónico',
                'rules' => 'required|valid_email|is_unique[auth_identities.secret]',
            ],
            'password' => [
                'label' => 'Contraseña',
                'rules' => 'required|min_length[6]|max_length[64]',
            ],
            'password_confirm' => [
                'label' => 'Confirmar contraseña',
                'rules' => 'required|matches[password]',
            ],
            'role' => [
                'label' => 'Rol',
                'rules' => 'required|in_list[admin,medico,usuario,observador]',
            ],
            'specialty' => [
                'label' => 'Especialidad',
                'rules' => 'permit_empty|max_length[120]',
            ],
            'license_number' => [
                'label' => 'Nº de colegiado',
                'rules' => 'permit_empty|max_length[64]',
            ],
            'center_id' => [
                'label' => 'Centro asignado',
                'rules' => 'permit_empty|integer',
            ],
        ];

        $messages = [
            'password_confirm' => [
                'matches' => 'Las contraseñas no coinciden.',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data     = $this->validator->getValidated();
        $centerId = $data['role'] === 'admin' ? null : (! empty($data['center_id']) ? (int) $data['center_id'] : null);
        if ($centerId !== null && model(MedicalCenterModel::class)->find($centerId) === null) {
            return redirect()->back()->withInput()->with('error', 'El centro seleccionado no existe.');
        }

        $users = auth()->getProvider();

        $user = new User([
            'username'       => $data['username'],
            'full_name'      => trim((string) ($data['full_name'] ?? '')) ?: null,
            'email'          => $data['email'],
            'password'       => $data['password'],
            'specialty'      => $data['role'] === 'medico' ? (trim((string) ($data['specialty'] ?? '')) ?: null) : null,
            'license_number' => $data['role'] === 'medico' ? (trim((string) ($data['license_number'] ?? '')) ?: null) : null,
            'center_id'      => $centerId,
        ]);

        $users->save($user);

        $saved = $users->findById($users->getInsertID());
        if ($saved !== null) {
            if ($this->request->getPost('active')) {
                $saved->activate();
            }
            $saved->addGroup($data['role']);
        }

        AuditLogModel::record('user.created', null, null, "Usuario {$data['email']} ({$data['role']})", $this->request);

        return redirect()->to('/users')->with('message', 'Usuario creado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de edición de un usuario existente.               //
    /////////////////////////////////////////////////////////////////////////////
    public function edit(int $id): string|RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        $centers = model(MedicalCenterModel::class)->orderBy('name', 'ASC')->findAll();

        return view('users/edit', [
            'title'   => 'Editar Usuario | PACSLine',
            'user'    => $user,
            'centers' => $centers,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza datos, rol, estado y contraseña opcional de un usuario.       //
    /////////////////////////////////////////////////////////////////////////////
    public function update(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $users = auth()->getProvider();
        $user  = $users->findById($id);
        if ($user === null) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        $rules = [
            'username' => [
                'label' => 'Usuario',
                'rules' => "required|min_length[3]|max_length[30]|is_unique[users.username,id,{$id}]",
            ],
            'email' => [
                'label' => 'Correo electrónico',
                'rules' => "required|valid_email|max_length[254]|is_unique[auth_identities.secret,user_id,{$id}]",
            ],
            'full_name' => [
                'label' => 'Nombre completo',
                'rules' => 'permit_empty|max_length[150]',
            ],
            'role' => [
                'label' => 'Rol',
                'rules' => 'required|in_list[admin,medico,usuario,observador]',
            ],
            'specialty' => [
                'label' => 'Especialidad',
                'rules' => 'permit_empty|max_length[120]',
            ],
            'license_number' => [
                'label' => 'Nº de colegiado',
                'rules' => 'permit_empty|max_length[64]',
            ],
            'center_id' => [
                'label' => 'Centro asignado',
                'rules' => 'permit_empty|integer',
            ],
            'new_password' => [
                'label' => 'Nueva contraseña',
                'rules' => 'permit_empty|min_length[6]|max_length[64]',
            ],
            'new_password_confirm' => [
                'label' => 'Confirmar contraseña',
                'rules' => 'required_with[new_password]|matches[new_password]',
            ],
        ];

        $messages = [
            'new_password_confirm' => [
                'required_with' => 'Confirma la nueva contraseña.',
                'matches'       => 'Las contraseñas no coinciden.',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data     = $this->validator->getValidated();
        $centerId = $data['role'] === 'admin' ? null : (! empty($data['center_id']) ? (int) $data['center_id'] : null);
        if ($centerId !== null && model(MedicalCenterModel::class)->find($centerId) === null) {
            return redirect()->back()->withInput()->with('error', 'El centro seleccionado no existe.');
        }

        $user->username       = $data['username'];
        $user->email          = $data['email'];
        $user->full_name      = trim((string) ($data['full_name'] ?? '')) ?: null;
        $user->specialty      = $data['role'] === 'medico' ? (trim((string) ($data['specialty'] ?? '')) ?: null) : null;
        $user->license_number = $data['role'] === 'medico' ? (trim((string) ($data['license_number'] ?? '')) ?: null) : null;
        $user->center_id      = $centerId;
        $user->active         = $this->request->getPost('active') ? 1 : 0;

        if (! empty($data['new_password'])) {
            $user->fill(['password' => $data['new_password']]);
        }

        $users->save($user);
        $user->syncGroups($data['role']);

        AuditLogModel::record('user.updated', null, null, "Usuario #{$id} ({$data['role']})", $this->request);

        return redirect()->to('/users')->with('message', 'Usuario actualizado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina un usuario de forma permanente (impide autoeliminación).        //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(int $id): RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        if ((int) auth()->id() === $id) {
            return redirect()->to('/users')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $users = auth()->getProvider();
        $user  = $users->findById($id);
        if ($user === null) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }

        $label = $user->email ?: $user->username;
        $users->delete($id, true);

        AuditLogModel::record('user.deleted', null, null, "Usuario #{$id} ({$label})", $this->request);

        return redirect()->to('/users')->with('message', 'Usuario eliminado.');
    }
}
