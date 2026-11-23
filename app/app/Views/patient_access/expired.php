<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
  <script {csp-script-nonce}>
    (function() {
      var saved = localStorage.getItem('theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>PACS Online | Enlace no disponible</title>
  <meta name="theme-color" content="#5d87ff" />

  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logos/favicon.svg') ?>" />
  <link rel="alternate icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="apple-touch-icon" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100">
      <div class="position-relative z-index-5">
        <div class="row min-vh-100 g-0">
          <!-- Columna izquierda: Fondo Azul y Partículas (Modernize) -->
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative">
            <div id="particles-js" class="position-absolute w-100 h-100 top-0 start-0 z-index-1"></div>

            <div class="px-5 position-relative z-index-2 text-center text-xl-start mx-auto w-100" style="max-width: 680px;">
              <h1 class="text-white display-4 fw-bolder mb-4" style="line-height: 1.15;">
                Tus estudios e informes médicos,<br>protegidos en todo momento.
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75">
                Por motivos de seguridad y protección de datos, los enlaces de acceso tienen validez temporal y se bloquean automáticamente tras varios intentos fallidos.
              </p>
            </div>
          </div>

          <!-- Columna derecha: Aviso -->
          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 min-vh-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4 text-center">
              <div class="text-nowrap logo-img d-flex align-items-center justify-content-center mb-5">
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo login-logo" alt="PACS Online" />
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo login-logo" alt="PACS Online" />
              </div>

              <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-3" style="width: 64px; height: 64px;">
                <i class="ti ti-link-off fs-8"></i>
              </span>

              <h3 class="fw-bolder mb-2">Enlace no disponible</h3>
              <p class="text-muted fs-4 mb-0">
                Este enlace ha caducado, ha sido revocado o se ha bloqueado por seguridad tras varios intentos fallidos. Por favor, contacta con tu centro médico para solicitar uno nuevo.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/particles.js/particles.min.js') ?>"></script>
  <script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function() {
      if (document.getElementById('particles-js') && typeof particlesJS === 'function') {
        particlesJS('particles-js', {
          particles: {
            number: { value: 70, density: { enable: true, value_area: 800 } },
            color: { value: '#ffffff' },
            shape: { type: 'circle' },
            opacity: { value: 0.45, random: false },
            size: { value: 3, random: true },
            line_linked: { enable: true, distance: 150, color: '#ffffff', opacity: 0.35, width: 1 },
            move: { enable: true, speed: 4, direction: 'none', out_mode: 'out' }
          },
          interactivity: {
            detect_on: 'canvas',
            events: { onhover: { enable: true, mode: 'repulse' }, resize: true },
            modes: { repulse: { distance: 160, duration: 0.4 } }
          },
          retina_detect: true
        });
      }
    });
  </script>
</body>
</html>
