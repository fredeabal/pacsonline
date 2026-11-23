<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Worklist de informes médicos</h5>
  </div>

  <div class="card-body">
    <form id="worklistFilterForm" method="get" action="<?= base_url('reports') ?>" class="row g-2 mb-3">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="input-group flex-nowrap">
          <span class="input-group-text"><i class="ti ti-user-check"></i></span>
          <div class="flex-grow-1 min-w-0">
            <select name="scope" class="form-select worklist-filter-select" aria-label="Filtrar por asignación">
              <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>Asignación: Todos</option>
              <option value="mine" <?= $scope === 'mine' ? 'selected' : '' ?>>Asignación: Mis asignados</option>
            </select>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="input-group flex-nowrap">
          <span class="input-group-text"><i class="ti ti-list-check"></i></span>
          <div class="flex-grow-1 min-w-0">
            <select name="status" class="form-select worklist-filter-select" aria-label="Filtrar por estado">
              <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Estado: Todos</option>
              <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Estado: Pendientes</option>
              <option value="in_review" <?= $filter === 'in_review' ? 'selected' : '' ?>>Estado: En revisión</option>
              <option value="reported" <?= $filter === 'reported' ? 'selected' : '' ?>>Estado: Informados</option>
            </select>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="input-group flex-nowrap">
          <span class="input-group-text"><i class="ti ti-scan"></i></span>
          <div class="flex-grow-1 min-w-0">
            <select name="modality" class="form-select worklist-filter-select" aria-label="Filtrar por modalidad">
              <option value="ALL" <?= $modalityFilter === 'ALL' ? 'selected' : '' ?>>Modalidad: Todas</option>
              <?php foreach ($availableModalities as $mod): ?>
                <option value="<?= esc($mod, 'attr') ?>" <?= $modalityFilter === $mod ? 'selected' : '' ?>>Modalidad: <?= esc($mod) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="input-group flex-nowrap">
          <span class="input-group-text"><i class="ti ti-calendar"></i></span>
          <div class="flex-grow-1 min-w-0">
            <select name="date_preset" id="reportsDatePreset" class="form-select worklist-filter-date-preset" aria-label="Filtrar por fecha">
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
      <div id="reportsCustomDateWrap" class="col-12 <?= ($datePreset ?? 'all') === 'custom' ? '' : 'd-none' ?>">
        <div class="row g-2">
          <div class="col-12 col-sm-6">
            <div class="input-group">
              <span class="input-group-text" title="Fecha desde"><i class="ti ti-calendar"></i></span>
              <input type="text" id="reportsDateFrom" name="date_from" value="<?= esc(($datePreset ?? 'all') === 'custom' && ! empty($dateFrom) ? format_date($dateFrom, '') : '') ?>" class="form-control datepicker-input worklist-filter-date" placeholder="Desde (dd/mm/aaaa)" autocomplete="off" aria-label="Fecha desde" title="Fecha desde">
            </div>
          </div>
          <div class="col-12 col-sm-6">
            <div class="input-group">
              <span class="input-group-text" title="Fecha hasta"><i class="ti ti-calendar-event"></i></span>
              <input type="text" id="reportsDateTo" name="date_to" value="<?= esc(($datePreset ?? 'all') === 'custom' && ! empty($dateTo) ? format_date($dateTo, '') : '') ?>" class="form-control datepicker-input worklist-filter-date" placeholder="Hasta (dd/mm/aaaa)" autocomplete="off" aria-label="Fecha hasta" title="Fecha hasta">
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="input-group">
          <span class="input-group-text" id="reportsSearchIconWrap">
            <i class="ti ti-search" id="reportsSearchIcon"></i>
            <span class="spinner-border spinner-border-sm text-primary d-none" id="reportsSearchSpinner" role="status" aria-label="Buscando..."></span>
          </span>
          <input type="text" id="reportsSearchInput" name="q" value="<?= esc($search ?? '') ?>" class="form-control" placeholder="Buscar por paciente o ID..." autocomplete="off">
          <a href="<?= base_url('reports') ?>" id="reportsSearchClear" class="input-group-text text-muted text-decoration-none <?= (empty($search) && ($datePreset ?? 'all') === 'all') ? 'd-none' : '' ?>" title="Limpiar búsqueda"><i class="ti ti-x"></i></a>
        </div>
      </div>
    </form>

    <div id="reportsResults">
    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="reportsTable" style="table-layout: fixed;">
        <thead>
          <tr>
            <th style="width: 105px;">Fecha</th>
            <th style="width: 24%;">Paciente</th>
            <th class="text-center" style="width: 105px;">Modalidad</th>
            <th>Descripción</th>
            <th class="text-center" style="width: 105px;">Prioridad</th>
            <th class="text-center" style="width: 110px;">Estado</th>
            <th class="text-end pe-3" style="width: 76px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-stethoscope d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay estudios en esta bandeja.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row):
              $tags     = $row['study']['MainDicomTags'] ?? [];
              $patient  = $row['study']['PatientMainDicomTags'] ?? [];
              $uid      = $row['uid'];
              $status   = $row['status'];
              $modality = $row['modality'] ?? 'DICOM';
              $attCount = (int) ($row['attachments_count'] ?? 0);

              [$badgeClass, $statusLabel] = match ($status) {
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
                <div class="fw-semibold text-truncate" title="<?= esc($patientName) ?>"><?= esc($patientName) ?></div>
                <div class="text-muted small text-truncate" title="<?= esc($patientId) ?>">ID: <?= esc($patientId) ?></div>
              </td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              </td>
              <td class="text-truncate" title="<?= esc($studyDesc) ?>">
                <?= esc($studyDesc) ?>
              </td>
              <td class="text-center">
                <?php if (($row['priority'] ?? 'normal') === 'urgent'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold">Urgente</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Normal</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><span class="badge <?= $badgeClass ?> fw-semibold"><?= $statusLabel ?></span></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/edit/' . urlencode($uid)) ?>">
                        <i class="ti ti-stethoscope fs-5"></i> <?= $status === 'reported' ? 'Editar informe' : 'Informar estudio' ?>
                      </a>
                    </li>
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
                    <?php if ($status === 'reported'): ?>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/pdf/' . urlencode($uid)) ?>" target="_blank">
                          <i class="ti ti-file-type-pdf fs-5"></i> Ver Informe PDF
                        </a>
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
    <div class="d-block d-md-none" id="reportsCards">
      <?php if (empty($rows)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-stethoscope d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay estudios en esta bandeja.</span>
        </div>
      <?php else: ?>
        <?php foreach ($rows as $row):
          $tags     = $row['study']['MainDicomTags'] ?? [];
          $patient  = $row['study']['PatientMainDicomTags'] ?? [];
          $uid      = $row['uid'];
          $status   = $row['status'];
          $modality = $row['modality'] ?? 'DICOM';
          $attCount = (int) ($row['attachments_count'] ?? 0);
          [$badgeClass, $statusLabel] = match ($status) {
              'reported'  => ['bg-success-subtle text-success border border-success-subtle fs-2', 'Informado'],
              'in_review' => ['bg-warning-subtle text-warning border border-warning-subtle fs-2', 'En revisión'],
              default     => ['bg-info-subtle text-info border border-info-subtle fs-2', 'Pendiente'],
          };
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item cursor-pointer" onclick="window.location.href='<?= base_url('reports/edit/' . urlencode($uid)) ?>'">
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
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/edit/' . urlencode($uid)) ?>">
                      <i class="ti ti-stethoscope fs-5"></i> <?= $status === 'reported' ? 'Editar informe' : 'Informar estudio' ?>
                    </a>
                  </li>
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
                  <?php if ($status === 'reported'): ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/pdf/' . urlencode($uid)) ?>" target="_blank">
                        <i class="ti ti-file-type-pdf fs-5"></i> Ver Informe PDF
                      </a>
                    </li>
                  <?php endif; ?>
                </ul>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              <span class="badge <?= $badgeClass ?> fw-semibold"><?= $statusLabel ?></span>
              <?php if (($row['priority'] ?? 'normal') === 'urgent'): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold">Urgente</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Normal</span>
              <?php endif; ?>
              <?php if ($attCount > 0): ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle fs-2">
                  <i class="ti ti-paperclip"></i> <?= $attCount ?> adj.
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php
      $extraParams = '&scope=' . urlencode((string) $scope)
          . '&status=' . urlencode((string) $filter)
          . '&modality=' . urlencode((string) $modalityFilter)
          . (! empty($search) ? '&q=' . urlencode((string) $search) : '')
          . (($datePreset ?? 'all') !== 'all' ? '&date_preset=' . urlencode((string) $datePreset) : '')
          . (($datePreset ?? 'all') === 'custom' && ! empty($dateFrom) ? '&date_from=' . urlencode((string) $dateFrom) : '')
          . (($datePreset ?? 'all') === 'custom' && ! empty($dateTo) ? '&date_to=' . urlencode((string) $dateTo) : '');
    ?>
    <?= view('pagers/orthanc_full', [
        'currentPage'  => $page ?? 1,
        'perPage'      => $perPage ?? 25,
        'hasNext'      => $hasNext ?? false,
        'totalStudies' => $totalStudies ?? null,
        'baseUrl'      => 'reports',
        'extraParams'  => $extraParams,
    ]) ?>
    </div>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  (function() {
    var form = document.getElementById('worklistFilterForm');
    var input = document.getElementById('reportsSearchInput');
    var datePresetSelect = document.getElementById('reportsDatePreset');
    var customDateWrap = document.getElementById('reportsCustomDateWrap');
    var dateFromInput = document.getElementById('reportsDateFrom');
    var dateToInput = document.getElementById('reportsDateTo');
    var clearBtn = document.getElementById('reportsSearchClear');
    var searchIcon = document.getElementById('reportsSearchIcon');
    var searchSpinner = document.getElementById('reportsSearchSpinner');
    var results = document.getElementById('reportsResults');
    if (!form || !results) return;

    var timer = null;
    var controller = null;

    function setSearching(active) {
      if (searchIcon) searchIcon.classList.toggle('d-none', active);
      if (searchSpinner) searchSpinner.classList.toggle('d-none', !active);
      results.style.opacity = active ? '0.6' : '1';
    }

    function runFilter() {
      var params = new URLSearchParams(new FormData(form));
      if (datePresetSelect && datePresetSelect.value !== 'custom') {
        params.delete('date_from');
        params.delete('date_to');
      }
      if (clearBtn) {
        var hasText = input && input.value.trim() !== '';
        var hasDate = datePresetSelect && datePresetSelect.value !== 'all';
        clearBtn.classList.toggle('d-none', !hasText && !hasDate);
      }
      var qs = params.toString();
      var url = form.action + (qs ? '?' + qs : '');
      if (controller) controller.abort();
      controller = new AbortController();
      var currentController = controller;
      setSearching(true);
      fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.text(); })
        .then(function(html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          var next = doc.getElementById('reportsResults');
          if (next) results.innerHTML = next.innerHTML;
          if (controller === currentController) setSearching(false);
          history.replaceState(null, '', url);
        })
        .catch(function(err) {
          if (err.name !== 'AbortError' && controller === currentController) setSearching(false);
        });
    }

    if (input) {
      input.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(runFilter, 350);
      });
    }

    form.addEventListener('submit', function(e) {
      e.preventDefault();
      clearTimeout(timer);
      runFilter();
    });

    if (window.jQuery) {
      window.jQuery(form).on('change', '.worklist-filter-select', function() {
        runFilter();
      });
    } else {
      form.querySelectorAll('.worklist-filter-select').forEach(function(sel) {
        sel.addEventListener('change', runFilter);
      });
    }

    if (datePresetSelect) {
      function handlePresetChange() {
        var isCustom = datePresetSelect.value === 'custom';
        if (customDateWrap) customDateWrap.classList.toggle('d-none', !isCustom);
        if (!isCustom) {
          if (dateFromInput) dateFromInput.value = '';
          if (dateToInput) dateToInput.value = '';
          runFilter();
        } else {
          if (dateFromInput && !dateFromInput.value && dateToInput && !dateToInput.value) {
            dateFromInput.focus();
          } else {
            runFilter();
          }
        }
      }

      if (window.jQuery) {
        window.jQuery(datePresetSelect).on('change', handlePresetChange);
      } else {
        datePresetSelect.addEventListener('change', handlePresetChange);
      }
    }

    form.querySelectorAll('.worklist-filter-date').forEach(function(el) {
      el.addEventListener('change', runFilter);
    });
  })();
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
