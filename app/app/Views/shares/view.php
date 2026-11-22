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
  <title>Estudio Compartido | PACS Online</title>
  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body class="bg-body-tertiary d-flex align-items-center justify-content-center min-vh-100 p-3">
  <div class="card border-0 shadow-sm" style="max-width: 560px; width: 100%;">
    <div class="card-body p-4 p-md-5">
      <div class="d-flex align-items-center gap-3 mb-4">
        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white" style="width: 48px; height: 48px;">
          <i class="ti ti-scan fs-7"></i>
        </span>
        <div>
          <h4 class="fw-bold mb-0">Estudio DICOM Compartido</h4>
          <small class="text-muted">Acceso autorizado temporalmente</small>
        </div>
      </div>

      <div class="bg-body-secondary rounded-3 p-3 mb-4">
        <div class="mb-2"><strong>Paciente:</strong> <?= esc(format_patient_name($study['patient_name'] ?? null, 'No especificado')) ?></div>
        <div class="mb-2"><strong>Descripción:</strong> <?= esc($study['study_description'] ?? 'Estudio DICOM') ?></div>
        <div><strong>Fecha del estudio:</strong> <?= esc(format_date($study['study_date'] ?? null)) ?></div>
      </div>

      <a href="<?= ohif_url($share['study_instance_uid']) ?>" target="_blank" class="btn btn-primary btn-lg w-100 fw-semibold">
        <i class="ti ti-eye me-2"></i> Abrir Estudio en Visor OHIF
      </a>

      <?php if (! empty($share['expires_at'])): ?>
        <p class="text-center text-muted fs-2 mt-3 mb-0">
          Este enlace caduca el <?= esc(format_date($share['expires_at'])) ?>.
        </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
