<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme">
<head>
  <script {csp-script-nonce}>
    (function() {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Enlace no disponible | PACS Online</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card border-0 shadow-sm text-center" style="max-width: 440px; width: 100%;">
    <div class="card-body p-4 p-md-5">
      <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-danger-subtle text-danger mb-3" style="width: 52px; height: 52px;">
        <i class="ti ti-link-off fs-7"></i>
      </span>
      <h4 class="fw-bold mb-2">Enlace no disponible</h4>
      <p class="text-muted fs-3 mb-0">
        Este enlace ha caducado, ha sido revocado o se ha bloqueado por seguridad tras varios intentos fallidos. Por favor, contacta con tu centro médico para solicitar uno nuevo.
      </p>
    </div>
  </div>
</body>
</html>
