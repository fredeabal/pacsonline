<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Subir estudio DICOM</h5>
    <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <form action="<?= base_url('studies/upload') ?>" method="post" enctype="multipart/form-data" id="dicomUploadForm" novalidate>
      <?= csrf_field() ?>

      <div class="dropzone-modern-wrapper p-5 rounded-3 border border-2 border-dashed border-secondary-subtle text-center position-relative bg-body-tertiary mb-4">
        <input type="file" id="dicom_file" name="dicom_file" class="visually-hidden" accept=".dcm,.dicom,.zip" required>

        <div id="dicomDropzonePrompt">
          <div class="round-50 rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3 shadow-sm">
            <i class="ti ti-cloud-upload fs-7"></i>
          </div>
          <h6 class="fw-semibold mb-1">Arrastra tu estudio DICOM o haz clic para examinar</h6>
          <span class="text-muted small d-block mb-3">Formatos permitidos: .dcm, .dicom o archivo comprimido .zip (Máx. 512 MB)</span>
          <label for="dicom_file" class="btn btn-sm btn-outline-primary px-3 mb-0" style="cursor: pointer;">
            <i class="ti ti-folder-open me-1"></i> Seleccionar Archivo
          </label>
        </div>

        <div id="dicomDropzonePreview" class="d-none">
          <div class="round-50 rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3 shadow-sm">
            <i class="ti ti-file-check fs-7"></i>
          </div>
          <h6 class="fw-semibold mb-1" id="dicomFileName">archivo.dcm</h6>
          <span class="text-muted small d-block mb-3" id="dicomFileSize">0 MB</span>
          <label for="dicom_file" class="btn btn-sm btn-outline-primary px-3 mb-0" style="cursor: pointer;">
            <i class="ti ti-refresh me-1"></i> Cambiar Archivo
          </label>
        </div>
      </div>

      <hr class="my-4">

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label for="patient_user_id" class="form-label fw-semibold">Asignar a paciente existente (opcional)</label>
          <select class="form-select" id="patient_user_id" name="patient_user_id">
            <option value="">-- Sin asignar --</option>
            <?php foreach ($patients as $pid => $pUser): ?>
              <option value="<?= (int) $pid ?>" <?= old('patient_user_id') == $pid ? 'selected' : '' ?>>
                <?= esc($pUser->full_name ?: $pUser->username) ?> (ID: <?= (int) $pid ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label for="center_id" class="form-label fw-semibold">Centro médico remitente (opcional)</label>
          <select class="form-select" id="center_id" name="center_id">
            <option value="">-- Subida directa / Sin centro --</option>
            <?php foreach ($centers as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= old('center_id') == $c['id'] ? 'selected' : '' ?>>
                <?= esc($c['name']) ?> (<?= esc($c['ae_title']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label for="assigned_doctor_id" class="form-label fw-semibold">Médico informante (opcional)</label>
          <select class="form-select" id="assigned_doctor_id" name="assigned_doctor_id">
            <option value="">-- Cualquier médico --</option>
            <?php foreach ($doctors as $did => $dUser): ?>
              <option value="<?= (int) $did ?>" <?= old('assigned_doctor_id') == $did ? 'selected' : '' ?>>
                <?= esc($dUser->full_name ?: $dUser->username) ?> (ID: <?= (int) $did ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label for="priority" class="form-label fw-semibold">Prioridad</label>
          <select class="form-select" id="priority" name="priority">
            <option value="normal" <?= old('priority', 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="urgent" <?= old('priority') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
          </select>
        </div>
      </div>

      <div class="mb-4 p-3 rounded-3 border bg-body-tertiary">
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="toggleNewPatient">
          <label class="form-check-label fw-semibold" for="toggleNewPatient">Crear cuenta de paciente nuevo al subir</label>
        </div>
        <div class="row g-3 mt-1 d-none" id="newPatientFields">
          <div class="col-md-6">
            <label for="new_patient_name" class="form-label fw-semibold">Nombre completo del paciente</label>
            <input type="text" class="form-control" id="new_patient_name" name="new_patient_name" placeholder="Ej: Juan Pérez" value="<?= esc(old('new_patient_name')) ?>">
          </div>
          <div class="col-md-6">
            <label for="new_patient_email" class="form-label fw-semibold">Correo del paciente</label>
            <input type="email" class="form-control" id="new_patient_email" name="new_patient_email" placeholder="paciente@correo.com" value="<?= esc(old('new_patient_email')) ?>">
            <div class="form-text">Se generará una contraseña temporal automáticamente.</div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2">
        <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary" id="dicomSubmitBtn">
          <i class="ti ti-cloud-upload me-1"></i> Subir al PACS
        </button>
      </div>
    </form>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  (function() {
    const dropzone = document.querySelector('.dropzone-modern-wrapper');
    const input    = document.getElementById('dicom_file');
    const promptEl = document.getElementById('dicomDropzonePrompt');
    const prevEl   = document.getElementById('dicomDropzonePreview');
    const nameEl   = document.getElementById('dicomFileName');
    const sizeEl   = document.getElementById('dicomFileSize');

    function showFile(file) {
      if (!file) return;
      nameEl.textContent = file.name;
      sizeEl.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
      promptEl.classList.add('d-none');
      prevEl.classList.remove('d-none');
    }

    if (input) {
      input.addEventListener('change', function() {
        if (input.files && input.files[0]) showFile(input.files[0]);
      });
    }

    if (dropzone && input) {
      ['dragenter', 'dragover'].forEach(function(evt) {
        dropzone.addEventListener(evt, function(e) {
          e.preventDefault(); e.stopPropagation();
          dropzone.classList.add('border-primary', 'bg-primary-subtle');
        });
      });
      ['dragleave', 'drop'].forEach(function(evt) {
        dropzone.addEventListener(evt, function(e) {
          e.preventDefault(); e.stopPropagation();
          dropzone.classList.remove('border-primary', 'bg-primary-subtle');
        });
      });
      dropzone.addEventListener('drop', function(e) {
        if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
          input.files = e.dataTransfer.files;
          showFile(e.dataTransfer.files[0]);
        }
      });
    }

    const toggleNew = document.getElementById('toggleNewPatient');
    const newFields = document.getElementById('newPatientFields');
    if (toggleNew && newFields) {
      toggleNew.addEventListener('change', function() {
        newFields.classList.toggle('d-none', !toggleNew.checked);
      });
    }

    const uploadForm = document.getElementById('dicomUploadForm');
    const submitBtn  = document.getElementById('dicomSubmitBtn');
    if (uploadForm && submitBtn) {
      uploadForm.addEventListener('submit', function(e) {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        const swalColors = {
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#fff' : '#0b1114'
        };

        if (!input || !input.files || input.files.length === 0) {
          e.preventDefault();
          if (typeof Swal !== 'undefined') {
            Swal.fire({
              icon: 'warning',
              title: 'Archivo requerido',
              text: 'Selecciona un estudio DICOM o ZIP.',
              showConfirmButton: false,
              timer: 3500,
              timerProgressBar: true,
              ...swalColors
            });
          } else {
            alert('Selecciona un estudio DICOM o ZIP.');
          }
          return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Subiendo...';

        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: 'Subiendo estudio...',
            text: 'Procesando e importando imágenes al PACS.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            ...swalColors,
            didOpen: function() {
              Swal.showLoading();
            }
          });
        }
      });
    }
  })();
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
