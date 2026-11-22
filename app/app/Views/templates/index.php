<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Mis plantillas de informe</h5>
    <div class="d-flex gap-2">
      <a href="<?= base_url('templates/create') ?>" class="btn btn-sm btn-primary">
        <i class="ti ti-plus fs-4"></i> <span class="d-none d-md-inline ms-1">Nueva plantilla</span>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#templatesTable" data-cards-target="#templatesCards" placeholder="Buscar por nombre o modalidad...">
    </div>

    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="templatesTable">
        <thead>
          <tr>
            <th class="text-center">Fecha</th>
            <th>Nombre</th>
            <th class="text-center">Modalidad</th>
            <th>Impresión diagnóstica</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($templates)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-template d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No tienes plantillas personales todavía. <a href="<?= base_url('templates/create') ?>">Crea tu primera plantilla</a>.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($templates as $tpl): ?>
            <tr style="cursor: pointer;" onclick="window.location.href='<?= base_url('templates/edit/' . (int) $tpl['id']) ?>'">
              <td class="text-center text-muted small"><?= esc(format_date($tpl['created_at'] ?? null)) ?></td>
              <td class="fw-semibold text-body"><?= esc($tpl['name']) ?></td>
              <td class="text-center">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($tpl['modality']) ?></span>
              </td>
              <td class="text-muted text-truncate" style="max-width: 340px;"><?= esc($tpl['impression']) ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('templates/edit/' . (int) $tpl['id']) ?>">
                        <i class="ti ti-pencil fs-5"></i> Editar
                      </a>
                    </li>
                    <li>
                      <form action="<?= base_url('templates/delete/' . (int) $tpl['id']) ?>" method="post" class="d-inline delete-template-form">
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
    <div class="d-block d-md-none" id="templatesCards">
      <?php if (empty($templates)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-template d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No tienes plantillas personales todavía.</span>
        </div>
      <?php else: ?>
        <?php foreach ($templates as $tpl): ?>
        <div class="card border shadow-sm mb-3 mobile-card-item" style="cursor: pointer;" onclick="window.location.href='<?= base_url('templates/edit/' . (int) $tpl['id']) ?>'">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc($tpl['name']) ?></strong>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold mt-1"><?= esc($tpl['modality']) ?></span>
              </div>
              <div class="dropdown dropstart" onclick="event.stopPropagation();">
                <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('templates/edit/' . (int) $tpl['id']) ?>">
                      <i class="ti ti-pencil fs-5"></i> Editar
                    </a>
                  </li>
                  <li>
                    <form action="<?= base_url('templates/delete/' . (int) $tpl['id']) ?>" method="post" class="d-inline delete-template-form">
                      <?= csrf_field() ?>
                      <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                        <i class="ti ti-trash fs-5"></i> Eliminar
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
            </div>
            <p class="text-muted small mb-0"><?= esc($tpl['impression']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.querySelectorAll('.delete-template-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar plantilla?',
          text: 'No se puede deshacer.',
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
      } else if (confirm('¿Eliminar esta plantilla?')) {
        form.submit();
      }
    });
  });
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
