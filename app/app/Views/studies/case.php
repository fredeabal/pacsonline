<?= view('template/header') ?>

<?php
  $currentUser = auth()->user();
  $backUrl     = ($currentUser?->can('reports.review') && ! $currentUser?->can('users.manage'))
      ? base_url('reports')
      : base_url('studies');
  $modality = extract_study_modality($study ?? []);
  $priority = $study['priority'] ?? 'normal';
?>

<?php
  // Caja de dato del resumen (solo borde, sin fondo).
  $summaryBox = static function (string $icon, string $label, string $value): string {
      return '<div class="p-3 rounded-2 border border-primary-subtle h-100">'
          . '<span class="text-primary text-uppercase fw-semibold d-flex align-items-center gap-1 mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">'
          . '<i class="ti ti-' . esc($icon, 'attr') . '"></i> ' . esc($label) . '</span>'
          . '<span class="fw-semibold text-body fs-3 text-uppercase text-truncate d-block" title="' . esc($value, 'attr') . '">' . esc($value) . '</span>'
          . '</div>';
  };
  $sectionLabelStyle = 'font-size: 0.7rem; letter-spacing: 0.05em;';
?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0 text-primary fw-semibold">Resumen del estudio</h5>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($currentUser?->can('studies.edit_dicom')): ?>
        <a href="<?= base_url('studies/case/' . urlencode($studyUid) . '/dicom') ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
          <i class="ti ti-edit"></i> <span class="d-none d-sm-inline">Editar DICOM</span>
        </a>
      <?php endif; ?>
      <?php if ($currentUser?->can('reports.review') && (empty($report) || ($report['status'] ?? '') !== 'reported')): ?>
        <a href="<?= base_url('reports/edit/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
          <i class="ti ti-stethoscope"></i> <?= empty($report) ? 'Informar' : 'Editar borrador' ?>
        </a>
      <?php endif; ?>
      <?php if ($currentUser?->can('studies.share')): ?>
        <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
          <i class="ti ti-user-check"></i> <span class="d-none d-sm-inline">Acceso paciente</span>
        </a>
      <?php endif; ?>
      <a href="<?= ohif_url($studyUid) ?>" target="_blank" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
        <i class="ti ti-eye"></i> Abrir en OHIF
      </a>
      <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
        <i class="ti ti-arrow-left"></i> <span class="d-none d-md-inline">Volver</span>
      </a>
    </div>
  </div>

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
          <div class="col-12 col-md-6 col-lg-7">
            <?= $summaryBox('file-description', 'Descripción', trim($modality . ' ' . format_study_description($study['study_description'] ?? null))) ?>
          </div>
          <div class="col-6 col-md-3 col-lg-2">
            <?= $summaryBox('folders', 'Series', (string) ($study['series_count'] ?? '-')) ?>
          </div>
          <div class="col-6 col-md-3 col-lg-3">
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
  </div>
</div>

