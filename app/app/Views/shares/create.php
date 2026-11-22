<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Generar enlace seguro</h5>
    <a href="<?= base_url('studies/share/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <p class="text-muted mb-4">
      Paciente: <strong><?= esc(format_patient_name($study['patient_name'] ?? null, 'Estudio DICOM')) ?></strong> ·
      Estudio: <strong><?= esc($study['study_description'] ?? 'Estudio DICOM') ?></strong>
    </p>

    <form action="<?= base_url('studies/share') ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="study_instance_uid" value="<?= esc($studyUid) ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label for="recipient_label" class="form-label fw-semibold">Médico / Destinatario (opcional)</label>
          <input type="text" class="form-control" id="recipient_label" name="recipient_label" placeholder="Ej: Dr. García — Traumatología" value="<?= old('recipient_label') ?>">
        </div>

        <div class="col-md-4">
          <label for="pin" class="form-label fw-semibold">Código PIN de acceso (opcional)</label>
          <input type="text" class="form-control" id="pin" name="pin" minlength="4" maxlength="12" placeholder="Ej: 4829 (mín. 4 caracteres)">
        </div>

        <div class="col-md-4">
          <label for="expires_days" class="form-label fw-semibold">Caducidad del enlace</label>
          <select class="form-select" id="expires_days" name="expires_days">
            <option value="1">24 horas</option>
            <option value="7" selected>7 días</option>
            <option value="30">30 días</option>
            <option value="0">Sin caducidad</option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="<?= base_url('studies/share/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-link me-1"></i> Crear Enlace de Acceso
        </button>
      </div>
    </form>
  </div>
</div>

<?= view('template/footer') ?>
