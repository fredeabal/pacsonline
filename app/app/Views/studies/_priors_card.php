<?php
/**
 * Tarjeta reutilizable de estudios previos (Priors) del mismo PatientID.
 *
 * Variables esperadas:
 * - string $studyUid
 * - array  $priorStudies
 * - bool   $isReportEditor (opcional, por defecto false)
 */
$priors         = $priorStudies ?? [];
$priorsCount    = count($priors);
$isReportEditor = ! empty($isReportEditor);
?>

<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0 text-primary fw-semibold">
      <i class="ti ti-history me-1"></i> Estudios previos del paciente
    </h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2">
      <?= $priorsCount ?> <?= $priorsCount === 1 ? 'estudio' : 'estudios' ?>
    </span>
  </div>

  <div class="card-body">
    <div class="border rounded-1 table-responsive" style="min-height: auto;">
      <table class="table text-nowrap mb-0 align-middle table-hover">
        <thead>
          <tr>
            <th>Fecha</th>
            <th class="text-center">Modalidad</th>
            <th>Descripción</th>
            <th class="text-center">Informe</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($priors === []): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-history-off d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No se encontraron otros estudios previos para este ID de paciente.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($priors as $prior):
              $priorUid        = (string) ($prior['uid'] ?? '');
              $priorDate       = format_date($prior['study_date'] ?? null);
              $priorModality   = (string) ($prior['modality'] ?? 'DICOM');
              $priorDesc       = format_study_description($prior['description'] ?? null);
              $reportStatus    = (string) ($prior['report_status'] ?? 'pending');
              $canViewReport   = ! empty($prior['can_view_report']) && is_array($prior['report'] ?? null);
              $hasPublishedPdf = ! empty($prior['has_published_report']);
              $compareUrl      = ohif_compare_url([$studyUid, $priorUid]);
              $singleOhifUrl   = ohif_url($priorUid);
              $caseUrl         = base_url('studies/case/' . urlencode($priorUid));
              $pdfUrl          = base_url('reports/pdf/' . urlencode($priorUid));

              if ($reportStatus === 'reported') {
                  $statusBadgeClass = 'bg-success-subtle text-success border border-success-subtle';
                  $statusLabel      = 'Informado';
              } elseif ($reportStatus === 'in_review') {
                  $statusBadgeClass = 'bg-warning-subtle text-warning border border-warning-subtle';
                  $statusLabel      = 'En revisión';
              } else {
                  $statusBadgeClass = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                  $statusLabel      = 'Pendiente';
              }
            ?>
            <tr>
              <td class="text-muted small"><?= esc($priorDate) ?></td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($priorModality) ?></span>
              </td>
              <td class="fw-semibold text-body" style="max-width: 340px;">
                <a href="<?= $caseUrl ?>" class="text-body text-decoration-none d-block text-truncate" title="<?= esc($priorDesc, 'attr') ?>">
                  <?= esc($priorDesc) ?>
                </a>
              </td>
              <td class="text-center">
                <span class="badge <?= $statusBadgeClass ?> fs-2 fw-semibold"><?= esc($statusLabel) ?></span>
              </td>
              <td class="text-end pe-3">
                <div class="d-inline-flex align-items-center gap-1">
                  <?php if ($canViewReport): ?>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 js-view-prior-report"
                      data-prior-date="<?= esc($priorDate, 'attr') ?>"
                      data-prior-modality="<?= esc($priorModality, 'attr') ?>"
                      data-prior-description="<?= esc($priorDesc, 'attr') ?>"
                      data-prior-status="<?= esc($statusLabel, 'attr') ?>"
                      data-prior-reported-at="<?= esc(format_date($prior['report']['reported_at'] ?? null), 'attr') ?>"
                      data-prior-history="<?= esc($prior['report']['clinical_history'] ?? '', 'attr') ?>"
                      data-prior-findings="<?= esc(report_plain_text($prior['report']['findings'] ?? ''), 'attr') ?>"
                      data-prior-impression="<?= esc(report_plain_text($prior['report']['impression'] ?? ''), 'attr') ?>"
                      data-prior-pdf-url="<?= $hasPublishedPdf ? esc($pdfUrl, 'attr') : '' ?>"
                      data-prior-compare-url="<?= esc($compareUrl, 'attr') ?>"
                      title="Ver informe previo">
                      <i class="ti ti-file-text"></i> <span class="d-none d-md-inline">Ver informe</span>
                    </button>
                  <?php endif; ?>

                  <a href="<?= $compareUrl ?>" target="_blank" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1" title="Abrir este estudio junto al actual en OHIF">
                    <i class="ti ti-columns-2"></i> <span class="d-none d-md-inline">Comparar</span>
                  </a>

                  <div class="dropdown dropstart d-inline-block">
                    <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Más acciones" aria-label="Más acciones">
                      <i class="ti ti-dots-vertical fs-6"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= $compareUrl ?>" target="_blank">
                          <i class="ti ti-columns-2 fs-5"></i> Comparar en OHIF
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= $singleOhifUrl ?>" target="_blank">
                          <i class="ti ti-eye fs-5"></i> Abrir solo en OHIF
                        </a>
                      </li>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= $caseUrl ?>">
                          <i class="ti ti-folder fs-5"></i> Abrir ficha del caso
                        </a>
                      </li>
                      <?php if ($hasPublishedPdf): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <a class="dropdown-item d-flex align-items-center gap-2" href="<?= $pdfUrl ?>" target="_blank">
                            <i class="ti ti-file-type-pdf fs-5"></i> Ver / Descargar PDF
                          </a>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </div>
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

