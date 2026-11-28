<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Listado de usuarios</h5>
    <a href="<?= base_url('users/create') ?>" class="btn btn-sm btn-primary" title="Crear usuario">
      <i class="ti ti-user-plus fs-4"></i> <span class="d-none d-md-inline ms-1">Crear usuario</span>
    </a>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#usersTable" data-cards-target="#usersCards" placeholder="Buscar por nombre, correo o rol...">
    </div>

    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="usersTable">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Centro</th>
            <th class="text-center">Rol</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Creado</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-users d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay usuarios registrados.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php $roleGroups = config('AuthGroups')->groups; ?>
            <?php foreach ($users as $u):
              $groups    = $u->getGroups();
              $role      = $groups[0] ?? 'observador';
              $roleLabel = $roleGroups[$role]['title'] ?? ucfirst($role);
              $badge     = 'bg-primary-subtle text-primary border border-primary-subtle fs-2';
            ?>
            <tr style="cursor: pointer;" onclick="window.location.href='<?= base_url('users/edit/' . (int) $u->id) ?>'">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="<?= base_url('assets/images/profile/default-avatar.svg') ?>" class="rounded-circle object-fit-cover flex-shrink-0 round-28" width="28" height="28" alt="user" />
                  <strong class="fw-semibold text-body"><?= esc(mb_strtoupper((string) ($u->full_name ?: $u->username), 'UTF-8')) ?></strong>
                </div>
              </td>
              <td class="text-muted"><?= esc(mb_strtolower((string) $u->email, 'UTF-8')) ?></td>
              <td class="text-muted">
                <?php $uCenterId = ! empty($u->center_id) ? (int) $u->center_id : null; ?>
                <?= $uCenterId !== null && isset($centersById[$uCenterId]) ? esc(mb_strtoupper($centersById[$uCenterId], 'UTF-8')) : 'TODOS' ?>
              </td>
              <td class="text-center">
                <span class="badge <?= $badge ?> fw-semibold"><?= esc($roleLabel) ?></span>
              </td>
              <td class="text-center">
                <?php if ($u->active): ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 28px; height: 28px;" title="Activo">
                    <i class="ti ti-check fs-5"></i>
                  </div>
                <?php else: ?>
                  <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 28px; height: 28px;" title="Inactivo">
                    <i class="ti ti-x fs-5"></i>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted small"><?= esc(format_date(isset($u->created_at) ? (string) $u->created_at : null)) ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <div class="dropdown dropstart d-inline-block">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('users/edit/' . (int) $u->id) ?>">
                        <i class="ti ti-pencil fs-5"></i> Editar
                      </a>
                    </li>
                    <?php if ((int) $u->id !== (int) auth()->id()): ?>
                    <li>
                      <form action="<?= base_url('users/delete/' . (int) $u->id) ?>" method="post" class="d-inline delete-user-form">
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

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none" id="usersCards">
      <?php if (empty($users)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-users d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay usuarios registrados.</span>
        </div>
      <?php else: ?>
        <?php $roleGroups = config('AuthGroups')->groups; ?>
        <?php foreach ($users as $u):
          $groups    = $u->getGroups();
          $role      = $groups[0] ?? 'observador';
          $roleLabel = $roleGroups[$role]['title'] ?? ucfirst($role);
          $badge     = 'bg-primary-subtle text-primary border border-primary-subtle fs-2';
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item" style="cursor: pointer;" onclick="window.location.href='<?= base_url('users/edit/' . (int) $u->id) ?>'">
          <div class="card-body p-3 d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
              <img src="<?= base_url('assets/images/profile/default-avatar.svg') ?>" class="rounded-circle object-fit-cover flex-shrink-0" width="38" height="38" alt="user" />
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc(mb_strtoupper((string) ($u->full_name ?: $u->username), 'UTF-8')) ?></strong>
                <span class="text-muted small d-block mb-1"><?= esc(mb_strtolower((string) $u->email, 'UTF-8')) ?></span>
                <?php $mCenterId = ! empty($u->center_id) ? (int) $u->center_id : null; ?>
                <span class="text-muted small d-block mb-1">
                  <?= $mCenterId !== null && isset($centersById[$mCenterId]) ? esc(mb_strtoupper($centersById[$mCenterId], 'UTF-8')) : 'TODOS' ?>
                </span>
                <span class="badge <?= $badge ?> fw-semibold"><?= esc($roleLabel) ?></span>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
              <?php if ($u->active): ?>
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success flex-shrink-0" style="width: 28px; height: 28px;">
                  <i class="ti ti-check fs-5"></i>
                </div>
              <?php else: ?>
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger flex-shrink-0" style="width: 28px; height: 28px;">
                  <i class="ti ti-x fs-5"></i>
                </div>
              <?php endif; ?>
              <div class="dropdown dropstart" onclick="event.stopPropagation();">
                <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                  <i class="ti ti-dots-vertical fs-6"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                  <li>
                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_url('users/edit/' . (int) $u->id) ?>">
                      <i class="ti ti-pencil fs-5"></i> Editar
                    </a>
                  </li>
                  <?php if ((int) $u->id !== (int) auth()->id()): ?>
                  <li>
                    <form action="<?= base_url('users/delete/' . (int) $u->id) ?>" method="post" class="d-inline delete-user-form">
                      <?= csrf_field() ?>
                      <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                        <i class="ti ti-trash fs-5"></i> Eliminar
                      </button>
                    </form>
                  </li>
                  <?php endif; ?>
                </ul>
              </div>
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
  document.querySelectorAll('.delete-user-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar usuario?',
          text: 'Se eliminará este usuario de forma permanente.',
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
      } else if (confirm('¿Eliminar este usuario de forma permanente?')) {
        form.submit();
      }
    });
  });
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