<?php if ($currentUser?->can('studies.assign')): ?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Asignación y prioridad del caso</h5>
  </div>

  <div class="card-body">
    <form action="<?= base_url('studies/case/' . urlencode($studyUid)) ?>" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="orthanc_id" value="<?= esc($study['orthanc_id'] ?? '') ?>">

      <div class="row g-3">
        <?php $hasCentersList = ! empty($centers); ?>
        <div class="<?= $hasCentersList ? 'col-md-4' : 'col-md-6' ?>">
          <label for="assigned_doctor_id" class="form-label fw-semibold">Médico informante</label>
          <select class="form-select" id="assigned_doctor_id" name="assigned_doctor_id">
            <option value="">-- Cualquier médico --</option>
            <?php foreach ($doctors as $did => $dUser): ?>
              <option value="<?= (int) $did ?>" <?= ((int) ($study['assigned_doctor_id'] ?? 0) === (int) $did) ? 'selected' : '' ?>>
                <?= esc($dUser->full_name ?: $dUser->username) ?> (ID: <?= (int) $did ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if ($hasCentersList): ?>
        <div class="col-md-4">
          <label for="case_center_id" class="form-label fw-semibold">Centro remitente</label>
          <select class="form-select" id="case_center_id" name="center_id">
            <option value="">-- Sin centro asignado --</option>
            <?php foreach ($centers as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= ((int) ($study['center_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>>
                <?= esc($c['name']) ?> (<?= esc($c['ae_title']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="<?= $hasCentersList ? 'col-md-4' : 'col-md-6' ?>">
          <label for="priority" class="form-label fw-semibold">Prioridad</label>
          <select class="form-select" id="priority" name="priority">
            <option value="normal" <?= ($study['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
            <option value="urgent" <?= ($study['priority'] ?? 'normal') === 'urgent' ? 'selected' : '' ?>>Urgente</option>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Asignación
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Motivo de consulta y antecedentes</h5>
  </div>
  <div class="card-body">
    <?php if (! empty($canEditCaseContent)): ?>
      <form action="<?= base_url('studies/case/' . urlencode($studyUid) . '/notes') ?>" method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label for="clinical_notes" class="form-label fw-semibold">Síntomas, motivo de segunda opinión o antecedentes relevantes</label>
          <textarea class="form-control" id="clinical_notes" name="clinical_notes" rows="3" placeholder="Describe tus síntomas, tratamientos previos o dudas concretas para el especialista..."><?= esc(old('clinical_notes', $study['clinical_notes'] ?? '')) ?></textarea>
        </div>
        <div class="d-flex justify-content-center">
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="ti ti-device-floppy me-1"></i> Guardar Motivo de Consulta
          </button>
        </div>
      </form>
    <?php else: ?>
      <?php if (! empty($study['clinical_notes'])): ?>
        <p class="mb-0 text-body"><?= nl2br(esc($study['clinical_notes'])) ?></p>
      <?php else: ?>
        <p class="mb-0 text-muted">El paciente no ha registrado motivo de consulta todavía.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php
  $hasPublishedReport = ! empty($report) && ($report['status'] ?? '') === 'reported';
  $hasDraftReport     = ! empty($report) && ($report['status'] ?? '') !== 'reported' && $currentUser?->can('reports.review');
  $showReportRow      = $hasPublishedReport || $hasDraftReport;
  $dicomDocsList      = $dicomDocs ?? [];
?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Documentos clínicos (Informes / Analíticas)</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= count($attachments) + count($dicomDocsList) + ($showReportRow ? 1 : 0) ?> archivos</span>
  </div>

  <div class="card-body">
    <div class="border rounded-1 table-responsive" style="min-height: auto;">
      <table class="table text-nowrap mb-0 align-middle table-hover">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Documento</th>
            <th class="text-center">Tipo</th>
            <th class="text-center">Tamaño</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (! $showReportRow && empty($attachments) && empty($dicomDocsList)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-paperclip d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay documentos clínicos adjuntos en este caso.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php if ($showReportRow): ?>
            <tr style="cursor: pointer;" onclick="window.open('<?= base_url('reports/pdf/' . urlencode($studyUid)) ?>', '_blank')">
              <td class="text-muted small"><?= esc(format_date($report['reported_at'] ?? ($report['updated_at'] ?? null))) ?></td>
              <td class="fw-semibold text-body">
                <i class="ti ti-file-text text-primary me-1"></i> <?= $hasPublishedReport ? 'Informe_Medico_Oficial.pdf' : 'Informe_Medico_Borrador.pdf' ?>
              </td>
              <td class="text-center">
                <?php if ($hasPublishedReport): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold">PDF</span>
                <?php else: ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2 fw-semibold">Borrador</span>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted small"><?= $hasPublishedReport ? 'Oficial' : 'En revisión' ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/pdf/' . urlencode($studyUid)) ?>" target="_blank">
                        <i class="ti ti-eye fs-5"></i> <?= $hasPublishedReport ? 'Ver / Descargar PDF' : 'Vista previa PDF' ?>
                      </a>
                    </li>
                    <?php if ($currentUser?->can('reports.review')): ?>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/edit/' . urlencode($studyUid)) ?>">
                          <i class="ti ti-edit fs-5"></i> <?= $hasPublishedReport ? 'Editar informe' : 'Editar borrador' ?>
                        </a>
                      </li>
                    <?php endif; ?>
                    <?php
                      $canDeleteReport = $currentUser !== null && (
                          $currentUser->can('studies.delete')
                          || ($currentUser->can('reports.review') && (int) ($report['doctor_user_id'] ?? 0) === (int) $currentUser->id)
                      );
                    ?>
                    <?php if ($canDeleteReport): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="<?= base_url('reports/delete/' . urlencode($studyUid)) ?>" method="post" class="delete-attachment-form" data-confirm-title="¿Eliminar informe médico?">
                          <?= csrf_field() ?>
                          <input type="hidden" name="return_to" value="case">
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="ti ti-trash fs-5"></i> Eliminar
                          </button>
                        </form>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </td>
            </tr>
            <?php endif; ?>
            <?php
              $canImportToReport = $currentUser !== null
                  && $currentUser->can('reports.review')
                  && user_can_modify_report($currentUser, $report ?? null, $study ?? null);
              $hasExistingReport = ! empty($report);
            ?>
            <?php foreach ($dicomDocsList as $doc):
              $docUrl = base_url('studies/dicom-doc/' . urlencode($studyUid) . '/' . urlencode((string) $doc['instance_id']));
            ?>
            <tr style="cursor: pointer;" onclick="window.open('<?= $docUrl ?>', '_blank')">
              <td class="text-muted small"><?= esc(format_date($doc['date'] ?? null)) ?></td>
              <td class="fw-semibold text-body">
                <i class="ti ti-file-text text-primary me-1"></i> <?= esc($doc['title'] ?? 'Informe_DICOM.pdf') ?>
              </td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold">PDF</span>
              </td>
              <td class="text-center text-muted small"><?= ! empty($doc['size']) ? number_format(((int) $doc['size']) / 1024, 1) . ' KB' : 'DICOM' ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= $docUrl ?>" target="_blank">
                        <i class="ti ti-eye fs-5"></i> Ver / Descargar PDF
                      </a>
                    </li>
                    <?php if ($canImportToReport): ?>
                      <li>
                        <form action="<?= base_url('reports/import-pdf/' . urlencode($studyUid)) ?>" method="post" class="import-pdf-form" data-has-report="<?= $hasExistingReport ? '1' : '0' ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="source" value="dicom">
                          <input type="hidden" name="instance_id" value="<?= esc((string) $doc['instance_id'], 'attr') ?>">
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-primary">
                            <i class="ti ti-file-import fs-5"></i> Importar al informe
                          </button>
                        </form>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php foreach ($attachments as $att):
              $attExt = strtoupper((string) pathinfo((string) ($att['original_name'] ?? ''), PATHINFO_EXTENSION));
              if ($attExt === '') {
                  $attExt = 'ARCHIVO';
              }
            ?>
            <tr style="cursor: pointer;" onclick="window.open('<?= base_url('studies/attachment/' . (int) $att['id']) ?>', '_blank')">
              <td class="text-muted small"><?= esc(format_date($att['created_at'] ?? null)) ?></td>
              <td class="fw-semibold text-body">
                <i class="ti ti-file-text text-primary me-1"></i> <?= esc($att['original_name']) ?>
              </td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($attExt) ?></span>
              </td>
              <td class="text-center text-muted small"><?= number_format(((int) $att['file_size']) / 1024, 1) ?> KB</td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/attachment/' . (int) $att['id']) ?>" target="_blank">
                        <i class="ti ti-eye fs-5"></i> Ver / Descargar
                      </a>
                    </li>
                    <?php if ($canImportToReport && $attExt === 'PDF'): ?>
                      <li>
                        <form action="<?= base_url('reports/import-pdf/' . urlencode($studyUid)) ?>" method="post" class="import-pdf-form" data-has-report="<?= $hasExistingReport ? '1' : '0' ?>">
                          <?= csrf_field() ?>
                          <input type="hidden" name="source" value="attachment">
                          <input type="hidden" name="attachment_id" value="<?= (int) $att['id'] ?>">
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-primary">
                            <i class="ti ti-file-import fs-5"></i> Importar al informe
                          </button>
                        </form>
                      </li>
                    <?php endif; ?>
                    <?php if ($currentUser?->can('studies.delete') || ($currentUser?->can('reports.review') && (int) ($att['uploaded_by_user_id'] ?? 0) === (int) auth()->id())): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="<?= base_url('studies/attachment/' . (int) $att['id'] . '/delete') ?>" method="post" class="delete-attachment-form">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="ti ti-trash fs-5"></i> Eliminar
                          </button>
                        </form>
                      </li>
                    <?php endif; ?>
                  </ul>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?= view('studies/_priors_card', [
    'studyUid'       => $studyUid,
    'priorStudies'   => $priorStudies ?? [],
    'isReportEditor' => false,
]) ?>

<?php if ($currentUser?->can('studies.attachments')): ?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Adjuntar documentos clínicos</h5>
  </div>
  <div class="card-body">
    <form action="<?= base_url('studies/attachment/' . urlencode($studyUid)) ?>" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="attachmentInput" class="form-label fw-semibold">Adjuntar informe previo, analítica o petición médica</label>
        <input class="form-control" type="file" id="attachmentInput" name="attachments[]" multiple accept=".pdf,.png,.jpg,.jpeg" required>
        <div class="form-text">Formatos permitidos: PDF, PNG, JPG (Máx. 20 MB por archivo). Puedes seleccionar varios archivos.</div>
      </div>
      <div class="d-flex justify-content-center">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-cloud-upload me-1"></i> Subir Adjuntos
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($currentUser?->can('studies.send') && ! empty($sendableNodes)): ?>
<div class="card mb-4">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Enviar estudio a nodo DICOM</h5>
  </div>
  <div class="card-body">
    <form action="<?= base_url('studies/send/' . urlencode($studyUid)) ?>" method="post" id="sendNodeForm">
      <?= csrf_field() ?>
      <label for="send_center_id" class="form-label fw-semibold">Nodo destino</label>
      <div class="input-group flex-nowrap">
        <div class="flex-grow-1 min-w-0">
          <select class="form-select" id="send_center_id" name="center_id" required>
            <option value="">-- Seleccionar nodo --</option>
            <?php foreach ($sendableNodes as $node): ?>
              <option value="<?= (int) $node['id'] ?>">
                <?= esc($node['name']) ?> (<?= esc($node['ae_title']) ?> · <?= esc($node['dicom_host']) ?>:<?= (int) $node['dicom_port'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary text-nowrap" id="sendNodeBtn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="sendNodeSpinner" role="status" aria-hidden="true"></span>
          <i class="ti ti-send me-1" id="sendNodeIcon"></i>
          <span id="sendNodeBtnText">Enviar a nodo</span>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.querySelectorAll('.import-pdf-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      if (form.dataset.hasReport !== '1') {
        return;
      }
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Reemplazar contenido del informe?',
          text: 'Ya existe un informe para este estudio. Al importar este PDF se reemplazará su contenido.',
          showCancelButton: true,
          confirmButtonText: 'Sí, importar',
          cancelButtonText: 'Cancelar',
          customClass: {
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-dark'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      } else if (confirm('Ya existe un informe para este estudio. ¿Reemplazar su contenido con este PDF?')) {
        form.submit();
      }
    });
  });

  document.querySelectorAll('.delete-attachment-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      const confirmTitle = form.dataset.confirmTitle || '¿Eliminar documento?';
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: confirmTitle,
          text: 'Se eliminará de forma permanente.',
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
            form.submit();
          }
        });
      } else if (confirm('¿Eliminar este documento?')) {
        form.submit();
      }
    });
  });

  const sendNodeForm    = document.getElementById('sendNodeForm');
  const sendNodeBtn     = document.getElementById('sendNodeBtn');
  const sendNodeSpinner = document.getElementById('sendNodeSpinner');
  const sendNodeIcon    = document.getElementById('sendNodeIcon');
  const sendNodeBtnText = document.getElementById('sendNodeBtnText');
  const sendNodeSelect  = document.getElementById('send_center_id');

  function notifySendResult(icon, title, text) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: icon, title: title, text: text });
    }
  }

  if (sendNodeForm && sendNodeBtn) {
    sendNodeForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      if (!sendNodeSelect || !sendNodeSelect.value) {
        notifySendResult('warning', 'Selecciona un nodo', 'Selecciona un nodo destino antes de enviar.');
        return;
      }

      sendNodeBtn.disabled = true;
      if (sendNodeSpinner) sendNodeSpinner.classList.remove('d-none');
      if (sendNodeIcon) sendNodeIcon.classList.add('d-none');
      if (sendNodeBtnText) sendNodeBtnText.textContent = 'Enviando...';

      try {
        const resp = await fetch(sendNodeForm.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(sendNodeForm)
        });
        const data = await resp.json().catch(() => ({ ok: false, message: 'Respuesta inesperada del servidor (HTTP ' + resp.status + ').' }));

        if (data.csrf_hash) {
          const csrfInput = sendNodeForm.querySelector('input[name="<?= csrf_token() ?>"]');
          if (csrfInput) csrfInput.value = data.csrf_hash;
        }

        if (resp.ok && data.ok) {
          notifySendResult('success', 'Estudio enviado', data.message || 'Estudio enviado correctamente.');
        } else {
          notifySendResult('error', 'Error al enviar', data.message || 'No se pudo enviar el estudio.');
        }
      } catch (err) {
        notifySendResult('error', 'Error de conexión', 'Error de red o tiempo de espera agotado al enviar el estudio.');
      } finally {
        sendNodeBtn.disabled = false;
        if (sendNodeSpinner) sendNodeSpinner.classList.add('d-none');
        if (sendNodeIcon) sendNodeIcon.classList.remove('d-none');
        if (sendNodeBtnText) sendNodeBtnText.textContent = 'Enviar a nodo';
      }
    });
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
