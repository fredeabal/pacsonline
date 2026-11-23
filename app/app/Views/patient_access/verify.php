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
  <meta name="referrer" content="no-referrer" />
  <title>Verificación de Identidad | PACS Online</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card border-0 shadow-sm" style="max-width: 460px; width: 100%;">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white mb-3" style="width: 52px; height: 52px;">
          <i class="ti ti-shield-lock fs-7"></i>
        </span>
        <h4 class="fw-bold mb-1">Verificación de identidad</h4>
        <p class="text-muted fs-3 mb-0">Para proteger tus datos médicos, confirma tu documento de identidad y fecha de nacimiento.</p>
      </div>

      <?php if (session()->has('error')): ?>
        <div class="alert alert-danger py-2 fs-3"><?= esc(session('error')) ?></div>
      <?php endif; ?>

      <?php if (session()->has('message')): ?>
        <div class="alert alert-success py-2 fs-3"><?= esc(session('message')) ?></div>
      <?php endif; ?>

      <form action="<?= base_url('paciente/' . esc($token, 'url') . '/verify') ?>" method="post" id="patientVerifyForm" autocomplete="off">
        <?= csrf_field() ?>

        <div class="mb-3">
          <label for="patient_id" class="form-label fw-semibold">Número de documento (DNI / NIE / Pasaporte)</label>
          <input type="text" class="form-control form-control-lg" id="patient_id" name="patient_id" placeholder="Ej: 12345678A" autocomplete="off" autofocus required>
        </div>

        <div class="mb-4">
          <label for="patient_birth_date" class="form-label fw-semibold">Fecha de nacimiento</label>
          <input type="date" class="form-control form-control-lg" id="patient_birth_date" name="patient_birth_date" autocomplete="off" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          <i class="ti ti-lock-open me-1"></i> Acceder a mi estudio
        </button>
      </form>

      <p class="text-center text-muted fs-2 mt-4 mb-0">
        Acceso personal e intransferible protegido conforme a la normativa de protección de datos de salud.
      </p>
    </div>
  </div>

  <script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('patientVerifyForm');
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
