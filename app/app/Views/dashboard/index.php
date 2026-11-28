<?php /** Vista principal del Dashboard adaptada al rol: Supervisor, Médico, Usuario u Observador. */ ?>
<?= view('template/header') ?>

<div class="row">
  <div class="col-12 mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <?php if ($roleMode === 'medico'): ?>
            <h4 class="fw-bold mb-1">Panel Médico<?= ! empty($scopedCenterName) ? ' · ' . esc($scopedCenterName) : '' ?></h4>
            <p class="text-muted mb-0">Revisa los estudios pendientes en tu Worklist y emite informes médicos.</p>
          <?php elseif ($roleMode === 'usuario'): ?>
            <h4 class="fw-bold mb-1">Panel de Gestión PACS<?= ! empty($scopedCenterName) ? ' · ' . esc($scopedCenterName) : '' ?></h4>
            <p class="text-muted mb-0">Consulta estudios, sube archivos DICOM, reenvía a nodos y genera enlaces de envío para pacientes.</p>
          <?php elseif ($roleMode === 'observador'): ?>
            <h4 class="fw-bold mb-1">Panel de Consulta PACS<?= ! empty($scopedCenterName) ? ' · ' . esc($scopedCenterName) : '' ?></h4>
            <p class="text-muted mb-0">Visualiza los casos clínicos, el visor DICOM y los informes médicos publicados.</p>
          <?php else: ?>
            <h4 class="fw-bold mb-1">Panel de Supervisión PACS</h4>
            <p class="text-muted mb-0">Gestiona estudios, informes médicos, usuarios, centros remitentes y configuración de la plataforma.</p>
          <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($roleMode === 'medico'): ?>
            <a href="<?= base_url('reports') ?>" class="btn btn-sm btn-primary">
              <i class="ti ti-stethoscope me-1"></i> Ir al Worklist
            </a>
            <a href="<?= base_url('templates') ?>" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-template me-1"></i> Mis Plantillas
            </a>
          <?php elseif ($roleMode === 'usuario'): ?>
            <a href="<?= base_url('studies/upload') ?>" class="btn btn-sm btn-primary">
              <i class="ti ti-cloud-upload me-1"></i> Subir Estudio
            </a>
            <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-report-medical me-1"></i> Ver Estudios
            </a>
          <?php elseif ($roleMode === 'observador'): ?>
            <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-primary">
              <i class="ti ti-report-medical me-1"></i> Ver Estudios
            </a>
          <?php else: ?>
            <a href="<?= base_url('studies/upload') ?>" class="btn btn-sm btn-primary">
              <i class="ti ti-cloud-upload me-1"></i> Subir Estudio
            </a>
            <a href="<?= base_url('reports') ?>" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-stethoscope me-1"></i> Informar
            </a>
            <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-outline-primary">
              <i class="ti ti-report-medical me-1"></i> Ver Estudios
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div id="dashboardAlerts" class="<?= empty($alerts) ? 'd-none' : 'mb-3' ?>">
  <?php foreach (($alerts ?? []) as $alert): ?>
    <div class="alert alert-<?= esc($alert['level'] ?? 'warning') ?> d-flex align-items-center gap-2 mb-2 border-0 shadow-sm" role="alert">
      <i class="ti <?= esc($alert['icon'] ?? 'ti-alert-triangle') ?> fs-5 flex-shrink-0"></i>
      <div>
        <strong class="me-1"><?= esc($alert['title'] ?? '') ?>:</strong>
        <span><?= esc($alert['message'] ?? '') ?></span>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <?php if ($roleMode === 'observador'): ?>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-primary-subtle text-primary p-3"><i class="ti ti-report-medical fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Estudios en PACS</span>
            <h3 class="fw-bold mb-0" data-metric="totalStudies"><?= (int) $totalStudies ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-success-subtle text-success p-3"><i class="ti ti-file-check fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Informes Publicados</span>
            <h3 class="fw-bold mb-0" data-metric="reportedCount"><?= (int) $reportedCount ?></h3>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($roleMode === 'usuario'): ?>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-primary-subtle text-primary p-3"><i class="ti ti-report-medical fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Estudios en PACS</span>
            <h3 class="fw-bold mb-0" data-metric="totalStudies"><?= (int) $totalStudies ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-info-subtle text-info p-3"><i class="ti ti-clock fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Pendientes por Informar</span>
            <h3 class="fw-bold mb-0" data-metric="pendingCount"><?= (int) $pendingCount ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-success-subtle text-success p-3"><i class="ti ti-file-check fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Informados</span>
            <h3 class="fw-bold mb-0" data-metric="reportedCount"><?= (int) $reportedCount ?></h3>
          </div>
        </div>
      </div>
    </div>
  <?php elseif ($roleMode === 'medico'): ?>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-info-subtle text-info p-3"><i class="ti ti-clock fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Pendientes por Informar</span>
            <h3 class="fw-bold mb-0" data-metric="pendingCount"><?= (int) $pendingCount ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-danger-subtle text-danger p-3"><i class="ti ti-alert-triangle fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Urgentes</span>
            <h3 class="fw-bold mb-0" data-metric="urgentCount"><?= (int) $urgentCount ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-success-subtle text-success p-3"><i class="ti ti-file-check fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Informados</span>
            <h3 class="fw-bold mb-0" data-metric="reportedCount"><?= (int) $reportedCount ?></h3>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-primary-subtle text-primary p-3 flex-shrink-0"><i class="ti ti-report-medical fs-7"></i></span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Estudios en PACS</span>
            <h3 class="fw-bold mb-0" data-metric="countStudies"><?= number_format((int) ($orthancStats['CountStudies'] ?? $totalStudies), 0, ',', '.') ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-info-subtle text-info p-3 flex-shrink-0"><i class="ti ti-users fs-7"></i></span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Pacientes</span>
            <h3 class="fw-bold mb-0" data-metric="countPatients"><?= number_format((int) ($orthancStats['CountPatients'] ?? 0), 0, ',', '.') ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-success-subtle text-success p-3 flex-shrink-0"><i class="ti ti-folders fs-7"></i></span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Series</span>
            <h3 class="fw-bold mb-0" data-metric="countSeries"><?= number_format((int) ($orthancStats['CountSeries'] ?? 0), 0, ',', '.') ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-warning-subtle text-warning p-3 flex-shrink-0"><i class="ti ti-photo fs-7"></i></span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Imágenes DICOM</span>
            <h3 class="fw-bold mb-0" data-metric="countInstances"><?= number_format((int) ($orthancStats['CountInstances'] ?? 0), 0, ',', '.') ?></h3>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-secondary-subtle text-secondary p-3 flex-shrink-0"><i class="ti ti-database fs-7"></i></span>
          <div class="min-w-0 flex-grow-1">
            <span class="text-muted fs-2 d-block">Almacenamiento</span>
            <?php if (! empty($diskStats)): ?>
              <?php
                $usedPct   = (float) $diskStats['used_pct'];
                $freePct   = (float) $diskStats['free_pct'];
                $usedTb    = $diskStats['used_bytes'] / (1024 ** 4);
                $totalTb   = $diskStats['total_bytes'] / (1024 ** 4);
                $freeTb    = $diskStats['free_bytes'] / (1024 ** 4);
                $barClass  = $usedPct >= 85 ? 'bg-danger' : ($usedPct >= 70 ? 'bg-warning' : 'bg-primary');
              ?>
              <div class="d-flex align-items-baseline justify-content-between gap-2">
                <h3 class="fw-bold mb-0" data-metric="diskUsedPct"><?= number_format($usedPct, 1, ',', '.') ?>% ocupado</h3>
                <span class="text-muted small" data-metric="diskFreePct"><?= number_format($freePct, 1, ',', '.') ?>% libre</span>
              </div>
              <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar <?= $barClass ?>" data-metric="diskBar" role="progressbar" style="width: <?= min(100, max(0, $usedPct)) ?>%;" aria-valuenow="<?= $usedPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <span class="text-muted small d-block mt-1" data-metric="diskSummary">
                <?= number_format($usedTb, 2, ',', '.') ?> TB / <?= number_format($totalTb, 2, ',', '.') ?> TB (<?= number_format($freeTb, 2, ',', '.') ?> TB libres)
              </span>
            <?php else: ?>
              <h3 class="fw-bold mb-0" data-metric="orthancDiskFallback">
                <?php
                  $mb = (float) ($orthancStats['TotalDiskSizeMB'] ?? 0);
                  if ($mb >= 1024 * 1024) {
                      echo number_format($mb / (1024 * 1024), 2, ',', '.') . ' TB';
                  } elseif ($mb >= 1024) {
                      echo number_format($mb / 1024, 1, ',', '.') . ' GB';
                  } else {
                      echo number_format($mb, 0, ',', '.') . ' MB';
                  }
                ?>
              </h3>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 <?= $orthancAlive ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-3 flex-shrink-0" data-metric="orthancStatusIcon">
            <i class="ti ti-server fs-7"></i>
          </span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Servidor PACS</span>
            <h4 class="fw-bold mb-0 <?= $orthancAlive ? 'text-success' : 'text-danger' ?>" data-metric="orthancStatusText"><?= $orthancAlive ? 'Conectado' : 'Sin conexión' ?></h4>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const METRICS_URL = <?= json_encode(base_url('dashboard/metrics')) ?>;
  const REFRESH_INTERVAL_MS = 15000;
  const intFmt = new Intl.NumberFormat('es-ES');
  const dec1Fmt = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const dec2Fmt = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  let inFlight = false;
  let lastFetchAt = Date.now();

  function setText(metric, value) {
    document.querySelectorAll('[data-metric="' + metric + '"]').forEach(function (el) {
      el.textContent = value;
    });
  }

  function formatMb(mb) {
    if (mb >= 1024 * 1024) return dec2Fmt.format(mb / (1024 * 1024)) + ' TB';
    if (mb >= 1024) return dec1Fmt.format(mb / 1024) + ' GB';
    return intFmt.format(Math.round(mb)) + ' MB';
  }

  function applyMetrics(data) {
    setText('totalStudies', String(Number(data.totalStudies || 0)));
    setText('reportedCount', String(Number(data.reportedCount || 0)));
    setText('pendingCount', String(Number(data.pendingCount || 0)));
    setText('urgentCount', String(Number(data.urgentCount || 0)));

    const stats = data.orthancStats || {};
    setText('countStudies', intFmt.format(Number(stats.CountStudies ?? data.totalStudies ?? 0)));
    setText('countPatients', intFmt.format(Number(stats.CountPatients || 0)));
    setText('countSeries', intFmt.format(Number(stats.CountSeries || 0)));
    setText('countInstances', intFmt.format(Number(stats.CountInstances || 0)));

    const disk = data.diskStats;
    if (disk) {
      const usedPct = Number(disk.used_pct || 0);
      const freePct = Number(disk.free_pct || 0);
      const tbDiv = 1024 ** 4;
      setText('diskUsedPct', dec1Fmt.format(usedPct) + '% ocupado');
      setText('diskFreePct', dec1Fmt.format(freePct) + '% libre');
      setText(
        'diskSummary',
        dec2Fmt.format(Number(disk.used_bytes || 0) / tbDiv) + ' TB / ' +
        dec2Fmt.format(Number(disk.total_bytes || 0) / tbDiv) + ' TB (' +
        dec2Fmt.format(Number(disk.free_bytes || 0) / tbDiv) + ' TB libres)'
      );
      const bar = document.querySelector('[data-metric="diskBar"]');
      if (bar) {
        const clamped = Math.min(100, Math.max(0, usedPct));
        bar.style.width = clamped + '%';
        bar.setAttribute('aria-valuenow', String(usedPct));
        bar.classList.remove('bg-danger', 'bg-warning', 'bg-primary');
        bar.classList.add(usedPct >= 85 ? 'bg-danger' : (usedPct >= 70 ? 'bg-warning' : 'bg-primary'));
      }
    } else if (stats.TotalDiskSizeMB !== undefined) {
      setText('orthancDiskFallback', formatMb(Number(stats.TotalDiskSizeMB || 0)));
    }

    const alive = Boolean(data.orthancAlive);
    const icon = document.querySelector('[data-metric="orthancStatusIcon"]');
    const text = document.querySelector('[data-metric="orthancStatusText"]');
    if (icon) {
      icon.classList.toggle('bg-success-subtle', alive);
      icon.classList.toggle('text-success', alive);
      icon.classList.toggle('bg-danger-subtle', !alive);
      icon.classList.toggle('text-danger', !alive);
    }
    if (text) {
      text.textContent = alive ? 'Conectado' : 'Sin conexión';
      text.classList.toggle('text-success', alive);
      text.classList.toggle('text-danger', !alive);
    }

    const alertsBox = document.getElementById('dashboardAlerts');
    if (alertsBox && Array.isArray(data.alerts)) {
      alertsBox.replaceChildren();
      if (data.alerts.length === 0) {
        alertsBox.classList.add('d-none');
        alertsBox.classList.remove('mb-3');
      } else {
        alertsBox.classList.remove('d-none');
        alertsBox.classList.add('mb-3');
        data.alerts.forEach(function (a) {
          const level = a.level === 'danger' ? 'danger' : 'warning';
          const wrap = document.createElement('div');
          wrap.className = 'alert alert-' + level + ' d-flex align-items-center gap-2 mb-2 border-0 shadow-sm';
          wrap.setAttribute('role', 'alert');

          const ic = document.createElement('i');
          ic.className = 'ti ' + (a.icon || 'ti-alert-triangle') + ' fs-5 flex-shrink-0';
          const body = document.createElement('div');
          const strong = document.createElement('strong');
          strong.className = 'me-1';
          strong.textContent = (a.title || '') + ':';
          const span = document.createElement('span');
          span.textContent = a.message || '';

          body.appendChild(strong);
          body.appendChild(span);
          wrap.appendChild(ic);
          wrap.appendChild(body);
          alertsBox.appendChild(wrap);
        });
      }
    }
  }

  async function refreshMetrics() {
    if (inFlight || document.hidden) return;
    inFlight = true;
    try {
      const resp = await fetch(METRICS_URL, {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (!resp.ok) return;
      const data = await resp.json();
      applyMetrics(data);
      lastFetchAt = Date.now();
    } catch (_) {
      // Silencioso para no interrumpir la sesión si hay un corte momentáneo.
    } finally {
      inFlight = false;
    }
  }

  setInterval(refreshMetrics, REFRESH_INTERVAL_MS);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - lastFetchAt >= 10000) {
      refreshMetrics();
    }
  });
})();
</script>

<?= view('template/footer') ?>
