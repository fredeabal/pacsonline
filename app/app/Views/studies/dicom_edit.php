<?= view('template/header', ['title' => $title ?? 'Editar datos DICOM | PACS Online']) ?>

<?php /** Página dedicada para editar las etiquetas DICOM del paciente y del estudio. */ ?>
<?php
  $patientId   = old('patient_id', (string) ($dicom['patient_id'] ?? ''));
  $patientName = old('patient_name', format_patient_name($dicom['patient_name'] ?? null, ''));
  $birthDate   = old('patient_birth_date', (string) ($dicom['patient_birth_date'] ?? ''));
  $sex         = strtoupper(trim((string) old('patient_sex', (string) ($dicom['patient_sex'] ?? ''))));
  $studyDesc   = old('study_description', (string) ($dicom['study_description'] ?? ''));
  $studyDate   = old('study_date', (string) ($dicom['study_date'] ?? ''));
  $accession   = old('accession_number', (string) ($dicom['accession_number'] ?? ''));
  $refPhysician = old('referring_physician_name', format_patient_name($dicom['referring_physician_name'] ?? null, ''));
  $institution = old('institution_name', (string) ($dicom['institution_name'] ?? ''));
?>
<div class="card">
  <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0 text-primary fw-semibold">
      <i class="ti ti-edit me-1"></i> Editar datos DICOM
    </h5>
    <a href="<?= base_url('studies/case/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
      <i class="ti ti-arrow-left"></i> Volver al caso
    </a>
  </div>

  <form action="<?= base_url('studies/case/' . urlencode($studyUid) . '/patient-dicom') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="orthanc_id" value="<?= esc($orthancId) ?>">

    <div class="card-body">
      <div class="alert alert-warning small mb-4">
        <i class="ti ti-alert-triangle me-1"></i>
        Esta acción reescribe las etiquetas DICOM del estudio en el PACS y queda registrada en auditoría. Si cambia el <strong>ID de paciente</strong>, el PACS recalculará el ID interno del estudio.
      </div>

      <div class="row g-4">
        <div class="col-12 col-lg-6">
          <div class="border rounded p-3 h-100">
            <h6 class="text-uppercase text-muted fw-semibold mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
              <i class="ti ti-user me-1"></i> Datos del paciente
            </h6>
            <div class="mb-3">
              <label for="dicom_patient_name" class="form-label fw-semibold">Nombre del paciente</label>
              <input type="text" class="form-control" id="dicom_patient_name" name="patient_name" value="<?= esc($patientName) ?>" maxlength="120" required>
            </div>
            <div class="mb-3">
              <label for="dicom_patient_id" class="form-label fw-semibold">ID del paciente</label>
              <input type="text" class="form-control" id="dicom_patient_id" name="patient_id" value="<?= esc($patientId === '-' ? '' : $patientId) ?>" maxlength="64" required>
            </div>
            <div class="mb-3">
              <label for="dicom_patient_birth_date" class="form-label fw-semibold">Fecha de nacimiento</label>
              <input type="text" class="form-control datepicker-input" id="dicom_patient_birth_date" name="patient_birth_date" value="<?= esc(format_date($birthDate, '')) ?>" placeholder="dd/mm/aaaa" autocomplete="off">
            </div>
            <div>
              <label for="dicom_patient_sex" class="form-label fw-semibold">Sexo</label>
              <select class="form-select" id="dicom_patient_sex" name="patient_sex">
                <option value="" <?= $sex === '' ? 'selected' : '' ?>>Sin especificar</option>
                <option value="M" <?= $sex === 'M' ? 'selected' : '' ?>>Masculino</option>
                <option value="F" <?= $sex === 'F' ? 'selected' : '' ?>>Femenino</option>
                <option value="O" <?= $sex === 'O' ? 'selected' : '' ?>>Otro</option>
              </select>
            </div>
          </div>
        </div>

        <div class="col-12 col-lg-6">
          <div class="border rounded p-3 h-100">
            <h6 class="text-uppercase text-muted fw-semibold mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
              <i class="ti ti-file-description me-1"></i> Datos del estudio
            </h6>
            <div class="mb-3">
              <label for="dicom_study_description" class="form-label fw-semibold">Descripción del estudio</label>
              <input type="text" class="form-control" id="dicom_study_description" name="study_description" value="<?= esc($studyDesc) ?>" maxlength="160" placeholder="Ej. COLUMNA LUMBOSACRA, AP Y LAT.">
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label for="dicom_study_date" class="form-label fw-semibold">Fecha del estudio</label>
                <input type="text" class="form-control datepicker-input" id="dicom_study_date" name="study_date" value="<?= esc(format_date($studyDate, '')) ?>" placeholder="dd/mm/aaaa" autocomplete="off">
              </div>
              <div class="col-6">
                <label for="dicom_accession_number" class="form-label fw-semibold">Número de acceso</label>
                <input type="text" class="form-control" id="dicom_accession_number" name="accession_number" value="<?= esc($accession) ?>" maxlength="64">
              </div>
            </div>
            <div class="mb-3">
              <label for="dicom_referring_physician_name" class="form-label fw-semibold">Médico solicitante</label>
              <input type="text" class="form-control" id="dicom_referring_physician_name" name="referring_physician_name" value="<?= esc($refPhysician) ?>" maxlength="120">
            </div>
            <div>
              <label for="dicom_institution_name" class="form-label fw-semibold">Centro / Institución</label>
              <?php
                $centersList     = $centers ?? [];
                $matchedInList   = false;
                foreach ($centersList as $c) {
                    if (mb_strtolower(trim((string) $c['name']), 'UTF-8') === mb_strtolower(trim((string) $institution), 'UTF-8')) {
                        $matchedInList = true;
                        break;
                    }
                }
              ?>
              <select class="form-select" id="dicom_institution_name" name="institution_name">
                <option value="">-- Sin centro especificado --</option>
                <?php if (trim((string) $institution) !== '' && ! $matchedInList): ?>
                  <option value="<?= esc($institution, 'attr') ?>" selected>
                    <?= esc($institution) ?> (Actual en DICOM)
                  </option>
                <?php endif; ?>
                <?php foreach ($centersList as $c): ?>
                  <option value="<?= esc($c['name'], 'attr') ?>" <?= mb_strtolower(trim((string) $c['name']), 'UTF-8') === mb_strtolower(trim((string) $institution), 'UTF-8') ? 'selected' : '' ?>>
                    <?= esc($c['name']) ?> (<?= esc($c['ae_title']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card-footer d-flex justify-content-center gap-2">
      <a href="<?= base_url('studies/case/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-dark">Cancelar</a>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="ti ti-device-floppy me-1"></i> Guardar cambios DICOM
      </button>
    </div>
  </form>
</div>

<?= view('template/footer') ?>
