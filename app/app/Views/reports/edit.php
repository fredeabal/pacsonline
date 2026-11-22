<?= view('template/header') ?>

<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0 text-primary fw-semibold">Datos del estudio</h5>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= ohif_url($studyUid) ?>" target="_blank" class="btn btn-sm btn-primary">
        <i class="ti ti-eye me-1"></i> Abrir Imágenes en OHIF
      </a>
      <a href="<?= base_url('studies/case/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary">
        <i class="ti ti-paperclip me-1"></i> Ficha del Caso
      </a>
      <a href="<?= base_url('reports') ?>" class="btn btn-sm btn-outline-primary">
        <i class="ti ti-arrow-left me-1"></i> Volver
      </a>
    </div>
  </div>
  <?php
    $modality = $study['modality'] ?? extract_study_modality($study ?? []);
    $priority = $study['priority'] ?? 'normal';
    $summaryBox = static function (string $icon, string $label, string $value): string {
        return '<div class="p-3 rounded-2 border border-primary-subtle h-100">'
            . '<span class="text-primary text-uppercase fw-semibold d-flex align-items-center gap-1 mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">'
            . '<i class="ti ti-' . esc($icon, 'attr') . '"></i> ' . esc($label) . '</span>'
            . '<span class="fw-semibold text-body fs-3 text-uppercase text-truncate d-block" title="' . esc($value, 'attr') . '">' . esc($value) . '</span>'
            . '</div>';
    };
    $sectionLabelStyle = 'font-size: 0.7rem; letter-spacing: 0.05em;';
  ?>
  <div class="card-body">
    <div class="row g-3">
      <!-- Paciente -->
      <div class="col-12 col-lg-5">
        <div class="text-muted text-uppercase fw-semibold mb-2" style="<?= $sectionLabelStyle ?>">Paciente</div>
        <div class="row g-3">
          <div class="col-12"><?= $summaryBox('user', 'Paciente', format_patient_name($study['patient_name'] ?? null)) ?></div>
          <div class="col-6"><?= $summaryBox('id', 'ID Paciente', (string) ($study['patient_id'] ?? '-')) ?></div>
          <div class="col-6"><?= $summaryBox('cake', 'Edad', (string) ($study['patient_age'] ?? '-')) ?></div>
        </div>
      </div>

      <!-- Estudio -->
      <div class="col-12 col-lg-7">
        <div class="text-muted text-uppercase fw-semibold mb-2" style="<?= $sectionLabelStyle ?>">Estudio</div>
        <div class="row g-3">
          <div class="col-6"><?= $summaryBox('hash', 'Nº Caso', (string) ($study['study_number'] ?? '-')) ?></div>
          <div class="col-6"><?= $summaryBox('calendar', 'Fecha', format_date($study['study_date'] ?? null)) ?></div>
          <div class="col-12"><?= $summaryBox('building-hospital', 'Centro', (string) ($study['center_name'] ?? '-')) ?></div>
        </div>
      </div>

      <!-- Descripción y prioridad -->
      <div class="col-12">
        <div class="row g-3">
          <div class="col-12 col-md-8 col-lg-9">
            <?= $summaryBox('file-description', 'Descripción', trim($modality . ' ' . format_study_description($study['study_description'] ?? null))) ?>
          </div>
          <div class="col-12 col-md-4 col-lg-3">
            <div class="p-3 rounded-2 border border-primary-subtle h-100">
              <span class="text-primary text-uppercase fw-semibold d-flex align-items-center gap-1 mb-1" style="<?= $sectionLabelStyle ?>">
                <i class="ti ti-flag"></i> Prioridad
              </span>
              <div>
                <?php if ($priority === 'urgent'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold text-uppercase" style="min-width: 84px;">
                    <i class="ti ti-alert-triangle me-1"></i>Urgente
                  </span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle fs-2 fw-semibold text-uppercase" style="min-width: 84px;">Normal</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php if (! empty($study['clinical_notes'])): ?>
      <hr class="my-3">
      <div>
        <span class="text-muted small fw-semibold d-block mb-1"><i class="ti ti-notes me-1"></i> Motivo de consulta del paciente:</span>
        <p class="mb-0 text-body small"><?= nl2br(esc($study['clinical_notes'])) ?></p>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex align-items-center justify-content-between gap-2">
    <h5 class="mb-0 text-primary fw-semibold">Editor de informe médico</h5>
    <button type="button" id="voiceBtn" class="btn btn-sm btn-primary text-nowrap">
      <i class="ti ti-microphone me-1"></i> Dictar por Voz
    </button>
  </div>
  <div class="card-body">
    <form action="<?= base_url('reports/save/' . urlencode($studyUid)) ?>" method="post">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label for="templateSelect" class="form-label fw-semibold">Aplicar plantilla personal (opcional)</label>
        <div class="input-group flex-nowrap">
          <div class="flex-grow-1 min-w-0">
            <select id="templateSelect" class="form-select">
              <option value="">-- Seleccionar plantilla --</option>
              <?php foreach ($templates as $tpl): ?>
                <option
                  value="<?= (int) $tpl['id'] ?>"
                  data-findings="<?= esc($tpl['findings'], 'attr') ?>"
                  data-impression="<?= esc($tpl['impression'], 'attr') ?>">
                  [<?= esc($tpl['modality']) ?>] <?= esc($tpl['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <a href="<?= base_url('templates') ?>" class="btn bg-transparent border text-body d-flex align-items-center justify-content-center" title="Gestionar mis plantillas">
            <i class="ti ti-template fs-5"></i>
          </a>
        </div>
      </div>

      <div class="mb-3">
        <label for="clinical_history" class="form-label fw-semibold">Antecedentes / Motivo Clínico (opcional)</label>
        <textarea class="form-control" id="clinical_history" name="clinical_history" rows="2" placeholder="Motivo de consulta o datos clínicos relevantes..."><?= esc(old('clinical_history', $report['clinical_history'] ?? ($study['clinical_notes'] ?? ''))) ?></textarea>
      </div>

      <div class="mb-3">
        <label for="findings" class="form-label fw-semibold">Hallazgos</label>
        <textarea class="form-control" id="findings" name="findings" rows="7" required placeholder="Describe los hallazgos radiológicos..."><?= esc(old('findings', $report['findings'] ?? '')) ?></textarea>
      </div>

      <div class="mb-4">
        <label for="impression" class="form-label fw-semibold">Conclusión / Impresión Diagnóstica</label>
        <textarea class="form-control" id="impression" name="impression" rows="3" required placeholder="Conclusión diagnóstica..."><?= esc(old('impression', $report['impression'] ?? '')) ?></textarea>
      </div>

      <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="<?= base_url('reports') ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" name="action_type" value="draft" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Borrador
        </button>
        <button type="submit" name="action_type" value="publish" class="btn btn-sm btn-success">
          <i class="ti ti-check me-1"></i> Firmar y Publicar Informe
        </button>
        <?php
          $currentUser     = auth()->user();
          $canDeleteReport = $report !== null && $currentUser !== null && (
              $currentUser->can('users.manage')
              || ($currentUser->can('reports.review') && (int) ($report['doctor_user_id'] ?? 0) === (int) $currentUser->id)
          );
        ?>
        <?php if ($canDeleteReport): ?>
          <button type="button" id="deleteReportBtn" class="btn btn-sm btn-danger">
            <i class="ti ti-trash me-1"></i> Eliminar Informe
          </button>
        <?php endif; ?>
      </div>
    </form>

    <?php if (! empty($canDeleteReport)): ?>
      <form id="deleteReportForm" action="<?= base_url('reports/delete/' . urlencode($studyUid)) ?>" method="post" class="d-none">
        <?= csrf_field() ?>
      </form>
    <?php endif; ?>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  const deleteReportBtn  = document.getElementById('deleteReportBtn');
  const deleteReportForm = document.getElementById('deleteReportForm');
  if (deleteReportBtn && deleteReportForm) {
    deleteReportBtn.addEventListener('click', function() {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar informe?',
          text: 'El estudio volverá a estado Pendiente.',
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
            deleteReportForm.submit();
          }
        });
      } else if (confirm('¿Eliminar este informe?')) {
        deleteReportForm.submit();
      }
    });
  }

  const templateSelect = document.getElementById('templateSelect');
  if (templateSelect) {
    templateSelect.addEventListener('change', function() {
      const opt = templateSelect.options[templateSelect.selectedIndex];
      if (!opt || !opt.value) return;
      document.getElementById('findings').value   = opt.getAttribute('data-findings') || '';
      document.getElementById('impression').value = opt.getAttribute('data-impression') || '';
    });
  }

  const voiceBtn = document.getElementById('voiceBtn');
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  if (!SpeechRecognition) {
    voiceBtn.disabled = true;
    voiceBtn.title = 'Tu navegador no soporta dictado por voz nativo (usa Chrome o Edge).';
  } else {
    const recognition = new SpeechRecognition();
    recognition.lang = 'es-ES';
    recognition.continuous = true;
    recognition.interimResults = false;
    let listening = false;
    let activeField = document.getElementById('findings');

    ['clinical_history', 'findings', 'impression'].forEach(function(id) {
      document.getElementById(id).addEventListener('focus', function() {
        activeField = this;
      });
    });

    voiceBtn.addEventListener('click', function() {
      if (listening) {
        recognition.stop();
        listening = false;
        voiceBtn.classList.remove('btn-danger');
        voiceBtn.classList.add('btn-primary');
        voiceBtn.innerHTML = '<i class="ti ti-microphone me-1"></i> Dictar por Voz';
      } else {
        recognition.start();
        listening = true;
        voiceBtn.classList.remove('btn-primary');
        voiceBtn.classList.add('btn-danger');
        voiceBtn.innerHTML = '<i class="ti ti-player-stop me-1"></i> Detener Dictado';
      }
    });

    recognition.onresult = function(event) {
      const text = event.results[event.results.length - 1][0].transcript.trim();
      if (text) {
        activeField.value = (activeField.value ? activeField.value + ' ' : '') + text;
      }
    };
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
