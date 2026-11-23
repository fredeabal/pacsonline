<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Generar acceso seguro para paciente</h5>
    <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <p class="text-muted mb-3">
      Paciente: <strong><?= esc(format_patient_name($study['patient_name'] ?? null, 'Estudio DICOM')) ?></strong> ·
      Estudio: <strong><?= esc(format_study_description($study['study_description'] ?? null, 'Estudio DICOM')) ?></strong>
    </p>

    <?php if ($dicomPatientId === '' || $dicomBirthDate === ''): ?>
      <div class="alert alert-warning py-2 px-3 fs-3 mb-4">
        <i class="ti ti-alert-triangle me-1"></i>
        Algunos datos de identificación no venían completos en las etiquetas DICOM del estudio. Confírmalos o complétalos abajo para que el paciente pueda verificar su identidad al abrir el enlace.
      </div>
    <?php endif; ?>

    <form action="<?= base_url('studies/patient-access') ?>" method="post" id="createPatientLinkForm">
      <?= csrf_field() ?>
      <input type="hidden" name="study_instance_uid" value="<?= esc($studyUid) ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label for="patient_id" class="form-label fw-semibold">Número de documento (DNI / NIE / Pasaporte) <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="patient_id" name="patient_id" required maxlength="64" placeholder="Ej: 12345678A" value="<?= esc(old('patient_id', $dicomPatientId)) ?>">
          <div class="form-text">Se normaliza automáticamente (sin espacios ni guiones) y se guarda cifrado.</div>
        </div>

        <div class="col-md-4">
          <label for="patient_birth_date" class="form-label fw-semibold">Fecha de nacimiento <span class="text-danger">*</span></label>
          <input type="date" class="form-control" id="patient_birth_date" name="patient_birth_date" required value="<?= esc(old('patient_birth_date', $dicomBirthDate)) ?>">
          <div class="form-text">El paciente deberá introducir exactamente esta fecha junto con su documento.</div>
        </div>

        <div class="col-md-4">
          <label for="expires_days" class="form-label fw-semibold">Caducidad del enlace</label>
          <select class="form-select" id="expires_days" name="expires_days">
            <option value="7" <?= old('expires_days') === '7' ? 'selected' : '' ?>>7 días</option>
            <option value="15" <?= old('expires_days') === '15' ? 'selected' : '' ?>>15 días</option>
            <option value="30" <?= old('expires_days', '30') === '30' ? 'selected' : '' ?>>30 días (recomendado)</option>
            <option value="60" <?= old('expires_days') === '60' ? 'selected' : '' ?>>60 días</option>
          </select>
        </div>

        <div class="col-md-6">
          <label for="patient_email" class="form-label fw-semibold">Enviar enlace por correo electrónico (opcional)</label>
          <input type="email" class="form-control" id="patient_email" name="patient_email" maxlength="190" placeholder="paciente@correo.com" value="<?= esc(old('patient_email', '')) ?>">
          <div class="form-text">
            <?php if (! empty($smtpEnabled)): ?>
              Se enviará solo el enlace seguro (sin adjuntar el PDF ni datos clínicos, conforme al RGPD).
            <?php else: ?>
              SMTP no está activado en Ajustes; podrás copiar el enlace, mostrar el QR o enviarlo por WhatsApp.
            <?php endif; ?>
          </div>
        </div>

        <div class="col-md-6 d-flex align-items-center">
          <div class="form-check form-switch mt-md-3">
            <input class="form-check-input" type="checkbox" role="switch" id="allow_download" name="allow_download" value="1" <?= old('allow_download', '1') ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="allow_download">
              Permitir descarga del estudio DICOM (.zip)
            </label>
            <div class="form-text">El paciente siempre podrá abrir el visor OHIF y descargar su informe PDF cuando esté firmado.</div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-shield-check me-1"></i> Generar Enlace para Paciente
        </button>
      </div>
    </form>
  </div>
</div>

<?= view('template/footer') ?>
