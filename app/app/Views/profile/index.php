<?= view('template/header') ?>

<div class="card mb-4">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Datos personales y firma médica</h5>
  </div>

  <div class="card-body">
    <form action="<?= base_url('profile') ?>" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label for="username" class="form-label fw-semibold">Usuario</label>
          <input type="text" class="form-control" id="username" name="username" value="<?= esc(old('username', $user->username)) ?>" minlength="3" maxlength="30" required>
        </div>
        <div class="col-md-6">
          <label for="email" class="form-label fw-semibold">Correo electrónico</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= esc(old('email', $user->email)) ?>" required>
        </div>
        <div class="col-md-12">
          <label for="full_name" class="form-label fw-semibold">Nombre completo</label>
          <input type="text" class="form-control" id="full_name" name="full_name" placeholder="Ej: Dra. Laura Martínez" value="<?= esc(old('full_name', $user->full_name ?? '')) ?>">
        </div>

        <?php if ($user->can('reports.review')): ?>
          <div class="col-md-6">
            <label for="specialty" class="form-label fw-semibold">Especialidad</label>
            <input type="text" class="form-control" id="specialty" name="specialty" placeholder="Ej: Radiodiagnóstico" value="<?= esc(old('specialty', $user->specialty ?? '')) ?>">
          </div>
          <div class="col-md-6">
            <label for="license_number" class="form-label fw-semibold">Nº de Colegiado</label>
            <input type="text" class="form-control" id="license_number" name="license_number" placeholder="Ej: COL-284512" value="<?= esc(old('license_number', $user->license_number ?? '')) ?>">
          </div>

          <div class="col-12 mt-3">
            <label class="form-label fw-semibold">Firma digital para informes PDF (opcional)</label>
            <div class="dropzone-modern-wrapper p-4 rounded-3 border border-2 border-dashed border-secondary-subtle text-center bg-body-tertiary">
              <input type="file" id="signatureInput" name="signature" class="visually-hidden" accept=".png,.jpg,.jpeg">
              <?php if (! empty($user->signature_file)): ?>
                <div class="mb-3">
                  <img src="<?= base_url('profile/signature') ?>?t=<?= time() ?>" alt="Firma actual" class="border rounded bg-white p-2" style="max-height: 90px;">
                </div>
              <?php endif; ?>
              <h6 class="fw-semibold mb-1" id="sigLabel">Sube tu firma en PNG o JPG (fondo blanco o transparente)</h6>
              <span class="text-muted small d-block mb-3">Máximo 2 MB. Aparecerá en el pie de los informes médicos PDF.</span>
              <label for="signatureInput" class="btn btn-sm btn-outline-primary px-3 mb-0" style="cursor: pointer;">
                <i class="ti ti-signature me-1"></i> Seleccionar Firma
              </label>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-center mt-4">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Perfil
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Cambiar contraseña</h5>
  </div>

  <div class="card-body">
    <form action="<?= base_url('profile/password') ?>" method="post">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label for="current_password" class="form-label fw-semibold">Contraseña actual</label>
          <input type="password" class="form-control" id="current_password" name="current_password" required>
        </div>
        <div class="col-md-4">
          <label for="new_password" class="form-label fw-semibold">Nueva contraseña</label>
          <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
        </div>
        <div class="col-md-4">
          <label for="confirm_password" class="form-label fw-semibold">Confirmar contraseña</label>
          <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
      </div>

      <div class="d-flex justify-content-center mt-4">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-lock me-1"></i> Actualizar Contraseña
        </button>
      </div>
    </form>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  const sigInput = document.getElementById('signatureInput');
  const sigLabel = document.getElementById('sigLabel');
  if (sigInput && sigLabel) {
    sigInput.addEventListener('change', function() {
      if (sigInput.files && sigInput.files[0]) {
        sigLabel.textContent = 'Firma seleccionada: ' + sigInput.files[0].name;
      }
    });
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
