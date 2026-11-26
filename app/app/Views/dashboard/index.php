<?php /** Vista principal del Dashboard adaptada al rol: Supervisor, Médico, Usuario u Observador. */ ?>
<?= view('template/header') ?>

<div class="row">
  <div class="col-12 mb-4">
    <div class="card border-0 shadow-sm">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <?php if ($roleMode === 'medico'): ?>
            <h4 class="fw-bold mb-1">Panel Médico</h4>
            <p class="text-muted mb-0">Revisa los estudios pendientes en tu Worklist y emite informes médicos.</p>
          <?php elseif ($roleMode === 'usuario'): ?>
            <h4 class="fw-bold mb-1">Panel de Gestión PACS</h4>
            <p class="text-muted mb-0">Consulta estudios, sube archivos DICOM, reenvía a nodos y genera enlaces de envío para pacientes.</p>
          <?php elseif ($roleMode === 'observador'): ?>
            <h4 class="fw-bold mb-1">Panel de Consulta PACS</h4>
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

<div class="row g-3 mb-4">
  <?php if ($roleMode === 'observador'): ?>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="rounded-3 bg-primary-subtle text-primary p-3"><i class="ti ti-report-medical fs-7"></i></span>
          <div>
            <span class="text-muted fs-2 d-block">Estudios en PACS</span>
            <h3 class="fw-bold mb-0"><?= (int) $totalStudies ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $reportedCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $totalStudies ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $pendingCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $reportedCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $pendingCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $urgentCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= (int) $reportedCount ?></h3>
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
            <h3 class="fw-bold mb-0"><?= number_format((int) ($orthancStats['CountStudies'] ?? $totalStudies), 0, ',', '.') ?></h3>
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
            <h3 class="fw-bold mb-0"><?= number_format((int) ($orthancStats['CountPatients'] ?? 0), 0, ',', '.') ?></h3>
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
            <h3 class="fw-bold mb-0"><?= number_format((int) ($orthancStats['CountSeries'] ?? 0), 0, ',', '.') ?></h3>
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
            <h3 class="fw-bold mb-0"><?= number_format((int) ($orthancStats['CountInstances'] ?? 0), 0, ',', '.') ?></h3>
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
                <h3 class="fw-bold mb-0"><?= number_format($usedPct, 1, ',', '.') ?>% ocupado</h3>
                <span class="text-muted small"><?= number_format($freePct, 1, ',', '.') ?>% libre</span>
              </div>
              <div class="progress mt-2" style="height: 6px;">
                <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= min(100, max(0, $usedPct)) ?>%;" aria-valuenow="<?= $usedPct ?>" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
              <span class="text-muted small d-block mt-1">
                <?= number_format($usedTb, 2, ',', '.') ?> TB / <?= number_format($totalTb, 2, ',', '.') ?> TB (<?= number_format($freeTb, 2, ',', '.') ?> TB libres)
              </span>
            <?php else: ?>
              <h3 class="fw-bold mb-0">
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
          <span class="rounded-3 <?= $orthancAlive ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-3 flex-shrink-0">
            <i class="ti ti-server fs-7"></i>
          </span>
          <div class="min-w-0">
            <span class="text-muted fs-2 d-block">Servidor PACS</span>
            <h4 class="fw-bold mb-0 <?= $orthancAlive ? 'text-success' : 'text-danger' ?>"><?= $orthancAlive ? 'Conectado' : 'Sin conexión' ?></h4>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?= view('template/footer') ?>
