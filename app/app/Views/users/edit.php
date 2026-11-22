<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Editar usuario</h5>
    <a href="<?= base_url('users') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <?php $currentRole = $user->getGroups()[0] ?? 'paciente'; ?>
    <form action="<?= base_url('users/update/' . (int) $user->id) ?>" method="post">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label for="username" class="form-label fw-semibold">Usuario</label>
          <input type="text" class="form-control" id="username" name="username" value="<?= esc(old('username', $user->username)) ?>" required>
        </div>

        <div class="col-md-4">
          <label for="full_name" class="form-label fw-semibold">Nombre completo</label>
          <input type="text" class="form-control" id="full_name" name="full_name" value="<?= esc(old('full_name', $user->full_name ?? '')) ?>">
        </div>

        <div class="col-md-4">
          <label for="email" class="form-label fw-semibold">Correo electrónico</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= esc(old('email', $user->email)) ?>" required>
        </div>

        <div class="<?= $currentRole === 'medico' ? 'col-md-4' : 'col-12' ?>" id="roleCol">
          <label for="role" class="form-label fw-semibold">Rol en el PACS</label>
          <select class="form-select" id="role" name="role" required>
            <option value="paciente" <?= $currentRole === 'paciente' ? 'selected' : '' ?>>Paciente</option>
            <option value="medico" <?= $currentRole === 'medico' ? 'selected' : '' ?>>Médico Informante</option>
            <option value="admin" <?= $currentRole === 'admin' ? 'selected' : '' ?>>Administrador</option>
          </select>
        </div>

        <div class="col-md-4 doctor-only-field <?= $currentRole !== 'medico' ? 'd-none' : '' ?>">
          <label for="specialty" class="form-label fw-semibold">Especialidad</label>
          <input type="text" class="form-control" id="specialty" name="specialty" value="<?= esc(old('specialty', $user->specialty ?? '')) ?>">
        </div>

        <div class="col-md-4 doctor-only-field <?= $currentRole !== 'medico' ? 'd-none' : '' ?>">
          <label for="license_number" class="form-label fw-semibold">Nº de Colegiado</label>
          <input type="text" class="form-control" id="license_number" name="license_number" value="<?= esc(old('license_number', $user->license_number ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label for="new_password" class="form-label fw-semibold">Nueva contraseña (opcional)</label>
          <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
        </div>

        <div class="col-md-6">
          <label for="new_password_confirm" class="form-label fw-semibold">Confirmar nueva contraseña</label>
          <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm" minlength="6">
        </div>

        <div class="col-12 pt-1">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1" <?= $user->active ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="active">Cuenta activa</label>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="<?= base_url('users') ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Cambios
        </button>
        <?php if ((int) $user->id !== (int) auth()->id()): ?>
          <button type="button" id="deleteUserBtn" class="btn btn-sm btn-danger">
            <i class="ti ti-trash me-1"></i> Eliminar
          </button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ((int) $user->id !== (int) auth()->id()): ?>
      <form id="deleteUserForm" action="<?= base_url('users/delete/' . (int) $user->id) ?>" method="post" class="d-none">
        <?= csrf_field() ?>
      </form>
    <?php endif; ?>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const roleCol = document.getElementById('roleCol');
    const doctorFields = document.querySelectorAll('.doctor-only-field');
    if (!roleSelect || !doctorFields.length) return;

    function toggleDoctorFields() {
      const isMedico = roleSelect.value === 'medico';
      if (roleCol) {
        roleCol.classList.toggle('col-md-4', isMedico);
        roleCol.classList.toggle('col-12', !isMedico);
      }
      doctorFields.forEach(function(el) {
        el.classList.toggle('d-none', !isMedico);
      });
    }

    roleSelect.addEventListener('change', toggleDoctorFields);
    toggleDoctorFields();
  });

  const deleteUserBtn  = document.getElementById('deleteUserBtn');
  const deleteUserForm = document.getElementById('deleteUserForm');
  if (deleteUserBtn && deleteUserForm) {
    deleteUserBtn.addEventListener('click', function() {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar usuario?',
          text: 'Se eliminará este usuario de forma permanente.',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
          customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-outline-secondary'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            deleteUserForm.submit();
          }
        });
      } else if (confirm('¿Eliminar este usuario de forma permanente?')) {
        deleteUserForm.submit();
      }
    });
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
