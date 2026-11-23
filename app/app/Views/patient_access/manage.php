<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Acceso Paciente — <?= esc(format_patient_name($study['patient_name'] ?? null, 'Estudio DICOM')) ?></h5>
    <div class="d-flex gap-2">
      <a href="<?= base_url('studies/case/' . urlencode($studyUid)) ?>" class="btn btn-sm btn-outline-primary">
        <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver al estudio</span>
      </a>
      <a href="<?= base_url('studies/patient-access/' . urlencode($studyUid) . '/create') ?>" class="btn btn-sm btn-primary">
        <i class="ti ti-link fs-4"></i> <span class="d-none d-md-inline ms-1">Nuevo Enlace Paciente</span>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#patientLinksTable" data-cards-target="#patientLinksCards" placeholder="Buscar por correo o estado...">
    </div>

    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="patientLinksTable">
        <thead>
          <tr>
            <th class="text-center">Creado</th>
            <th>Correo enviado</th>
            <th class="text-center">Caducidad</th>
            <th class="text-center">Descarga ZIP</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Accesos</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($links)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-user-check d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>Aún no se ha generado ningún enlace de acceso para el paciente.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($links as $l):
              $isRevoked = ! empty($l['revoked_at']);
              $isLocked  = ! empty($l['locked_at']);
              $isExpired = ! empty($l['expires_at']) && strtotime((string) $l['expires_at']) < time();
              $isActive  = ! $isRevoked && ! $isLocked && ! $isExpired;
              $magicUrl  = base_url('paciente/' . $l['token']);
              $waText    = 'Tu estudio médico está disponible de forma segura en este enlace (te pedirá tu número de identificación y fecha de nacimiento): ' . $magicUrl;
            ?>
            <tr>
              <td class="text-center text-muted small"><?= esc(format_date($l['created_at'] ?? null)) ?></td>
              <td class="fw-semibold text-body"><?= esc($l['patient_email'] ?: 'Entrega manual / QR') ?></td>
              <td class="text-center text-muted small"><?= esc(format_date($l['expires_at'] ?? null)) ?></td>
              <td class="text-center">
                <?php if ((int) ($l['allow_download'] ?? 0) === 1): ?>
                  <span class="badge bg-info-subtle text-info border border-info-subtle fs-2">Permitida</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Solo visor</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($isRevoked): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2">Revocado</span>
                <?php elseif ($isLocked): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2">Bloqueado</span>
                <?php elseif ($isExpired): ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2">Expirado</span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle fs-2">Activo</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= (int) $l['views_count'] ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <?php if (! $isRevoked): ?>
                  <div class="dropdown dropstart d-inline-block">
                    <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                      <i class="ti ti-dots-vertical fs-6"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <a href="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/edit') ?>" class="dropdown-item d-flex align-items-center gap-2">
                          <i class="ti ti-edit fs-5"></i> Editar enlace
                        </a>
                      </li>
                      <?php if ($isActive): ?>
                      <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 resend-email-btn" data-action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/resend-email') ?>" data-email="<?= esc((string) ($l['patient_email'] ?? ''), 'attr') ?>">
                          <i class="ti ti-mail fs-5"></i> <?= ! empty($l['patient_email']) ? 'Reenviar correo' : 'Enviar por correo' ?>
                        </button>
                      </li>
                      <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 qr-patient-btn" data-qr="<?= esc($l['_qr'] ?? '', 'attr') ?>" data-url="<?= esc($magicUrl, 'attr') ?>">
                          <i class="ti ti-qrcode fs-5"></i> Ver Código QR
                        </button>
                      </li>
                      <li>
                        <a href="https://wa.me/?text=<?= urlencode($waText) ?>" target="_blank" class="dropdown-item d-flex align-items-center gap-2">
                          <i class="ti ti-brand-whatsapp fs-5"></i> Enviar por WhatsApp
                        </a>
                      </li>
                      <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 copy-patient-btn" data-url="<?= esc($magicUrl, 'attr') ?>">
                          <i class="ti ti-copy fs-5"></i> Copiar enlace
                        </button>
                      </li>
                      <?php endif; ?>
                      <?php if (auth()->user()?->can('studies.delete')): ?>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/revoke') ?>" method="post" class="d-inline revoke-patient-form">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="ti ti-ban fs-5"></i> Revocar acceso
                          </button>
                        </form>
                      </li>
                      <?php endif; ?>
                    </ul>
                  </div>
                <?php elseif ($isRevoked && auth()->user()?->can('studies.delete')): ?>
                  <div class="dropdown dropstart d-inline-block">
                    <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                      <i class="ti ti-dots-vertical fs-6"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <form action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/delete') ?>" method="post" class="d-inline delete-patient-link-form">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="ti ti-trash fs-5"></i> Eliminar enlace
                          </button>
                        </form>
                      </li>
                    </ul>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none" id="patientLinksCards">
      <?php if (empty($links)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-user-check d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>Aún no se ha generado ningún enlace de acceso para el paciente.</span>
        </div>
      <?php else: ?>
        <?php foreach ($links as $l):
          $isRevoked = ! empty($l['revoked_at']);
          $isLocked  = ! empty($l['locked_at']);
          $isExpired = ! empty($l['expires_at']) && strtotime((string) $l['expires_at']) < time();
          $isActive  = ! $isRevoked && ! $isLocked && ! $isExpired;
          $magicUrl  = base_url('paciente/' . $l['token']);
          $waText    = 'Tu estudio médico está disponible de forma segura en este enlace (te pedirá tu número de identificación y fecha de nacimiento): ' . $magicUrl;
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc($l['patient_email'] ?: 'Entrega manual / QR') ?></strong>
                <span class="text-muted small"><?= esc(format_date($l['created_at'] ?? null)) ?> · Expira: <?= esc(format_date($l['expires_at'] ?? null)) ?> · <?= (int) $l['views_count'] ?> accesos</span>
              </div>
              <?php if (! $isRevoked): ?>
                <div class="dropdown dropstart" onclick="event.stopPropagation();">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a href="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/edit') ?>" class="dropdown-item d-flex align-items-center gap-2">
                        <i class="ti ti-edit fs-5"></i> Editar enlace
                      </a>
                    </li>
                    <?php if ($isActive): ?>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 resend-email-btn" data-action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/resend-email') ?>" data-email="<?= esc((string) ($l['patient_email'] ?? ''), 'attr') ?>">
                        <i class="ti ti-mail fs-5"></i> <?= ! empty($l['patient_email']) ? 'Reenviar correo' : 'Enviar por correo' ?>
                      </button>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 qr-patient-btn" data-qr="<?= esc($l['_qr'] ?? '', 'attr') ?>" data-url="<?= esc($magicUrl, 'attr') ?>">
                        <i class="ti ti-qrcode fs-5"></i> Ver Código QR
                      </button>
                    </li>
                    <li>
                      <a href="https://wa.me/?text=<?= urlencode($waText) ?>" target="_blank" class="dropdown-item d-flex align-items-center gap-2">
                        <i class="ti ti-brand-whatsapp fs-5"></i> Enviar por WhatsApp
                      </a>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 copy-patient-btn" data-url="<?= esc($magicUrl, 'attr') ?>">
                        <i class="ti ti-copy fs-5"></i> Copiar enlace
                      </button>
                    </li>
                    <?php endif; ?>
                    <?php if (auth()->user()?->can('studies.delete')): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/revoke') ?>" method="post" class="d-inline revoke-patient-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                          <i class="ti ti-ban fs-5"></i> Revocar acceso
                        </button>
                      </form>
                    </li>
                    <?php endif; ?>
                  </ul>
                </div>
              <?php elseif ($isRevoked && auth()->user()?->can('studies.delete')): ?>
                <div class="dropdown dropstart" onclick="event.stopPropagation();">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <form action="<?= base_url('studies/patient-access/' . (int) $l['id'] . '/delete') ?>" method="post" class="d-inline delete-patient-link-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                          <i class="ti ti-trash fs-5"></i> Eliminar enlace
                        </button>
                      </form>
                    </li>
                  </ul>
                </div>
              <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-1">
              <?php if ($isRevoked): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2">Revocado</span>
              <?php elseif ($isLocked): ?>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2">Bloqueado</span>
              <?php elseif ($isExpired): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2">Expirado</span>
              <?php else: ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle fs-2">Activo</span>
              <?php endif; ?>
              <?php if ((int) ($l['allow_download'] ?? 0) === 1): ?>
                <span class="badge bg-info-subtle text-info border border-info-subtle fs-2">Descarga ZIP</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<form id="resendPatientEmailForm" method="post" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="patient_email" id="resendPatientEmailInput" value="">
</form>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  document.querySelectorAll('.resend-email-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const action = btn.getAttribute('data-action');
      const currentEmail = btn.getAttribute('data-email') || '';
      const form = document.getElementById('resendPatientEmailForm');
      const input = document.getElementById('resendPatientEmailInput');
      if (!form || !input) return;

      Swal.fire({
        title: currentEmail ? 'Reenviar enlace por correo' : 'Enviar enlace por correo',
        input: 'email',
        inputLabel: 'Correo electrónico del paciente',
        inputValue: currentEmail,
        inputPlaceholder: 'paciente@correo.com',
        showCancelButton: true,
        confirmButtonText: '<i class="ti ti-send me-1"></i> Enviar',
        cancelButtonText: 'Cancelar',
        customClass: {
          confirmButton: 'btn btn-primary me-2',
          cancelButton: 'btn btn-outline-secondary'
        },
        buttonsStyling: false,
        inputValidator: function(value) {
          if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim())) {
            return 'Introduce un correo electrónico válido.';
          }
        }
      }).then(function(result) {
        if (result.isConfirmed && result.value) {
          form.action = action;
          input.value = result.value.trim();
          Swal.fire({
            title: 'Enviando correo...',
            text: 'Por favor espera un momento.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function() {
              Swal.showLoading();
            }
          });
          form.submit();
        }
      });
    });
  });

  document.querySelectorAll('.copy-patient-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const url = btn.getAttribute('data-url');
      navigator.clipboard.writeText(url).then(function() {
        Swal.fire({
          icon: 'success',
          title: 'Enlace copiado',
          text: url,
          timer: 2500,
          showConfirmButton: false
        });
      }).catch(function() {
        Swal.fire({
          icon: 'error',
          title: 'No se pudo copiar',
          text: 'Copia el enlace manualmente.',
          timer: 2500,
          showConfirmButton: false
        });
      });
    });
  });

  document.querySelectorAll('.qr-patient-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const qr  = btn.getAttribute('data-qr');
      const url = btn.getAttribute('data-url');
      Swal.fire({
        title: 'Código QR para el Paciente',
        html: `<img src="${qr}" alt="QR" style="width:200px;height:200px;" class="mb-2 rounded border p-1 bg-white"><div class="small text-muted text-break">${url}</div>`,
        confirmButtonText: 'Cerrar',
        customClass: { confirmButton: 'btn btn-sm btn-primary' },
        buttonsStyling: false
      });
    });
  });

  document.querySelectorAll('.revoke-patient-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Revocar enlace del paciente?',
          text: 'El enlace dejará de funcionar de inmediato.',
          showCancelButton: true,
          confirmButtonText: 'Sí, revocar',
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
      } else if (confirm('¿Revocar este enlace?')) {
        form.submit();
      }
    });
  });
  document.querySelectorAll('.delete-patient-link-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar enlace revocado?',
          text: 'Esta acción eliminará el registro de la lista y no se puede deshacer.',
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
      } else if (confirm('¿Eliminar este enlace revocado?')) {
        form.submit();
      }
    });
  });
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
