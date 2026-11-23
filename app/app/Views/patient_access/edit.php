<?= view('template/header') ?>

<?php
  $expiresDateVal = ! empty($link['expires_at']) ? date('Y-m-d', strtotime((string) $link['expires_at'])) : date('Y-m-d', strtotime('+30 days'));
  $isLocked       = ! empty($link['locked_at']);
?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Editar acceso de paciente</h5>
    <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <p class="text-muted mb-4">
      Paciente: <strong><?= esc(format_patient_name($study['patient_name'] ?? null, 'Estudio DICOM')) ?></strong>
    </p>

    <form action="<?= base_url('studies/patient-access/' . (int) $link['id'] . '/update') ?>" method="post" id="editPatientLinkForm">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="expires_date" class="form-label fw-semibold">Fecha de caducidad <span class="text-danger">*</span></label>
          <input type="text" class="form-control datepicker-input" id="expires_date" name="expires_date" required placeholder="dd/mm/aaaa" autocomplete="off" value="<?= esc(format_date(old('expires_date', $expiresDateVal), '')) ?>">
        </div>

        <div class="col-md-6">
          <label for="patient_email" class="form-label fw-semibold">Correo electrónico del paciente (opcional)</label>
          <input type="email" class="form-control" id="patient_email" name="patient_email" maxlength="190" placeholder="paciente@correo.com" value="<?= esc(old('patient_email', (string) ($link['patient_email'] ?? ''))) ?>">
        </div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="allow_download" name="allow_download" value="1" <?= old('allow_download', (int) ($link['allow_download'] ?? 0) === 1 ? '1' : '') ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="allow_download">
              Permitir descarga DICOM (.zip)
            </label>
          </div>
        </div>

        <?php if ($isLocked): ?>
          <div class="col-12">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="unlock_link" name="unlock_link" value="1" checked>
              <label class="form-check-label fw-semibold text-warning" for="unlock_link">
                Desbloquear enlace (reiniciar intentos fallidos)
              </label>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary" id="editPatientLinkBtn">
          <i class="ti ti-device-floppy me-1"></i> Guardar cambios
        </button>
      </div>
    </form>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editPatientLinkForm');
    const btn  = document.getElementById('editPatientLinkBtn');
    if (!form || !btn) return;

    form.addEventListener('submit', function(e) {
      if (!form.checkValidity()) return;
      if (btn.disabled) {
        e.preventDefault();
        return;
      }
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Guardando...';
    });
  });
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
