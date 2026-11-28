<?= view('template/header') ?>
<link rel="stylesheet" href="<?= base_url('assets/libs/quill/dist/quill.snow.css') ?>">
<style>
  /* Etiquetas en español para el selector de encabezado de Quill */
  .ql-snow .ql-picker.ql-header .ql-picker-label::before,
  .ql-snow .ql-picker.ql-header .ql-picker-item::before { content: 'Normal'; }
  .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="1"]::before,
  .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="1"]::before { content: 'Título 1'; }
  .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="2"]::before,
  .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="2"]::before { content: 'Título 2'; }
  .ql-snow .ql-picker.ql-header .ql-picker-label[data-value="3"]::before,
  .ql-snow .ql-picker.ql-header .ql-picker-item[data-value="3"]::before { content: 'Título 3'; }
  .report-quill-wrapper { height: auto; overflow: visible; }
  .report-quill-wrapper .ql-container { height: auto !important; }
  .report-quill-wrapper .ql-editor { min-height: 360px; font-size: 0.96rem; line-height: 1.6; }
</style>

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

<?= view('studies/_priors_card', [
    'studyUid'       => $studyUid,
    'priorStudies'   => $priorStudies ?? [],
    'isReportEditor' => true,
]) ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex flex-wrap align-items-center justify-content-between gap-2">
    <h5 class="mb-0 text-primary fw-semibold">Editor de informe médico</h5>
    <div class="d-flex align-items-center gap-2">
      <span id="voiceStatus" class="small text-muted d-none"></span>
      <button type="button" id="voiceBtn" class="btn btn-sm btn-primary text-nowrap" title="Dictar por voz (Alt+M)">
        <i class="ti ti-microphone me-1"></i> Dictar por Voz
      </button>
    </div>
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

      <div class="mb-4 report-quill-wrapper">
        <label class="form-label fw-semibold">Contenido del Informe</label>
        <div id="report_editor"></div>
        <textarea class="d-none" id="clinical_history" name="clinical_history"><?= esc(old('clinical_history', $report['clinical_history'] ?? ($study['clinical_notes'] ?? ''))) ?></textarea>
        <textarea class="d-none" id="findings" name="findings"><?= esc(old('findings', $report['findings'] ?? '')) ?></textarea>
        <textarea class="d-none" id="impression" name="impression"><?= esc(old('impression', $report['impression'] ?? '')) ?></textarea>
      </div>

      <div class="d-flex flex-wrap justify-content-center gap-2 position-relative" style="z-index: 5;">
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
<script src="<?= base_url('assets/libs/quill/dist/quill.min.js') ?>" {csp-script-nonce}></script>
<script src="<?= base_url('assets/js/report-editor-voice.js') ?>" {csp-script-nonce}></script>
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

  const voiceHelpers = window.ReportVoiceHelpers;
  const quillToolbar = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ align: [] }],
    ['clean']
  ];

  const toolbarTooltips = {
    '.ql-bold': 'Negrita',
    '.ql-italic': 'Cursiva',
    '.ql-underline': 'Subrayado',
    '.ql-strike': 'Tachado',
    '.ql-list[value="ordered"]': 'Lista numerada',
    '.ql-list[value="bullet"]': 'Lista con viñetas',
    '.ql-clean': 'Limpiar formato',
    '.ql-header .ql-picker-label': 'Estilo de texto',
    '.ql-align .ql-picker-label': 'Alineación',
  };

  function toHtmlBlock(raw) {
    const val = (raw || '').trim();
    if (!val) return '<p><br></p>';
    if (/<[a-z][\s\S]*>/i.test(val)) return val;
    return val
      .split(/\n+/)
      .map(function(line) {
        const div = document.createElement('div');
        div.textContent = line;
        return '<p>' + div.innerHTML + '</p>';
      })
      .join('');
  }

  function composeUnifiedHtml(clinical, findings, impression) {
    const parts = [];
    if ((clinical || '').trim() !== '') {
      parts.push('<h3>Antecedentes / Motivo Clínico</h3>' + toHtmlBlock(clinical));
    }
    parts.push(toHtmlBlock(findings));
    if ((impression || '').trim() !== '') {
      parts.push(toHtmlBlock(impression));
    }
    return parts.join('<p><br></p>');
  }

  function splitUnifiedHtml(html) {
    const container = document.createElement('div');
    container.innerHTML = html || '';

    const sections = { clinical_history: [], findings: [], impression: [] };
    let current = null;
    let foundAnyHeading = false;

    Array.from(container.childNodes).forEach(function(node) {
      if (node.nodeType === Node.ELEMENT_NODE && /^H[1-4]$/.test(node.tagName)) {
        const headingText = (node.textContent || '')
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '');
        if (headingText.includes('antecedente') || headingText.includes('motivo') || headingText.includes('historia')) {
          current = 'clinical_history';
          foundAnyHeading = true;
          return;
        }
        if (headingText.includes('hallazgo')) {
          current = 'findings';
          foundAnyHeading = true;
          return;
        }
        if (headingText.includes('conclusion') || headingText.includes('impresion')) {
          current = 'impression';
          foundAnyHeading = true;
          return;
        }
      }

      if (current) {
        sections[current].push(node.outerHTML || node.textContent || '');
      } else {
        sections.findings.push(node.outerHTML || node.textContent || '');
      }
    });

    const cleanBlock = function(arr) {
      const joined = arr.join('').trim();
      const tmp = document.createElement('div');
      tmp.innerHTML = joined;
      return tmp.textContent.trim() === '' ? '' : joined;
    };

    if (!foundAnyHeading) {
      return {
        clinical_history: '',
        findings: cleanBlock(sections.findings),
        impression: '',
      };
    }

    return {
      clinical_history: cleanBlock(sections.clinical_history),
      findings: cleanBlock(sections.findings),
      impression: cleanBlock(sections.impression),
    };
  }

  const clinicalTa   = document.getElementById('clinical_history');
  const findingsTa   = document.getElementById('findings');
  const impressionTa = document.getElementById('impression');
  const editorEl     = document.getElementById('report_editor');
  let reportQuill    = null;

  function syncUnifiedToTextareas() {
    if (!reportQuill) return;
    const split = splitUnifiedHtml(reportQuill.root.innerHTML);
    if (clinicalTa) clinicalTa.value = split.clinical_history;
    if (findingsTa) findingsTa.value = split.findings;
    if (impressionTa) impressionTa.value = split.impression;
  }

  if (editorEl && typeof Quill !== 'undefined') {
    reportQuill = new Quill(editorEl, {
      theme: 'snow',
      placeholder: 'Redacta el informe médico aquí…',
      modules: { toolbar: quillToolbar },
    });

    const toolbarModule = reportQuill.getModule('toolbar');
    if (toolbarModule && toolbarModule.container) {
      Object.keys(toolbarTooltips).forEach(function(sel) {
        const btn = toolbarModule.container.querySelector(sel);
        if (btn) btn.setAttribute('title', toolbarTooltips[sel]);
      });
    }

    const initialHtml = composeUnifiedHtml(
      clinicalTa ? clinicalTa.value : '',
      findingsTa ? findingsTa.value : '',
      impressionTa ? impressionTa.value : ''
    );
    reportQuill.clipboard.dangerouslyPasteHTML(initialHtml);
    syncUnifiedToTextareas();

    reportQuill.on('text-change', syncUnifiedToTextareas);
  }

  // Sincronizar y validar al enviar el formulario
  const reportForm = document.querySelector('form[action*="/reports/save/"]');
  if (reportForm) {
    reportForm.addEventListener('submit', function(e) {
      syncUnifiedToTextareas();
      const totalPlain = reportQuill ? reportQuill.getText().trim() : '';
      if (totalPlain.length < 5) {
        e.preventDefault();
        if (reportQuill) reportQuill.focus();
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Informe incompleto', text: 'El informe debe tener al menos 5 caracteres.' });
        } else {
          alert('El informe debe tener al menos 5 caracteres.');
        }
        return;
      }

      const findingsTmp = document.createElement('div');
      findingsTmp.innerHTML = findingsTa ? findingsTa.value : '';
      const findingsPlain = (findingsTmp.textContent || '').trim();
      if (findingsPlain.length < 5 && findingsTa && reportQuill) {
        // Si el médico escribió fuera de la sección Hallazgos, guardar todo el contenido para no bloquear el envío
        findingsTa.value = reportQuill.root.innerHTML;
      }
    });
  }

  const templateSelect = document.getElementById('templateSelect');
  if (templateSelect) {
    templateSelect.addEventListener('change', function() {
      const opt = templateSelect.options[templateSelect.selectedIndex];
      if (!opt || !opt.value) return;
      const findingsVal   = opt.getAttribute('data-findings') || '';
      const impressionVal = opt.getAttribute('data-impression') || '';
      if (reportQuill) {
        const current = splitUnifiedHtml(reportQuill.root.innerHTML);
        const newHtml = composeUnifiedHtml(current.clinical_history, findingsVal, impressionVal);
        reportQuill.clipboard.dangerouslyPasteHTML(newHtml);
        syncUnifiedToTextareas();
      }
    });
  }

  function jumpToSectionInEditor(sectionKey) {
    if (!reportQuill) return false;
    const text = reportQuill.getText();
    const patterns = {
      clinical_history: /antecedentes\s*\/\s*motivo cl[ií]nico/i,
      findings: /hallazgos/i,
      impression: /conclusi[oó]n(\s*\/\s*impresi[oó]n diagn[oó]stica)?/i,
    };
    const match = text.match(patterns[sectionKey]);
    if (!match || typeof match.index !== 'number') return false;
    const lineEnd = text.indexOf('\n', match.index);
    const targetPos = lineEnd !== -1 ? lineEnd + 1 : text.length;
    reportQuill.setSelection(targetPos, 0, 'user');
    reportQuill.focus();
    return true;
  }

  function insertTextIntoActiveEditor(rawText) {
    if (!reportQuill) return;

    const range = reportQuill.getSelection(true) || { index: Math.max(0, reportQuill.getLength() - 1), length: 0 };
    const before = reportQuill.getText(0, range.index);
    const after = reportQuill.getText(range.index + range.length);

    const startsWithPunctuation = /^[.,:;)\n]/.test(rawText);
    const needsLeadingSpace = before.length > 0 && !/[\s(\n]$/.test(before) && !startsWithPunctuation;
    const needsTrailingSpace = after.length > 1 && !/^[\s.,:;)\n]/.test(after) && !/[\s(\n]$/.test(rawText);

    let formatted = rawText;
    if (before.length === 0 || /([.!?]\s*|\n)$/.test(before)) {
      formatted = formatted.replace(/^[a-záéíóúñ]/, function(ch) {
        return ch.toUpperCase();
      });
    }

    const toInsert = (needsLeadingSpace ? ' ' : '') + formatted + (needsTrailingSpace ? ' ' : '');
    if (range.length > 0) {
      reportQuill.deleteText(range.index, range.length, 'user');
    }
    reportQuill.insertText(range.index, toInsert, 'user');
    reportQuill.setSelection(range.index + toInsert.length, 0, 'user');
    syncUnifiedToTextareas();
  }

  const voiceBtn = document.getElementById('voiceBtn');
  const voiceStatus = document.getElementById('voiceStatus');
  const transcribeUrl = <?= json_encode(base_url('reports/transcribe')) ?>;
  const csrfTokenName = <?= json_encode(csrf_token()) ?>;
  let csrfTokenHash = <?= json_encode(csrf_hash()) ?>;

  function setVoiceStatus(message, isError) {
    if (!voiceStatus) return;
    if (!message) {
      voiceStatus.textContent = '';
      voiceStatus.classList.add('d-none');
      voiceStatus.classList.remove('text-danger', 'text-muted');
      return;
    }
    voiceStatus.textContent = message;
    voiceStatus.classList.remove('d-none', 'text-danger', 'text-muted');
    voiceStatus.classList.add(isError ? 'text-danger' : 'text-muted');
  }

  const supportsMediaRecorder =
    typeof window.MediaRecorder !== 'undefined' &&
    navigator.mediaDevices &&
    typeof navigator.mediaDevices.getUserMedia === 'function';

  if (!window.isSecureContext) {
    voiceBtn.disabled = true;
    voiceBtn.title = 'El dictado por voz requiere HTTPS o localhost.';
    setVoiceStatus('El dictado por voz requiere conexión segura (HTTPS).', true);
  } else if (!supportsMediaRecorder) {
    voiceBtn.disabled = true;
    voiceBtn.title = 'Tu navegador no soporta grabación de audio.';
    setVoiceStatus('Grabación de audio no disponible en este navegador.', true);
  } else {
    let mediaRecorder = null;
    let mediaStream = null;
    let audioCtx = null;
    let analyser = null;
    let vadInterval = null;
    let timerInterval = null;
    let recording = false;
    let startedAt = 0;
    let chunkStartedAt = 0;
    let lastSpeechAt = 0;
    let hasSpokenInChunk = false;
    let rotatingChunk = false;
    let uploadQueue = [];
    let isUploading = false;
    let selectedMime = '';

    const SPEECH_RMS_THRESHOLD = 0.015;
    const SILENCE_MS = 850;
    const MIN_CHUNK_MS = 1100;
    const MAX_CHUNK_MS = 10000;

    function pickMimeType() {
      const candidates = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
      for (let i = 0; i < candidates.length; i++) {
        if (MediaRecorder.isTypeSupported(candidates[i])) {
          return candidates[i];
        }
      }
      return '';
    }

    function cleanupAudioCapture() {
      if (vadInterval) {
        clearInterval(vadInterval);
        vadInterval = null;
      }
      if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
      }
      if (audioCtx) {
        audioCtx.close().catch(function() {});
        audioCtx = null;
        analyser = null;
      }
      if (mediaStream) {
        mediaStream.getTracks().forEach(function(t) { t.stop(); });
        mediaStream = null;
      }
    }

    function setIdleButton() {
      voiceBtn.disabled = false;
      voiceBtn.classList.remove('btn-danger');
      voiceBtn.classList.add('btn-primary');
      voiceBtn.innerHTML = '<i class="ti ti-microphone me-1"></i> Dictar por Voz';
    }

    function setRecordingButton() {
      voiceBtn.disabled = false;
      voiceBtn.classList.remove('btn-primary');
      voiceBtn.classList.add('btn-danger');
      voiceBtn.innerHTML = '<i class="ti ti-player-stop me-1"></i> Detener Dictado';
    }

    function setTranscribingButton() {
      voiceBtn.disabled = true;
      voiceBtn.classList.remove('btn-danger');
      voiceBtn.classList.add('btn-primary');
      voiceBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Transcribiendo…';
    }

    function updateRecordingStatus() {
      if (!recording) return;
      const secs = Math.floor((Date.now() - startedAt) / 1000);
      const mm = Math.floor(secs / 60);
      const ss = String(secs % 60).padStart(2, '0');
      const suffix = isUploading ? ' · Transcribiendo…' : '';
      setVoiceStatus('Grabando (' + mm + ':' + ss + ')' + suffix, false);
    }

    async function transcribeChunk(blob, mimeType) {
      const ext = mimeType.indexOf('mp4') !== -1 ? 'm4a' : (mimeType.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
      const formData = new FormData();
      formData.append('audio', blob, 'dictado.' + ext);
      formData.append(csrfTokenName, csrfTokenHash);

      const resp = await fetch(transcribeUrl, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await resp.json().catch(function() { return {}; });
      if (data && data.csrf_hash) {
        csrfTokenHash = data.csrf_hash;
        const hiddenCsrf = document.querySelector('input[name="' + csrfTokenName + '"]');
        if (hiddenCsrf) hiddenCsrf.value = data.csrf_hash;
      }

      if (!resp.ok) {
        throw new Error((data && data.error) || 'Error al transcribir.');
      }

      const rawText = (data && data.text ? data.text : '').trim();
      if (!rawText) return;

      const navTarget = voiceHelpers ? voiceHelpers.detectNavigationCommand(rawText) : null;
      if (navTarget && jumpToSectionInEditor(navTarget)) {
        return;
      }

      const processed = voiceHelpers
        ? voiceHelpers.applyVoiceCommands(rawText, voiceHelpers.VOICE_MACROS)
        : rawText;

      if (processed) {
        insertTextIntoActiveEditor(processed);
      }
    }

    async function processUploadQueue() {
      if (isUploading) return;
      isUploading = true;
      updateRecordingStatus();

      try {
        while (uploadQueue.length > 0) {
          const item = uploadQueue.shift();
          try {
            await transcribeChunk(item.blob, item.mimeType);
          } catch (err) {
            setVoiceStatus(err.message || 'Error al transcribir.', true);
          }
        }
      } finally {
        isUploading = false;
        if (recording) {
          updateRecordingStatus();
        } else {
          setIdleButton();
          if (voiceStatus && !voiceStatus.classList.contains('text-danger')) {
            setVoiceStatus('Dictado completado.', false);
          }
        }
      }
    }

    function startNewRecorderChunk() {
      if (!recording || !mediaStream) return;
      const chunks = [];
      const spokenFlagSnapshot = { active: false };
      chunkStartedAt = Date.now();
      lastSpeechAt = chunkStartedAt;
      hasSpokenInChunk = false;

      let rec;
      try {
        rec = selectedMime ? new MediaRecorder(mediaStream, { mimeType: selectedMime }) : new MediaRecorder(mediaStream);
      } catch (err) {
        recording = false;
        cleanupAudioCapture();
        setIdleButton();
        setVoiceStatus('No se pudo iniciar la grabación.', true);
        return;
      }

      rec.ondataavailable = function(e) {
        if (e.data && e.data.size > 0) {
          chunks.push(e.data);
        }
        if (hasSpokenInChunk) {
          spokenFlagSnapshot.active = true;
        }
      };

      rec.onstop = function() {
        const finalMime = rec.mimeType || selectedMime || 'audio/webm';
        const blob = new Blob(chunks, { type: finalMime });
        const shouldSend = blob.size > 0 && (spokenFlagSnapshot.active || hasSpokenInChunk);

        if (recording && rotatingChunk) {
          rotatingChunk = false;
          startNewRecorderChunk();
        }

        if (shouldSend) {
          uploadQueue.push({ blob: blob, mimeType: finalMime });
          processUploadQueue();
        } else if (!recording && !isUploading && uploadQueue.length === 0) {
          setIdleButton();
          setVoiceStatus('Dictado detenido.', false);
        }
      };

      mediaRecorder = rec;
      rec.start();
    }

    async function toggleDictation() {
      if (recording) {
        recording = false;
        rotatingChunk = false;
        cleanupAudioCapture();
        if (isUploading || uploadQueue.length > 0) {
          setTranscribingButton();
          setVoiceStatus('Transcribiendo tramo final…', false);
        } else {
          setTranscribingButton();
        }
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
          mediaRecorder.stop();
        } else if (!isUploading && uploadQueue.length === 0) {
          setIdleButton();
          setVoiceStatus('Dictado detenido.', false);
        }
        return;
      }

      try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      } catch (err) {
        setVoiceStatus('Permiso de micrófono denegado o no disponible.', true);
        return;
      }

      selectedMime = pickMimeType();
      recording = true;
      startedAt = Date.now();
      setRecordingButton();

      // Detector de pausas (VAD) con AudioContext para enviar frase a frase
      const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
      if (AudioContextCtor) {
        try {
          audioCtx = new AudioContextCtor();
          if (audioCtx.state === 'suspended') {
            await audioCtx.resume();
          }
          const source = audioCtx.createMediaStreamSource(mediaStream);
          analyser = audioCtx.createAnalyser();
          analyser.fftSize = 1024;
          source.connect(analyser);
          const pcm = new Float32Array(analyser.fftSize);

          vadInterval = setInterval(function() {
            if (!recording || !analyser || rotatingChunk) return;
            analyser.getFloatTimeDomainData(pcm);
            let sum = 0;
            for (let i = 0; i < pcm.length; i++) {
              sum += pcm[i] * pcm[i];
            }
            const rms = Math.sqrt(sum / pcm.length);
            const now = Date.now();

            if (rms >= SPEECH_RMS_THRESHOLD) {
              hasSpokenInChunk = true;
              lastSpeechAt = now;
            }

            const elapsed = now - chunkStartedAt;
            const silenceDuration = now - lastSpeechAt;
            const shouldRotateByPause = hasSpokenInChunk && elapsed >= MIN_CHUNK_MS && silenceDuration >= SILENCE_MS;
            const shouldRotateByMax = hasSpokenInChunk && elapsed >= MAX_CHUNK_MS;

            if ((shouldRotateByPause || shouldRotateByMax) && mediaRecorder && mediaRecorder.state === 'recording') {
              rotatingChunk = true;
              mediaRecorder.stop();
            }
          }, 80);
        } catch (err) {
          // Si AudioContext falla, sigue funcionando en modo manual al pulsar Detener
          hasSpokenInChunk = true;
        }
      } else {
        hasSpokenInChunk = true;
      }

      startNewRecorderChunk();
      updateRecordingStatus();
      timerInterval = setInterval(updateRecordingStatus, 500);
    }

    voiceBtn.addEventListener('click', toggleDictation);

    document.addEventListener('keydown', function(e) {
      if (e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 'm') {
        e.preventDefault();
        if (!voiceBtn.disabled) toggleDictation();
      }
    });
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
