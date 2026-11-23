<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme">
<head>
  <script {csp-script-nonce}>
    (function() {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
      try { localStorage.setItem('i18nextLng', 'es'); } catch (e) {}
    })();
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="referrer" content="no-referrer" />
  <title>Mi Estudio Médico | PACS Online</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card border-0 shadow-sm" style="max-width: 580px; width: 100%;">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
          <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white flex-shrink-0" style="width: 48px; height: 48px;">
            <i class="ti ti-scan fs-7"></i>
          </span>
          <div>
            <h4 class="fw-bold mb-0">Tu Estudio Médico</h4>
            <small class="text-muted">Identidad verificada · Acceso temporal seguro</small>
          </div>
        </div>
        <form action="<?= base_url('paciente/' . esc($token, 'url') . '/logout') ?>" method="post" class="m-0">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-outline-secondary" title="Cerrar acceso">
            <i class="ti ti-logout"></i> <span class="d-none d-sm-inline ms-1">Salir</span>
          </button>
        </form>
      </div>

      <?php if (session()->has('error')): ?>
        <div class="alert alert-danger py-2 fs-3"><?= esc(session('error')) ?></div>
      <?php endif; ?>

      <div class="bg-body-secondary rounded-3 p-3 mb-4">
        <div class="mb-2"><strong>Paciente:</strong> <?= esc(format_patient_name($study['patient_name'] ?? null, 'No especificado')) ?></div>
        <div class="mb-2"><strong>Estudio:</strong> <?= esc(format_study_description($study['study_description'] ?? null, 'Estudio DICOM')) ?></div>
        <div><strong>Fecha del estudio:</strong> <?= esc(format_date($study['study_date'] ?? null)) ?></div>
      </div>

      <div class="d-grid gap-2">
        <a href="<?= ohif_url((string) $link['study_instance_uid']) ?>" target="_blank" class="btn btn-primary btn-lg fw-semibold">
          <i class="ti ti-eye me-2"></i> Ver Imágenes en Visor Médico
        </a>

        <?php if (! empty($hasReport)): ?>
          <a href="<?= base_url('paciente/' . esc($token, 'url') . '/report') ?>" target="_blank" class="btn btn-success btn-lg fw-semibold">
            <i class="ti ti-file-type-pdf me-2"></i> Ver / Descargar Informe Médico (PDF)
          </a>
        <?php else: ?>
          <div class="alert alert-info py-2 px-3 mb-0 fs-3 text-center">
            <i class="ti ti-clock me-1"></i> El informe médico todavía está en proceso de redacción. Vuelve a consultar este enlace más tarde.
          </div>
        <?php endif; ?>

        <?php if ((int) ($link['allow_download'] ?? 0) === 1): ?>
          <a href="<?= base_url('paciente/' . esc($token, 'url') . '/download') ?>" class="btn btn-outline-primary fw-semibold">
            <i class="ti ti-download me-2"></i> Descargar Estudio Completo (DICOM .zip)
          </a>
        <?php endif; ?>
      </div>

      <?php if (! empty($link['expires_at'])): ?>
        <p class="text-center text-muted fs-2 mt-4 mb-0">
          Este enlace caduca el <strong><?= esc(format_date($link['expires_at'])) ?></strong>. Te recomendamos descargar tu informe y estudio antes de esa fecha.
        </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