<!-- Modal para consultar el contenido del informe previo sin salir de la pantalla -->
<div class="modal fade" id="priorReportModal" tabindex="-1" aria-labelledby="priorReportModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary-subtle">
        <div>
          <h5 class="modal-title text-primary fw-semibold mb-0" id="priorReportModalLabel">Informe de estudio previo</h5>
          <small class="text-muted" id="priorModalMeta"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="priorModalHistoryWrap" class="mb-3 d-none">
          <div class="text-primary text-uppercase fw-semibold small mb-1">Antecedentes / Motivo clínico</div>
          <div id="priorModalHistory" class="p-3 rounded-2 border bg-body-tertiary small" style="white-space: pre-wrap;"></div>
        </div>

        <div class="mb-3">
          <div class="text-primary text-uppercase fw-semibold small mb-1">Hallazgos</div>
          <div id="priorModalFindings" class="p-3 rounded-2 border bg-body-tertiary small" style="white-space: pre-wrap;"></div>
        </div>

        <div>
          <div class="text-primary text-uppercase fw-semibold small mb-1">Conclusión / Impresión diagnóstica</div>
          <div id="priorModalImpression" class="p-3 rounded-2 border border-primary-subtle bg-primary-subtle bg-opacity-10 fw-semibold small" style="white-space: pre-wrap;"></div>
        </div>
      </div>
      <div class="modal-footer d-flex flex-wrap justify-content-between gap-2">
        <div class="d-flex flex-wrap gap-2">
          <a href="#" id="priorModalCompareLink" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="ti ti-columns-2 me-1"></i> Comparar en OHIF
          </a>
          <a href="#" id="priorModalPdfLink" target="_blank" class="btn btn-sm btn-outline-primary d-none">
            <i class="ti ti-file-type-pdf me-1"></i> Abrir PDF
          </a>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($isReportEditor): ?>
            <button type="button" id="priorModalCiteBtn" class="btn btn-sm btn-primary">
              <i class="ti ti-quote me-1"></i> Citar en antecedentes
            </button>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-dark" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
  (function() {
    const modalEl = document.getElementById('priorReportModal');
    if (!modalEl) return;

    const metaEl        = document.getElementById('priorModalMeta');
    const historyWrapEl = document.getElementById('priorModalHistoryWrap');
    const historyEl     = document.getElementById('priorModalHistory');
    const findingsEl    = document.getElementById('priorModalFindings');
    const impressionEl  = document.getElementById('priorModalImpression');
    const pdfLinkEl     = document.getElementById('priorModalPdfLink');
    const compareLinkEl = document.getElementById('priorModalCompareLink');
    const citeBtnEl     = document.getElementById('priorModalCiteBtn');

    let currentCitationText = '';

    document.querySelectorAll('.js-view-prior-report').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const date        = btn.dataset.priorDate || '-';
        const modality    = btn.dataset.priorModality || '';
        const description = btn.dataset.priorDescription || '';
        const status      = btn.dataset.priorStatus || '';
        const reportedAt  = btn.dataset.priorReportedAt || '';
        const history     = (btn.dataset.priorHistory || '').trim();
        const findings    = (btn.dataset.priorFindings || '').trim();
        const impression  = (btn.dataset.priorImpression || '').trim();
        const pdfUrl      = btn.dataset.priorPdfUrl || '';
        const compareUrl  = btn.dataset.priorCompareUrl || '#';

        metaEl.textContent = [
          date,
          modality,
          description,
          status + (reportedAt && reportedAt !== '-' ? ' (' + reportedAt + ')' : '')
        ].filter(Boolean).join(' · ');

        if (history) {
          historyWrapEl.classList.remove('d-none');
          historyEl.textContent = history;
        } else {
          historyWrapEl.classList.add('d-none');
          historyEl.textContent = '';
        }

        findingsEl.textContent   = findings || 'Sin hallazgos registrados.';
        impressionEl.textContent = impression || 'Sin conclusión registrada.';

        if (compareLinkEl) {
          compareLinkEl.href = compareUrl;
        }

        if (pdfLinkEl) {
          if (pdfUrl) {
            pdfLinkEl.href = pdfUrl;
            pdfLinkEl.classList.remove('d-none');
          } else {
            pdfLinkEl.href = '#';
            pdfLinkEl.classList.add('d-none');
          }
        }

        currentCitationText = 'Estudio previo (' + date + (modality ? ' - ' + modality : '') + '): '
          + (impression || findings || description);

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
      });
    });

    if (citeBtnEl) {
      citeBtnEl.addEventListener('click', function() {
        const field = document.getElementById('clinical_history');
        if (field && currentCitationText) {
          field.value = field.value.trim()
            ? field.value.trim() + '\n' + currentCitationText
            : currentCitationText;
          field.focus();
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
      });
    }
  })();
</script>
