<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="horizontal">
<?php
  $patientName = format_patient_name($study['patient_name'] ?? null, 'No especificado');
  $studyDesc   = format_study_description($study['study_description'] ?? null, 'Estudio DICOM');
  $studyDate   = format_date($study['study_date'] ?? null);
  $expiresDate = ! empty($link['expires_at']) ? format_date((string) $link['expires_at']) : '-';
  $allowZip    = (int) ($link['allow_download'] ?? 0) === 1;

  $summaryBox = static function (string $icon, string $label, string $value): string {
      return '<div class="p-3 rounded-2 border border-primary-subtle h-100">'
          . '<span class="text-primary text-uppercase fw-semibold d-flex align-items-center gap-1 mb-1" style="font-size: 0.7rem; letter-spacing: 0.05em;">'
          . '<i class="ti ti-' . esc($icon, 'attr') . '"></i> ' . esc($label) . '</span>'
          . '<span class="fw-semibold text-body fs-3 text-uppercase text-truncate d-block" title="' . esc($value, 'attr') . '">' . esc($value) . '</span>'
          . '</div>';
  };
?>
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="referrer" content="no-referrer" />
  <title>Mi Estudio Médico | PACSLine</title>
  <meta name="theme-color" content="#5d87ff" />

  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logos/favicon.svg') ?>" />
  <link rel="alternate icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="apple-touch-icon" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
