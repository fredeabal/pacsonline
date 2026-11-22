<?php /** Listado de centros médicos remitentes y nodos DICOM sincronizados con Orthanc. */ ?>
<?= view('template/header') ?>

<?php if (session()->has('generated_token')): ?>
  <div class="alert alert-primary border border-primary-subtle mb-4">
    <h6 class="fw-bold mb-1"><i class="ti ti-key me-1"></i> Token del gateway (solo se muestra una vez)</h6>
    <p class="mb-2 small">Usa este token en el gateway para enviar estudios a <code><?= esc(base_url('api/ingest/dicom')) ?></code>:</p>
    <div class="input-group input-group-sm">
      <input type="text" class="form-control fw-bold" id="generatedTokenInput" value="<?= esc(session('generated_token')) ?>" readonly>
      <button type="button" class="btn btn-primary" onclick="navigator.clipboard.writeText(document.getElementById('generatedTokenInput').value)">
        <i class="ti ti-copy me-1"></i> Copiar
      </button>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Centros médicos remitentes</h5>
    <a href="<?= base_url('centers/create') ?>" class="btn btn-sm btn-primary">
      <i class="ti ti-building-hospital fs-4"></i> <span class="d-none d-md-inline ms-1">Nuevo centro</span>
    </a>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#centersTable" data-cards-target="#centersCards" placeholder="Buscar por nombre o AE Title...">
    </div>

    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="centersTable">
        <thead>
          <tr>
            <th>Centro Médico / Nodo</th>
            <th class="text-center">AE Title</th>
            <th class="text-center">Host / IP</th>
            <th class="text-center">Función</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Creado</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($centers)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-building-hospital d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay centros médicos registrados todavía.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($centers as $c): ?>
            <?php
              $canReceive = ! isset($c['can_receive']) || ! empty($c['can_receive']);
              $canSend    = ! empty($c['can_send']);
            ?>
            <tr style="cursor: pointer;" onclick="window.location.href='<?= base_url('centers/edit/' . (int) $c['id']) ?>'">
              <td class="fw-semibold text-body"><?= esc($c['name']) ?></td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= esc($c['ae_title']) ?></span>
              </td>
              <td class="text-center text-muted small">
                <?php if (! empty($c['dicom_host'])): ?>
                  <?= esc($c['dicom_host'] . (! empty($c['dicom_port']) ? ':' . (int) $c['dicom_port'] : '')) ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($canReceive && $canSend): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle fs-2">Ambos</span>
                <?php elseif ($canSend): ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2">Enviar</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Recibir</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($c['active']): ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 28px; height: 28px;" title="Activo">
                    <i class="ti ti-check fs-5"></i>
                  </div>
                <?php else: ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 28px; height: 28px;" title="Inactivo">
                    <i class="ti ti-x fs-5"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted small"><?= esc(! empty($c['last_seen_at']) ? format_date($c['last_seen_at']) : format_date($c['created_at'] ?? null)) ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('centers/edit/' . (int) $c['id']) ?>">
                        <i class="ti ti-pencil fs-5"></i> Editar
                      </a>
                    </li>
                    <li>
                      <form action="<?= base_url('centers/delete/' . (int) $c['id']) ?>" method="post" class="d-inline delete-center-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                          <i class="ti ti-trash fs-5"></i> Eliminar
                        </button>
                      </form>
                    </li>
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
    <div class="d-block d-md-none" id="centersCards">
      <?php if (empty($centers)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-building-hospital d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay centros médicos registrados todavía.</span>
        </div>
      <?php else: ?>
        <?php foreach ($centers as $c): ?>
        <div class="card border shadow-sm mb-3 mobile-card-item" style="cursor: pointer;" onclick="window.location.href='<?= base_url('centers/edit/' . (int) $c['id']) ?>'">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc($c['name']) ?></strong>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 mt-1"><?= esc($c['ae_title']) ?></span>
              </div>
              <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <?php if ($c['active']): ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 28px; height: 28px;">
                    <i class="ti ti-check fs-5"></i>
                  </div>
                <?php else: ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 28px; height: 28px;">
                    <i class="ti ti-x fs-5"></i>
                  </div>
                <?php endif; ?>
                <div class="dropdown dropstart" onclick="event.stopPropagation();">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('centers/edit/' . (int) $c['id']) ?>">
                        <i class="ti ti-pencil fs-5"></i> Editar
                      </a>
                    </li>
                    <li>
                      <form action="<?= base_url('centers/delete/' . (int) $c['id']) ?>" method="post" class="d-inline delete-center-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                          <i class="ti ti-trash fs-5"></i> Eliminar
                        </button>
                      </form>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="text-muted small mb-2">
              <?php if (! empty($c['dicom_host'])): ?>
                <?= esc($c['dicom_host'] . (! empty($c['dicom_port']) ? ':' . (int) $c['dicom_port'] : '')) ?> ·
              <?php endif; ?>
              Último: <?= esc(! empty($c['last_seen_at']) ? format_date($c['last_seen_at']) : 'Nunca') ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.querySelectorAll('.delete-center-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar nodo?',
          text: 'Se eliminará este centro/nodo de forma permanente.',
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
      } else if (confirm('¿Eliminar este centro/nodo de forma permanente?')) {
        form.submit();
      }
    });
  });
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
