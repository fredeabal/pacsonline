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

<div class="card">
  <div class="card-header bg-primary-subtle d-flex align-items-center justify-content-between">
    <h5 class="mb-0 text-primary fw-semibold">Estudios recientes</h5>
    <a href="<?= base_url($roleMode === 'medico' ? 'reports' : 'studies') ?>" class="btn btn-sm btn-outline-primary">Ver todos</a>
  </div>
  <div class="card-body">
    <?php if (empty($recentStudies)): ?>
      <p class="text-muted mb-0">No hay estudios disponibles todavía.</p>
    <?php else: ?>
      <!-- Vista Escritorio -->
      <div class="mb-2 border rounded-1 table-responsive d-none d-md-block">
        <table class="table text-nowrap mb-0 align-middle table-hover">
          <thead>
            <tr>
              <th class="text-center">Fecha</th>
              <th>Paciente</th>
              <th class="text-center">Modalidad</th>
              <th>Descripción</th>
              <th class="text-center">Prioridad</th>
              <th class="text-center">Informe</th>
              <th class="text-end pe-3">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentStudies as $study):
              $tags      = $study['MainDicomTags'] ?? [];
              $patient   = $study['PatientMainDicomTags'] ?? [];
              $uid       = $tags['StudyInstanceUID'] ?? '';
              $record    = $study['_record'] ?? null;
              $report    = $study['_report'] ?? null;
              $repStatus = $report['status'] ?? ($study['_report_status'] ?? 'pending');
              $modality  = extract_study_modality($study);
              $rowUrl    = $roleMode === 'medico'
                ? base_url('reports/edit/' . urlencode($uid))
                : base_url('studies/case/' . urlencode($uid));

              [$badgeClass, $repLabel] = match ($repStatus) {
                  'reported'  => ['bg-success-subtle text-success border border-success-subtle fs-2', 'Informado'],
                  'in_review' => ['bg-warning-subtle text-warning border border-warning-subtle fs-2', 'En revisión'],
                  default     => ['bg-info-subtle text-info border border-info-subtle fs-2', 'Pendiente'],
              };
            ?>
            <tr class="cursor-pointer" onclick="window.location.href='<?= $rowUrl ?>'">
              <td class="text-center"><?= esc(format_date($tags['StudyDate'] ?? null)) ?></td>
              <td class="fw-semibold text-body"><?= esc(format_patient_name($patient['PatientName'] ?? null)) ?></td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              </td>
              <td><?= esc(format_study_description($tags['StudyDescription'] ?? null)) ?></td>
              <td class="text-center">
                <?php if (($record['priority'] ?? ($study['_priority'] ?? 'normal')) === 'urgent'): ?>
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
                    <?php if (auth()->user()?->can('reports.review')): ?>
                      <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/edit/' . urlencode($uid)) ?>">
                          <i class="ti ti-stethoscope fs-5"></i> Informar estudio
                        </a>
                      </li>
                    <?php endif; ?>
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
                        <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/patient-access/' . urlencode($uid)) ?>">
                          <i class="ti ti-user-check fs-5"></i> Acceso paciente<?= ! empty($study['_active_patient_links']) ? ' (' . (int) $study['_active_patient_links'] . ')' : '' ?>
                        </a>
                      </li>
                    <?php endif; ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/download/' . urlencode($uid)) ?>">
                        <i class="ti ti-download fs-5"></i> Descargar DICOM (.zip)
                      </a>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div class="d-block d-md-none">
        <?php foreach ($recentStudies as $study):
          $tags      = $study['MainDicomTags'] ?? [];
          $patient   = $study['PatientMainDicomTags'] ?? [];
          $uid       = $tags['StudyInstanceUID'] ?? '';
          $record    = $study['_record'] ?? null;
          $report    = $study['_report'] ?? null;
          $repStatus = $report['status'] ?? ($study['_report_status'] ?? 'pending');
          $modality  = extract_study_modality($study);
          $rowUrl    = $roleMode === 'medico'
            ? base_url('reports/edit/' . urlencode($uid))
            : base_url('studies/case/' . urlencode($uid));

          [$badgeClass, $repLabel] = match ($repStatus) {
              'reported'  => ['bg-success-subtle text-success border border-success-subtle fs-2', 'Informado'],
              'in_review' => ['bg-warning-subtle text-warning border border-warning-subtle fs-2', 'En revisión'],
              default     => ['bg-info-subtle text-info border border-info-subtle fs-2', 'Pendiente'],
          };
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item cursor-pointer" onclick="window.location.href='<?= $rowUrl ?>'">
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
                  <?php if (auth()->user()?->can('reports.review')): ?>
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('reports/edit/' . urlencode($uid)) ?>">
                        <i class="ti ti-stethoscope fs-5"></i> Informar estudio
                      </a>
                    </li>
                  <?php endif; ?>
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
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/patient-access/' . urlencode($uid)) ?>">
                        <i class="ti ti-user-check fs-5"></i> Acceso paciente<?= ! empty($study['_active_patient_links']) ? ' (' . (int) $study['_active_patient_links'] . ')' : '' ?>
                      </a>
                    </li>
                  <?php endif; ?>
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('studies/download/' . urlencode($uid)) ?>">
                      <i class="ti ti-download fs-5"></i> Descargar DICOM (.zip)
                    </a>
                  </li>
                </ul>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($modality) ?></span>
              <span class="badge <?= $badgeClass ?> fw-semibold"><?= $repLabel ?></span>
              <?php if (($record['priority'] ?? ($study['_priority'] ?? 'normal')) === 'urgent'): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2 fw-semibold">Urgente</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Normal</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?= view('template/footer') ?>
