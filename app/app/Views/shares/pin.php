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
  <title>Acceso a Estudio Compartido | PACS Online</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card border-0 shadow-sm" style="max-width: 440px; width: 100%;">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white mb-3" style="width: 48px; height: 48px;">
          <i class="ti ti-lock fs-7"></i>
        </span>
        <h4 class="fw-bold mb-1">Estudio Médico Protegido</h4>
        <p class="text-muted fs-3 mb-0">Introduce el código PIN proporcionado para acceder a las imágenes DICOM.</p>
      </div>

      <?php if (session()->has('error')): ?>
        <div class="alert alert-danger py-2 fs-3"><?= esc(session('error')) ?></div>
      <?php endif; ?>

      <form action="<?= base_url('share/' . esc($token, 'url') . '/verify') ?>" method="post" id="pinUnlockForm">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label for="pin" class="form-label fw-semibold">Código PIN</label>
          <input type="password" class="form-control form-control-lg text-center fw-bold" id="pin" name="pin" placeholder="••••" autofocus required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="ti ti-unlock me-1"></i> Desbloquear Estudio
        </button>
      </form>
    </div>
  </div>
  <script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('pinUnlockForm');
      if (!form) return;
      form.addEventListener('submit', function() {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn || btn.disabled) return;
        const icon = btn.querySelector('i.ti');
        const spinnerHtml = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>';
        if (icon) {
          icon.outerHTML = spinnerHtml;
        } else {
          btn.insertAdjacentHTML('afterbegin', spinnerHtml);
        }
        btn.disabled = true;
      });
    });
  </script>
</body>
</html>
