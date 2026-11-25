<?php
/**
 * Lista reutilizable de eventos de auditoría con filtros AJAX y paginación.
 *
 * @var list<array<string, mixed>> $logs
 * @var array{logs?: list<array<string, mixed>>, total?: int, page?: int, perPage?: int, totalPages?: int} $auditData
 * @var array{category?: string, date?: string, q?: string} $filters
 * @var string $baseUrl
 * @var string $tableId
 * @var string $cardsId
 */
$baseUrl      = $baseUrl ?? 'audit';
$filters      = $filters ?? [];
$categoryVal  = (string) ($filters['category'] ?? 'all');
$dateVal      = (string) ($filters['date'] ?? 'all');
$qVal         = (string) ($filters['q'] ?? '');

$total        = (int) ($auditData['total'] ?? count($logs));
$currentPage  = (int) ($auditData['page'] ?? 1);
$totalPages   = (int) ($auditData['totalPages'] ?? 1);

$extraParts = [];
if ($baseUrl === 'settings') {
    $extraParts[] = 'tab=audit';
}
if ($categoryVal !== 'all') {
    $extraParts[] = 'category=' . rawurlencode($categoryVal);
}
if ($dateVal !== 'all') {
    $extraParts[] = 'date=' . rawurlencode($dateVal);
}
if ($qVal !== '') {
    $extraParts[] = 'q=' . rawurlencode($qVal);
}
$extraParams  = $extraParts !== [] ? '&' . implode('&', $extraParts) : '';
$filterPrefix = esc($tableId, 'attr');
$resultsId    = $filterPrefix . 'Results';
$hasActiveFilters = ($categoryVal !== 'all' || $dateVal !== 'all' || $qVal !== '');
?>
<div class="audit-filter-container" data-base-url="<?= esc(base_url($baseUrl), 'attr') ?>" data-is-settings="<?= $baseUrl === 'settings' ? '1' : '0' ?>" data-results-id="<?= $resultsId ?>">
  <form method="get" action="<?= base_url($baseUrl) ?>" class="mb-3 audit-search-form">
    <?php if ($baseUrl === 'settings'): ?>
      <input type="hidden" name="tab" value="audit">
    <?php endif; ?>
    <div class="row g-2 mb-2">
      <div class="col-12 col-md-6">
        <select name="category" class="form-select audit-category-select" aria-label="Filtrar eventos por categoría">
          <option value="all" <?= $categoryVal === 'all' ? 'selected' : '' ?>>Todas las categorías</option>
          <option value="auth" <?= $categoryVal === 'auth' ? 'selected' : '' ?>>Sesiones (login / logout / fallos)</option>
          <option value="study" <?= $categoryVal === 'study' ? 'selected' : '' ?>>Estudios e ingesta DICOM</option>
          <option value="report" <?= $categoryVal === 'report' ? 'selected' : '' ?>>Informes</option>
          <option value="patient" <?= $categoryVal === 'patient' ? 'selected' : '' ?>>Pacientes y avisos</option>
          <option value="admin" <?= $categoryVal === 'admin' ? 'selected' : '' ?>>Sistema (usuarios, centros, ajustes)</option>
          <option value="errors" <?= $categoryVal === 'errors' ? 'selected' : '' ?>>Solo fallos y bloqueos</option>
        </select>
      </div>
      <div class="col-12 col-md-6">
        <select name="date" class="form-select audit-date-select" aria-label="Filtrar eventos por fecha">
          <option value="all" <?= $dateVal === 'all' ? 'selected' : '' ?>>Todas las fechas</option>
          <option value="today" <?= $dateVal === 'today' ? 'selected' : '' ?>>Hoy</option>
          <option value="24h" <?= $dateVal === '24h' ? 'selected' : '' ?>>Últimas 24 horas</option>
          <option value="7d" <?= $dateVal === '7d' ? 'selected' : '' ?>>Últimos 7 días</option>
          <option value="30d" <?= $dateVal === '30d' ? 'selected' : '' ?>>Últimos 30 días</option>
        </select>
      </div>
    </div>

    <div class="input-group">
      <span class="input-group-text">
        <i class="ti ti-search audit-search-icon"></i>
        <span class="spinner-border spinner-border-sm text-primary d-none audit-search-spinner" role="status" aria-label="Buscando..."></span>
      </span>
      <input type="text" name="q" value="<?= esc($qVal) ?>" class="form-control audit-search-input" placeholder="Buscar por usuario, acción, detalle o IP..." autocomplete="off">
      <a href="<?= base_url($baseUrl . ($baseUrl === 'settings' ? '?tab=audit' : '')) ?>" class="input-group-text text-muted text-decoration-none audit-search-clear <?= $hasActiveFilters ? '' : 'd-none' ?>" title="Limpiar filtros"><i class="ti ti-x"></i></a>
    </div>
  </form>

  <div id="<?= $resultsId ?>">
    <!-- Vista Escritorio -->
    <div class="mb-3 border rounded-1 table-responsive d-none d-md-block">
      <table class="table mb-0 align-middle table-hover" id="<?= $filterPrefix ?>">
        <thead>
          <tr>
            <th class="text-nowrap">Fecha</th>
            <th class="text-nowrap">Usuario</th>
            <th class="text-nowrap">Acción</th>
            <th>Detalle</th>
            <th class="text-nowrap">IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-history d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay eventos registrados con los filtros actuales.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log):
              $userDisplay  = trim((string) ($log['user_full_name'] ?? '')) ?: trim((string) ($log['user_username'] ?? ''));
              $userUsername = trim((string) ($log['user_username'] ?? ''));
              $actionCode   = (string) ($log['action'] ?? '');
              $actionLabel  = \App\Models\AuditLogModel::actionLabel($actionCode);
              $studyUid     = trim((string) ($log['study_instance_uid'] ?? ''));
            ?>
            <tr>
              <td class="text-nowrap"><?= esc(format_datetime($log['created_at'] ?? null)) ?></td>
              <td class="text-nowrap">
                <?php if ($userDisplay !== ''): ?>
                  <span class="fw-medium"><?= esc($userDisplay) ?></span><?php if ($userUsername !== '' && $userUsername !== $userDisplay): ?><span class="d-none"> <?= esc($userUsername) ?></span><?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <?php if ($studyUid !== ''): ?>
                  <a href="<?= base_url('studies/case/' . rawurlencode($studyUid)) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold text-decoration-none" title="Ir al estudio (<?= esc($actionCode) ?>)">
                    <?= esc($actionLabel) ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2 fw-semibold" title="<?= esc($actionCode) ?>">
                    <?= esc($actionLabel) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="w-100">
                <div class="text-wrap" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word;" title="<?= esc($log['details'] ?? '') ?>"><?= esc($log['details'] ?? '-') ?></div>
              </td>
              <td class="text-nowrap"><code><?= esc($log['ip_address'] ?? '-') ?></code></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none" id="<?= esc($cardsId, 'attr') ?>">
      <?php if (empty($logs)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-history d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay eventos registrados con los filtros actuales.</span>
        </div>
      <?php else: ?>
        <?php foreach ($logs as $log):
          $userDisplay  = trim((string) ($log['user_full_name'] ?? '')) ?: trim((string) ($log['user_username'] ?? ''));
          $userUsername = trim((string) ($log['user_username'] ?? ''));
          $actionCode   = (string) ($log['action'] ?? '');
          $actionLabel  = \App\Models\AuditLogModel::actionLabel($actionCode);
          $studyUid     = trim((string) ($log['study_instance_uid'] ?? ''));
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <?php if ($studyUid !== ''): ?>
                <a href="<?= base_url('studies/case/' . rawurlencode($studyUid)) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold text-decoration-none" title="Ir al estudio">
                  <?= esc($actionLabel) ?>
                </a>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2 fw-semibold">
                  <?= esc($actionLabel) ?>
                </span>
              <?php endif; ?>
              <span class="text-muted small"><?= esc(format_datetime($log['created_at'] ?? null)) ?></span>
            </div>
            <?php if ($userDisplay !== ''): ?>
              <div class="small text-muted mb-1">
                Usuario: <strong class="text-body"><?= esc($userDisplay) ?></strong><?php if ($userUsername !== '' && $userUsername !== $userDisplay): ?><span class="d-none"> <?= esc($userUsername) ?></span><?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="small mb-1"><?= esc($log['details'] ?? '-') ?></div>
            <div class="text-muted small text-break">IP: <code><?= esc($log['ip_address'] ?? '-') ?></code></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?= view('pagers/audit_full', [
      'currentPage' => $currentPage,
      'totalPages'  => $totalPages,
      'total'       => $total,
      'baseUrl'     => $baseUrl,
      'extraParams' => $extraParams,
    ]) ?>
  </div>
</div>

<script {csp-script-nonce}>
(function() {
  if (window.__auditAjaxBound) return;
  window.__auditAjaxBound = true;

  var timer = null;
  var activeController = null;

  function setLoading(container, active) {
    var icon    = container.querySelector('.audit-search-icon');
    var spinner = container.querySelector('.audit-search-spinner');
    var resWrap = document.getElementById(container.getAttribute('data-results-id'));
    if (icon) icon.classList.toggle('d-none', active);
    if (spinner) spinner.classList.toggle('d-none', !active);
    if (resWrap) resWrap.style.opacity = active ? '0.6' : '1';
  }

  function fetchAuditUrl(container, url) {
    var resultsId = container.getAttribute('data-results-id');
    var resWrap   = document.getElementById(resultsId);
    if (!resWrap) return;

    if (activeController) {
      activeController.abort();
    }
    activeController = new AbortController();
    setLoading(container, true);

    fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: activeController.signal
    })
    .then(function(resp) { return resp.text(); })
    .then(function(html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var nextWrap = doc.getElementById(resultsId);
      if (nextWrap) {
        resWrap.innerHTML = nextWrap.innerHTML;
      }
      var card = container.closest('.card');
      var curBadge = card ? card.querySelector('.card-header .badge') : null;
      var newCard = doc.getElementById(resultsId) ? doc.getElementById(resultsId).closest('.card') : null;
      var newBadge = newCard ? newCard.querySelector('.card-header .badge') : null;
      if (curBadge && newBadge) {
        curBadge.textContent = newBadge.textContent;
      }
      window.history.replaceState({}, '', url);
    })
    .catch(function(err) {
      if (err.name !== 'AbortError') {
        console.error(err);
      }
    })
    .finally(function() {
      setLoading(container, false);
    });
  }

  function runAuditFilter(container, page) {
    var baseUrl    = container.getAttribute('data-base-url');
    var isSettings = container.getAttribute('data-is-settings') === '1';
    var catEl      = container.querySelector('.audit-category-select');
    var dateEl     = container.querySelector('.audit-date-select');
    var inputEl    = container.querySelector('.audit-search-input');
    var clearEl    = container.querySelector('.audit-search-clear');

    var cat  = catEl ? catEl.value : 'all';
    var date = dateEl ? dateEl.value : 'all';
    var q    = inputEl ? inputEl.value.trim() : '';

    if (clearEl) {
      clearEl.classList.toggle('d-none', cat === 'all' && date === 'all' && q === '');
    }

    var params = new URLSearchParams();
    if (isSettings) params.set('tab', 'audit');
    if (cat !== 'all') params.set('category', cat);
    if (date !== 'all') params.set('date', date);
    if (q !== '') params.set('q', q);
    if (page && page > 1) params.set('page', String(page));

    var qs  = params.toString();
    var url = baseUrl + (qs ? '?' + qs : '');
    fetchAuditUrl(container, url);
  }

  document.addEventListener('submit', function(e) {
    var form = e.target.closest('.audit-search-form');
    if (!form) return;
    e.preventDefault();
    var container = form.closest('.audit-filter-container');
    if (container) runAuditFilter(container, 1);
  });

  document.addEventListener('change', function(e) {
    if (!e.target.classList.contains('audit-category-select') && !e.target.classList.contains('audit-date-select')) return;
    var container = e.target.closest('.audit-filter-container');
    if (container) runAuditFilter(container, 1);
  });

  document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('audit-search-input')) return;
    var container = e.target.closest('.audit-filter-container');
    if (!container) return;
    clearTimeout(timer);
    timer = setTimeout(function() {
      runAuditFilter(container, 1);
    }, 300);
  });

  document.addEventListener('click', function(e) {
    var clearBtn = e.target.closest('.audit-search-clear');
    if (clearBtn) {
      e.preventDefault();
      var container = clearBtn.closest('.audit-filter-container');
      if (!container) return;
      var catEl   = container.querySelector('.audit-category-select');
      var dateEl  = container.querySelector('.audit-date-select');
      var inputEl = container.querySelector('.audit-search-input');
      if (catEl) catEl.value = 'all';
      if (dateEl) dateEl.value = 'all';
      if (inputEl) inputEl.value = '';
      runAuditFilter(container, 1);
      return;
    }

    var pageLink = e.target.closest('.audit-filter-container a.page-link');
    if (pageLink) {
      e.preventDefault();
      var container = pageLink.closest('.audit-filter-container');
      if (container) fetchAuditUrl(container, pageLink.href);
    }
  });
})();
</script>
