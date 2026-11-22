<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Listado de estudios DICOM</h5>
    <?php if (auth()->user()?->can('studies.upload')): ?>
      <a href="<?= base_url('studies/upload') ?>" class="btn btn-sm btn-primary" title="Subir estudio">
        <i class="ti ti-cloud-upload fs-4"></i> <span class="d-none d-md-inline ms-1">Subir estudio</span>
      </a>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <form id="studiesSearchForm" method="get" action="<?= base_url('studies') ?>" class="row g-2 mb-3">
      <div class="col-12">
        <div class="input-group flex-nowrap">
          <span class="input-group-text"><i class="ti ti-calendar"></i></span>
          <div class="flex-grow-1 min-w-0">
            <select name="date_preset" id="studiesDatePreset" class="form-select" aria-label="Filtrar por fecha">
              <option value="all" <?= ($datePreset ?? 'all') === 'all' ? 'selected' : '' ?>>Fecha: Todas</option>
              <option value="today" <?= ($datePreset ?? 'all') === 'today' ? 'selected' : '' ?>>Fecha: Hoy</option>
              <option value="week" <?= ($datePreset ?? 'all') === 'week' ? 'selected' : '' ?>>Fecha: Esta semana</option>
              <option value="month" <?= ($datePreset ?? 'all') === 'month' ? 'selected' : '' ?>>Fecha: Este mes</option>
              <option value="year" <?= ($datePreset ?? 'all') === 'year' ? 'selected' : '' ?>>Fecha: Este año</option>
              <option value="custom" <?= ($datePreset ?? 'all') === 'custom' ? 'selected' : '' ?>>Fecha: Personalizado</option>
            </select>
          </div>
        </div>
      </div>
      <div id="studiesCustomDateWrap" class="col-12 <?= ($datePreset ?? 'all') === 'custom' ? '' : 'd-none' ?>">
        <div class="row g-2">
          <div class="col-12 col-sm-6">
            <div class="input-group">
              <span class="input-group-text" title="Fecha desde"><i class="ti ti-calendar"></i></span>
              <input type="text" id="studiesDateFrom" name="date_from" value="<?= esc(($datePreset ?? 'all') === 'custom' && ! empty($dateFrom) ? format_date($dateFrom, '') : '') ?>" class="form-control datepicker-input studies-filter-date" placeholder="Desde (dd/mm/aaaa)" autocomplete="off" aria-label="Fecha desde" title="Fecha desde">
            </div>
          </div>
          <div class="col-12 col-sm-6">
            <div class="input-group">
              <span class="input-group-text" title="Fecha hasta"><i class="ti ti-calendar-event"></i></span>
              <input type="text" id="studiesDateTo" name="date_to" value="<?= esc(($datePreset ?? 'all') === 'custom' && ! empty($dateTo) ? format_date($dateTo, '') : '') ?>" class="form-control datepicker-input studies-filter-date" placeholder="Hasta (dd/mm/aaaa)" autocomplete="off" aria-label="Fecha hasta" title="Fecha hasta">
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="input-group">
          <span class="input-group-text"><i class="ti ti-search"></i></span>
          <input type="text" id="studiesSearchInput" name="q" value="<?= esc($search ?? '') ?>" class="form-control" placeholder="Buscar por paciente, ID o número de caso..." autocomplete="off">
          <a href="<?= base_url('studies') ?>" id="studiesSearchClear" class="input-group-text text-muted text-decoration-none <?= (empty($search) && ($datePreset ?? 'all') === 'all') ? 'd-none' : '' ?>" title="Limpiar búsqueda"><i class="ti ti-x"></i></a>
        </div>
      </div>
    </form>

    <div id="studiesResults">
    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="studiesTable" style="table-layout: fixed;">
        <thead>
          <tr>
            <th style="width: 105px;">Fecha</th>
            <th style="width: 24%;">Paciente</th>
            <th class="text-center" style="width: 105px;">Modalidad</th>
            <th>Descripción</th>
            <th class="text-center" style="width: 105px;">Prioridad</th>
            <th class="text-center" style="width: 110px;">Informe</th>
            <th class="text-end pe-3" style="width: 76px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($studies)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-report-medical d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay estudios disponibles para tu cuenta todavía.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($studies as $study):
              $tags      = $study['MainDicomTags'] ?? [];
              $patient   = $study['PatientMainDicomTags'] ?? [];
              $uid       = $tags['StudyInstanceUID'] ?? '';
              $record    = $study['_record'] ?? null;
              $report    = $study['_report'] ?? null;
              $repStatus = $report['status'] ?? 'pending';
              $modality  = extract_study_modality($study);

              [$badgeClass, $repLabel] = match ($repStatus) {
                  'reported'  => ['bg-success-subtle text-success border border-success-subtle fs-2', 'Informado'],
                  'in_review' => ['bg-warning-subtle text-warning border border-warning-subtle fs-2', 'En revisión'],
                  default     => ['bg-info-subtle text-info border border-info-subtle fs-2', 'Pendiente'],
              };
            ?>
            <?php
              $patientId   = (string) ($patient['PatientID'] ?? '-');
              $patientName = format_patient_name($patient['PatientName'] ?? null);
              $studyDesc   = format_study_description($tags['StudyDescription'] ?? null);
            ?>
            <tr class="cursor-pointer" onclick="window.location.href='<?= base_url('studies/case/' . urlencode($uid)) ?>'">
              <td><?= esc(format_date($tags['StudyDate'] ?? null)) ?></td>
              <td>
                <div class="fw-semibold text-body text-truncate" title="<?= esc($patientName) ?>"><?= esc($patientName) ?></div>
                <div class="text-muted small text-truncate" title="<?= esc($patientId) ?>">ID: <?= esc($patientId) ?></div>
              </td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              </td>
              <td class="text-truncate" title="<?= esc($studyDesc) ?>">
                <?= esc($studyDesc) ?>
              </td>
              <td class="text-center">
                <?php if (($record['priority'] ?? 'normal') === 'urgent'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold">Urgente</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Normal</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><span class="badge <?= $badgeClass ?> fw-semibold"><?= $repLabel ?></span></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= ohif_url($uid) ?>" target="_blank">
                        <i class="ti ti-eye fs-5"></i> Abrir en OHIF
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/case/' . urlencode($uid)) ?>">
                        <i class="ti ti-paperclip fs-5"></i> Ficha y adjuntos
                      </a>
                    </li>
                    <?php if ($repStatus === 'reported'): ?>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/pdf/' . urlencode($uid)) ?>" target="_blank">
                          <i class="ti ti-file-type-pdf fs-5"></i> Ver Informe PDF
                        </a>
                      </li>
                    <?php endif; ?>
                    <?php if (auth()->user()?->can('studies.share')): ?>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/share/' . urlencode($uid)) ?>">
                          <i class="ti ti-share fs-5"></i> Compartir con PIN<?= ! empty($study['_active_shares']) ? ' (' . (int) $study['_active_shares'] . ')' : '' ?>
                        </a>
                      </li>
                    <?php endif; ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/download/' . urlencode($uid)) ?>">
                        <i class="ti ti-download fs-5"></i> Descargar DICOM (.zip)
                      </a>
                    </li>
                    <?php if (auth()->user()?->can('users.manage')): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="<?= base_url('studies/delete/' . urlencode($uid)) ?>" method="post" class="delete-study-form m-0">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2">
                            <i class="ti ti-trash fs-5"></i> Eliminar estudio
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

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none" id="studiesCards">
      <?php if (empty($studies)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-report-medical d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay estudios disponibles todavía.</span>
        </div>
      <?php else: ?>
        <?php foreach ($studies as $study):
          $tags      = $study['MainDicomTags'] ?? [];
          $patient   = $study['PatientMainDicomTags'] ?? [];
          $uid       = $tags['StudyInstanceUID'] ?? '';
          $record    = $study['_record'] ?? null;
          $report    = $study['_report'] ?? null;
          $repStatus = $report['status'] ?? 'pending';
          $modality  = extract_study_modality($study);
          [$badgeClass, $repLabel] = match ($repStatus) {
              'reported'  => ['bg-success-subtle text-success border border-success-subtle fs-2', 'Informado'],
              'in_review' => ['bg-warning-subtle text-warning border border-warning-subtle fs-2', 'En revisión'],
              default     => ['bg-info-subtle text-info border border-info-subtle fs-2', 'Pendiente'],
          };
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item cursor-pointer" onclick="window.location.href='<?= base_url('studies/case/' . urlencode($uid)) ?>'">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc(format_patient_name($patient['PatientName'] ?? null)) ?></strong>
                <span class="text-muted small"><?= esc(format_date($tags['StudyDate'] ?? null)) ?> · <?= esc(format_study_description($tags['StudyDescription'] ?? null)) ?></span>
              </div>
              <div class="dropdown dropstart" onclick="event.stopPropagation();">
                <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= ohif_url($uid) ?>" target="_blank">
                      <i class="ti ti-eye fs-5"></i> Abrir en OHIF
                    </a>
                  </li>
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/case/' . urlencode($uid)) ?>">
                      <i class="ti ti-paperclip fs-5"></i> Ficha y adjuntos
                    </a>
                  </li>
                  <?php if ($repStatus === 'reported'): ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/pdf/' . urlencode($uid)) ?>" target="_blank">
                        <i class="ti ti-file-type-pdf fs-5"></i> Ver Informe PDF
                      </a>
                    </li>
                  <?php endif; ?>
                  <?php if (auth()->user()?->can('studies.share')): ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/share/' . urlencode($uid)) ?>">
                        <i class="ti ti-share fs-5"></i> Compartir con PIN
                      </a>
                    </li>
                  <?php endif; ?>
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/download/' . urlencode($uid)) ?>">
                      <i class="ti ti-download fs-5"></i> Descargar DICOM (.zip)
                    </a>
                  </li>
                  <?php if (auth()->user()?->can('users.manage')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form action="<?= base_url('studies/delete/' . urlencode($uid)) ?>" method="post" class="delete-study-form m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2">
                          <i class="ti ti-trash fs-5"></i> Eliminar estudio
                        </button>
                      </form>
                    </li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              <span class="badge <?= $badgeClass ?> fw-semibold"><?= $repLabel ?></span>
              <?php if (($record['priority'] ?? 'normal') === 'urgent'): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold">Urgente</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Normal</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php
      $qParam = (! empty($search) ? '&q=' . urlencode((string) $search) : '')
          . (($datePreset ?? 'all') !== 'all' ? '&date_preset=' . urlencode((string) $datePreset) : '')
          . (($datePreset ?? 'all') === 'custom' && ! empty($dateFrom) ? '&date_from=' . urlencode((string) $dateFrom) : '')
          . (($datePreset ?? 'all') === 'custom' && ! empty($dateTo) ? '&date_to=' . urlencode((string) $dateTo) : '');
    ?>
    <?= view('pagers/orthanc_full', [
        'currentPage'  => $page ?? 1,
        'perPage'      => $perPage ?? 25,
        'hasNext'      => $hasNext ?? false,
        'totalStudies' => $totalStudies ?? null,
        'baseUrl'      => 'studies',
        'extraParams'  => $qParam,
    ]) ?>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
  document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('studiesSearchForm');
    var input = document.getElementById('studiesSearchInput');
    var datePresetSelect = document.getElementById('studiesDatePreset');
    var customDateWrap = document.getElementById('studiesCustomDateWrap');
    var dateFromInput = document.getElementById('studiesDateFrom');
    var dateToInput = document.getElementById('studiesDateTo');
    var clearBtn = document.getElementById('studiesSearchClear');
    var results = document.getElementById('studiesResults');
    var timer = null;
    var controller = null;

    function runSearch() {
      if (!form || !input || !results) return;
      var q = input.value.trim();
      var preset = datePresetSelect ? datePresetSelect.value : 'all';
      var fromVal = (preset === 'custom' && dateFromInput) ? dateFromInput.value : '';
      var toVal = (preset === 'custom' && dateToInput) ? dateToInput.value : '';
      if (clearBtn) clearBtn.classList.toggle('d-none', q === '' && preset === 'all');
      var params = new URLSearchParams();
      if (q !== '') params.set('q', q);
      if (preset !== 'all') params.set('date_preset', preset);
      if (fromVal !== '') params.set('date_from', fromVal);
      if (toVal !== '') params.set('date_to', toVal);
      var qs = params.toString();
      var url = form.action + (qs !== '' ? '?' + qs : '');
      if (controller) controller.abort();
      controller = new AbortController();
      results.style.opacity = '0.6';
      fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.text(); })
        .then(function(html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var next = doc.getElementById('studiesResults');
          if (next) results.innerHTML = next.innerHTML;
          results.style.opacity = '1';
          history.replaceState(null, '', url);
        })
        .catch(function(err) {
          if (err.name !== 'AbortError') results.style.opacity = '1';
        });
    }

    if (form && input) {
      input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(runSearch, 350);
      });
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        clearTimeout(timer);
        runSearch();
      });
      if (datePresetSelect) {
        function handlePresetChange() {
          var isCustom = datePresetSelect.value === 'custom';
          if (customDateWrap) customDateWrap.classList.toggle('d-none', !isCustom);
          if (!isCustom) {
            if (dateFromInput) dateFromInput.value = '';
            if (dateToInput) dateToInput.value = '';
            runSearch();
          } else {
            if (dateFromInput && !dateFromInput.value && dateToInput && !dateToInput.value) {
              dateFromInput.focus();
            } else {
              runSearch();
            }
          }
        }
        if (window.jQuery) {
          window.jQuery(datePresetSelect).on('change', handlePresetChange);
        } else {
          datePresetSelect.addEventListener('change', handlePresetChange);
        }
      }
      form.querySelectorAll('.studies-filter-date').forEach(function(el) {
        el.addEventListener('change', runSearch);
      });
    }

    if (results) {
      results.addEventListener('submit', function(e) {
        var delForm = e.target.closest('.delete-study-form');
        if (!delForm) return;
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: '¿Eliminar estudio?',
            text: 'Se borrarán imágenes, informes, adjuntos y enlaces. No se puede deshacer.',
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
              delForm.submit();
            }
          });
        } else if (confirm('¿Eliminar este estudio?')) {
          delForm.submit();
        }
      });
    }
  });
</script>

<?= view('template/footer') ?>