</head>
<body class="min-vh-100 d-flex flex-column">

  <!-- Barra superior estilo Modernize -->
  <header class="sticky-top" style="background-color: var(--bs-body-bg);">
    <div class="container-xl py-2">
      <nav class="navbar navbar-expand p-0">
        <div class="d-flex align-items-center gap-3 text-nowrap">
          <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo" alt="PACSLine" style="height: 36px; width: auto;" />
          <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo" alt="PACSLine" style="height: 36px; width: auto;" />
          <small class="text-muted fs-2 border-start ps-3 d-none d-sm-inline">Portal de Resultados del Paciente</small>
        </div>

        <ul class="navbar-nav flex-row ms-auto align-items-center gap-2">
          <li class="nav-item nav-icon-hover-bg rounded-circle">
            <button type="button" class="nav-link dark-layout btn border-0 bg-transparent shadow-none" title="Modo oscuro" aria-label="Cambiar a modo oscuro" style="display: flex;">
              <i class="ti ti-moon fs-6"></i>
            </button>
            <button type="button" class="nav-link light-layout btn border-0 bg-transparent shadow-none" title="Modo claro" aria-label="Cambiar a modo claro" style="display: none;">
              <i class="ti ti-sun fs-6"></i>
            </button>
          </li>
          <li class="nav-item">
            <form action="<?= base_url('paciente/' . esc($token, 'url') . '/logout') ?>" method="post" class="m-0">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 fw-semibold">
                <i class="ti ti-logout fs-5"></i>
                <span class="d-none d-sm-inline">Cerrar acceso</span>
              </button>
            </form>
          </li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Contenido principal estilo Modernize -->
  <main class="flex-grow-1 py-4">
    <div class="container-xl">
      <!-- Tarjeta resumen del estudio -->
      <div class="card mb-4">
        <div class="card-header bg-primary-subtle d-flex flex-wrap justify-content-between align-items-center gap-2">
          <h5 class="mb-0 text-primary fw-semibold">
            <i class="ti ti-file-description me-1"></i> Resumen de tu estudio
          </h5>
          <span class="badge bg-success-subtle text-success border border-success-subtle fs-2 fw-semibold">
            <i class="ti ti-shield-check me-1"></i> Identidad verificada
          </span>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-4">
              <?= $summaryBox('user', 'Paciente', $patientName) ?>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <?= $summaryBox('scan', 'Estudio', $studyDesc) ?>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
              <?= $summaryBox('calendar', 'Fecha', $studyDate) ?>
            </div>
            <div class="col-6 col-md-6 col-lg-2">
              <?= $summaryBox('clock', 'Disponible hasta', $expiresDate) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Tarjetas de acciones -->
      <div class="row g-4">
        <!-- Visor médico OHIF -->
        <div class="col-12 col-md-6 <?= $allowZip ? 'col-lg-4' : '' ?>">
          <div class="card h-100 mb-0">
            <div class="card-body d-flex flex-column p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary flex-shrink-0" style="width: 48px; height: 48px;">
                  <i class="ti ti-eye fs-7"></i>
                </span>
                <div>
                  <h5 class="fw-semibold mb-0">Imágenes Médicas</h5>
                  <small class="text-muted">Visor diagnóstico OHIF</small>
                </div>
              </div>
              <p class="text-muted fs-3 mb-4 flex-grow-1">
                Explora todas las series e imágenes de tu estudio en alta calidad directamente desde el navegador.
              </p>
              <a href="<?= ohif_url((string) $link['study_instance_uid']) ?>" target="_blank" class="btn btn-primary w-100 fw-semibold">
                <i class="ti ti-eye me-1"></i> Abrir en Visor Médico
              </a>
            </div>
          </div>
        </div>

        <!-- Informe médico PDF -->
        <div class="col-12 col-md-6 <?= $allowZip ? 'col-lg-4' : '' ?>">
          <div class="card h-100 mb-0">
            <div class="card-body d-flex flex-column p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary flex-shrink-0" style="width: 48px; height: 48px;">
                  <i class="ti ti-report-medical fs-7"></i>
                </span>
                <div>
                  <h5 class="fw-semibold mb-0">Informe Médico</h5>
                  <small class="text-muted"><?= ! empty($hasReport) ? 'Firmado y disponible' : 'En proceso de redacción' ?></small>
                </div>
              </div>
              <?php if (! empty($hasReport)): ?>
                <p class="text-muted fs-3 mb-4 flex-grow-1">
                  Consulta o guarda una copia en formato PDF de tu informe radiológico firmado por el especialista.
                </p>
                <a href="<?= base_url('paciente/' . esc($token, 'url') . '/report') ?>" target="_blank" class="btn btn-primary w-100 fw-semibold">
                  <i class="ti ti-file-text me-1"></i> Ver / Descargar Informe PDF
                </a>
              <?php else: ?>
                <p class="text-muted fs-3 mb-4 flex-grow-1">
                  El especialista aún está redactando tu informe médico. Vuelve a acceder con este mismo enlace más tarde.
                </p>
                <button type="button" class="btn btn-primary w-100 fw-semibold" disabled>
                  <i class="ti ti-clock me-1"></i> Informe pendiente
                </button>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Descarga DICOM ZIP -->
        <?php if ($allowZip): ?>
        <div class="col-12 col-md-12 col-lg-4">
          <div class="card h-100 mb-0">
            <div class="card-body d-flex flex-column p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary flex-shrink-0" style="width: 48px; height: 48px;">
                  <i class="ti ti-download fs-7"></i>
                </span>
                <div>
                  <h5 class="fw-semibold mb-0">Archivo DICOM (.zip)</h5>
                  <small class="text-muted">Copia completa del estudio</small>
                </div>
              </div>
              <p class="text-muted fs-3 mb-4 flex-grow-1">
                Descarga los archivos DICOM originales comprimidos para conservarlos o entregarlos a otro médico.
              </p>
              <a href="<?= base_url('paciente/' . esc($token, 'url') . '/download') ?>" class="btn btn-primary w-100 fw-semibold">
                <i class="ti ti-download me-1"></i> Descargar Estudio (.zip)
              </a>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <p class="text-center text-muted fs-2 mt-4 mb-0">
        <i class="ti ti-shield-lock me-1"></i>Este enlace es personal e intransferible y caduca el <strong><?= esc($expiresDate) ?></strong>. Te recomendamos descargar tus resultados antes de esa fecha.
      </p>
    </div>
  </main>

  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script {csp-script-nonce}>
    (function() {
      function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
        document.querySelectorAll('.dark-layout').forEach(function(el) {
          el.style.display = theme === 'dark' ? 'none' : 'flex';
        });
        document.querySelectorAll('.light-layout').forEach(function(el) {
          el.style.display = theme === 'dark' ? 'flex' : 'none';
        });
      }

      applyTheme(document.documentElement.getAttribute('data-bs-theme') || 'light');

      document.querySelectorAll('.dark-layout').forEach(function(el) {
        el.addEventListener('click', function() { applyTheme('dark'); });
      });
      document.querySelectorAll('.light-layout').forEach(function(el) {
        el.addEventListener('click', function() { applyTheme('light'); });
      });

      const toastError = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      if (toastError && typeof Swal !== 'undefined') {
        const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        Swal.fire({
          icon: 'error',
          title: 'Aviso',
          text: String(toastError),
          timer: 4500,
          showConfirmButton: false,
          background: isDark ? '#0b1114' : '#f8f9fa',
          color: isDark ? '#fff' : '#0b1114'
        });
      }
    })();
  </script>
</body>
</html>
