<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Enlaces compartidos — <?= esc($study['patient_name'] ?? 'Estudio DICOM') ?></h5>
    <div class="d-flex gap-2">
      <a href="<?= base_url('studies') ?>" class="btn btn-sm btn-outline-primary">
        <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Estudios</span>
      </a>
      <a href="<?= base_url('studies/share/' . urlencode($studyUid) . '/create') ?>" class="btn btn-sm btn-primary">
        <i class="ti ti-link fs-4"></i> <span class="d-none d-md-inline ms-1">Nuevo Enlace</span>
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#sharesTable" data-cards-target="#sharesCards" placeholder="Buscar por destinatario o estado...">
    </div>

    <!-- Vista Escritorio -->
    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="sharesTable">
        <thead>
          <tr>
            <th class="text-center">Fecha</th>
            <th>Destinatario</th>
            <th class="text-center">Caducidad</th>
            <th class="text-center">Seguridad</th>
            <th class="text-center">Estado</th>
            <th class="text-center">Vistas</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($shares)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-share d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>Aún no se han generado enlaces para este estudio.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($shares as $s):
              $isRevoked = ! empty($s['revoked_at']);
              $isExpired = ! empty($s['expires_at']) && strtotime($s['expires_at']) < time();
              $shareUrl  = base_url('share/' . $s['token']);
              $expiresFormatted = ! empty($s['expires_at']) ? format_date($s['expires_at']) : 'Sin caducidad';
            ?>
            <tr>
              <td class="text-center text-muted small"><?= esc(format_date($s['created_at'] ?? null)) ?></td>
              <td class="fw-semibold text-body"><?= esc($s['recipient_label'] ?: 'Enlace general') ?></td>
              <td class="text-center text-muted small"><?= esc($expiresFormatted) ?></td>
              <td class="text-center">
                <?php if (! empty($s['pin_hash'])): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><i class="ti ti-lock"></i> Con PIN</span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2">Solo enlace</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($isRevoked): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-2">Revocado</span>
                <?php elseif ($isExpired): ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2">Expirado</span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle fs-2">Activo</span>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= (int) $s['views_count'] ?></td>
              <td class="text-end pe-3" onclick="event.stopPropagation();">
                <?php if (! $isRevoked && ! $isExpired):
                  $waText = 'Te comparto el acceso seguro al estudio médico: ' . $shareUrl;
                ?>
                  <div class="dropdown dropstart d-inline-block">
                    <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="Acciones" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                      <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 qr-share-btn" data-qr="<?= esc($s['_qr'] ?? '', 'attr') ?>" data-url="<?= esc($shareUrl, 'attr') ?>">
                          <i class="ti ti-qrcode fs-5"></i> Ver Código QR
                        </button>
                      </li>
                      <li>
                        <a href="https://wa.me/?text=<?= urlencode($waText) ?>" target="_blank" class="dropdown-item d-flex align-items-center gap-2">
                          <i class="ti ti-brand-whatsapp fs-5"></i> Enviar por WhatsApp
                        </a>
                      </li>
                      <li>
                        <button type="button" class="dropdown-item d-flex align-items-center gap-2 copy-share-btn" data-url="<?= esc($shareUrl, 'attr') ?>">
                          <i class="ti ti-copy fs-5"></i> Copiar enlace
                        </button>
                      </li>
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form action="<?= base_url('studies/share/' . (int) $s['id'] . '/revoke') ?>" method="post" class="d-inline revoke-share-form">
                          <?= csrf_field() ?>
                          <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                            <i class="ti ti-ban fs-5"></i> Revocar acceso
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
    <div class="d-block d-md-none" id="sharesCards">
      <?php if (empty($shares)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-share d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>Aún no se han generado enlaces para este estudio.</span>
        </div>
      <?php else: ?>
        <?php foreach ($shares as $s):
          $isRevoked = ! empty($s['revoked_at']);
          $isExpired = ! empty($s['expires_at']) && strtotime($s['expires_at']) < time();
          $shareUrl  = base_url('share/' . $s['token']);
          $waText    = 'Te comparto el acceso seguro al estudio médico: ' . $shareUrl;
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <strong class="d-block fw-semibold text-body"><?= esc($s['recipient_label'] ?: 'Enlace general') ?></strong>
                <span class="text-muted small"><?= esc(format_date($s['created_at'] ?? null)) ?> · <?= ! empty($s['expires_at']) ? 'Expira: ' . esc(format_date($s['expires_at'])) : 'Sin caducidad' ?> · <?= (int) $s['views_count'] ?> vistas</span>
              </div>
              <?php if (! $isRevoked && ! $isExpired): ?>
                <div class="dropdown dropstart" onclick="event.stopPropagation();">
                  <button type="button" class="btn btn-link text-muted p-1 d-inline-block border-0 shadow-none text-decoration-none" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Acciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 qr-share-btn" data-qr="<?= esc($s['_qr'] ?? '', 'attr') ?>" data-url="<?= esc($shareUrl, 'attr') ?>">
                        <i class="ti ti-qrcode fs-5"></i> Ver Código QR
                      </button>
                    </li>
                    <li>
                      <a href="https://wa.me/?text=<?= urlencode($waText) ?>" target="_blank" class="dropdown-item d-flex align-items-center gap-2">
                        <i class="ti ti-brand-whatsapp fs-5"></i> Enviar por WhatsApp
                      </a>
                    </li>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center gap-2 copy-share-btn" data-url="<?= esc($shareUrl, 'attr') ?>">
                        <i class="ti ti-copy fs-5"></i> Copiar enlace
                      </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form action="<?= base_url('studies/share/' . (int) $s['id'] . '/revoke') ?>" method="post" class="d-inline revoke-share-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2 text-danger">
                          <i class="ti ti-ban fs-5"></i> Revocar acceso
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
              <?php elseif ($isExpired): ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-2">Expirado</span>
              <?php else: ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle fs-2">Activo</span>
              <?php endif; ?>
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
  document.querySelectorAll('.copy-share-btn').forEach(function(btn) {
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

  document.querySelectorAll('.qr-share-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const qr  = btn.getAttribute('data-qr');
      const url = btn.getAttribute('data-url');
      Swal.fire({
        title: 'Código QR',
        html: `<img src="${qr}" alt="QR" style="width:200px;height:200px;" class="mb-2 rounded border p-1 bg-white"><div class="small text-muted text-break">${url}</div>`,
        confirmButtonText: 'Cerrar',
        customClass: { confirmButton: 'btn btn-sm btn-primary' },
        buttonsStyling: false
      });
    });
  });

  document.querySelectorAll('.revoke-share-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Revocar enlace?',
          text: 'El enlace dejará de funcionar.',
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
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
