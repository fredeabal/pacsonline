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
  <title>PACS Online | Recuperar Acceso</title>
  <meta name="theme-color" content="#5d87ff" />

  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logos/favicon.svg') ?>" />
  <link rel="alternate icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="apple-touch-icon" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100">
      <div class="position-relative z-index-5">
        <div class="row min-vh-100 g-0">
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative">
            <div id="particles-js" class="position-absolute w-100 h-100 top-0 start-0 z-index-1"></div>

            <div class="px-5 position-relative z-index-2 text-center text-xl-start mx-auto w-100" style="max-width: 680px;">
              <h1 class="text-white display-4 fw-bolder mb-4" style="line-height: 1.15;">
                Recupera el acceso<br>de forma segura.
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75">
                Te enviaremos un enlace temporal de un solo uso a tu correo electrónico para entrar sin contraseña.
              </p>
            </div>
          </div>

          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 min-vh-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4">
              <a href="<?= base_url() ?>" class="text-nowrap logo-img d-flex align-items-center justify-content-center mb-5 text-decoration-none">
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo login-logo" alt="PACS Online" />
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo login-logo" alt="PACS Online" />
              </a>

              <h3 class="mb-1 fw-bolder">Recuperar Acceso</h3>
              <p class="text-muted mb-4">Introduce tu correo electrónico para recibir un enlace de acceso directo.</p>

              <form action="<?= url_to('magic-link') ?>" method="post" id="magicLinkForm">
                <?= csrf_field() ?>

                <div class="mb-4">
                  <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                  <input type="email" class="form-control" id="email" name="email" inputmode="email" autocomplete="email" placeholder="medico@clinica.com" value="<?= old('email', auth()->user()->email ?? '') ?>" autofocus required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fs-4 fw-semibold rounded-2 shadow-sm mb-3">
                  <i class="ti ti-mail-forward me-1"></i> Enviar Enlace de Acceso
                </button>

                <div class="text-center">
                  <a href="<?= url_to('login') ?>" class="text-muted fs-3 text-decoration-none">
                    <i class="ti ti-arrow-left me-1"></i> Volver a Iniciar Sesión
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/particles.js/particles.min.js') ?>"></script>
  <script {csp-script-nonce}>
    document.addEventListener("DOMContentLoaded", function() {
      const magicLinkForm = document.getElementById('magicLinkForm');
      if (magicLinkForm) {
        magicLinkForm.addEventListener('submit', function() {
          const btn = magicLinkForm.querySelector('button[type="submit"]');
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
      }
      const toastMessage = <?= session()->has('message') ? json_encode(session('message')) : 'null' ?>;
      const toastError   = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      const toastErrors  = <?= session()->has('errors') ? json_encode(session('errors')) : 'null' ?>;
      const isDark       = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      const systemAlert  = Swal.mixin({
        position: 'center', showConfirmButton: false, buttonsStyling: false,
        timer: 4500, timerProgressBar: true,
        background: isDark ? '#0b1114' : '#f8f9fa', color: isDark ? '#fff' : '#0b1114'
      });
      const escapeHtml = (str) => String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
      if (toastMessage) systemAlert.fire({ icon: 'success', title: '¡Completado!', text: String(toastMessage), iconColor: '#34c759' });
      if (toastError) systemAlert.fire({ icon: 'error', title: 'Error', text: String(toastError), iconColor: '#b31b34' });
      if (toastErrors) {
        const msg = typeof toastErrors === 'object' && toastErrors !== null
          ? (Array.isArray(toastErrors) ? toastErrors : Object.values(toastErrors)).map(escapeHtml).join('<br>')
          : escapeHtml(toastErrors);
        systemAlert.fire({ icon: 'error', title: 'Datos inválidos', html: `<div class="text-center">${msg}</div>`, iconColor: '#b31b34' });
      }
      if (document.getElementById('particles-js') && typeof particlesJS === 'function') {
        particlesJS("particles-js", {
          "particles": {
            "number": { "value": 70, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#ffffff" }, "shape": { "type": "circle" },
            "opacity": { "value": 0.45 }, "size": { "value": 3, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#ffffff", "opacity": 0.35, "width": 1 },
            "move": { "enable": true, "speed": 4, "out_mode": "out" }
          },
          "retina_detect": true
        });
      }
    });
  </script>
</body>
</html>
