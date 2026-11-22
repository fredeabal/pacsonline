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
  <title>PACS Online | Iniciar Sesión</title>
  <meta name="theme-color" content="#5d87ff" />

  <link rel="shortcut icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
</head>
<body>

  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden min-vh-100 w-100">
      <div class="position-relative z-index-5">
        <div class="row min-vh-100 g-0">
          <!-- Columna izquierda: Fondo Azul y Partículas -->
          <div class="col-xl-7 col-xxl-8 d-none d-xl-flex flex-column justify-content-center align-items-center bg-primary-gradient position-relative">
            <div id="particles-js" class="position-absolute w-100 h-100 top-0 start-0 z-index-1"></div>

            <div class="px-5 position-relative z-index-2 text-center text-xl-start mx-auto w-100" style="max-width: 680px;">
              <h1 class="text-white display-4 fw-bolder mb-4" style="line-height: 1.15;">
                Diagnóstico por imagen<br>en la nube, seguro y accesible.
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75">
                Centraliza estudios DICOM, compártelos con credenciales temporales y gestiona informes médicos desde cualquier navegador.
              </p>
            </div>
          </div>

          <!-- Columna derecha: Formulario de Login -->
          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 min-vh-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4">
              <a href="<?= base_url() ?>" class="text-nowrap logo-img d-flex align-items-center justify-content-center gap-2 mb-5 text-decoration-none">
                <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white" style="width: 44px; height: 44px;">
                  <i class="ti ti-scan fs-7"></i>
                </span>
                <span class="fw-bolder fs-7 text-body">PACS <span class="text-primary">Online</span></span>
              </a>

              <h3 class="mb-1 fw-bolder">Acceder al Sistema</h3>
              <p class="text-muted mb-4">Introduce tus credenciales para continuar</p>

              <form action="<?= url_to('login') ?>" method="post" id="loginForm" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                  <label for="email" class="form-label fw-semibold">Correo Electrónico</label>
                  <input type="email" class="form-control" id="email" name="email" inputmode="email" autocomplete="email" placeholder="medico@clinica.com" value="<?= old('email') ?>" autofocus required>
                </div>

                <div class="mb-4">
                  <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="password" class="form-label fw-semibold mb-0">Contraseña</label>
                    <a href="<?= url_to('magic-link') ?>" class="text-primary fs-2 text-decoration-none fw-medium">¿Olvidaste tu contraseña?</a>
                  </div>
                  <div class="position-relative">
                    <input type="password" class="form-control pe-5" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
                    <button class="btn border-0 bg-transparent text-muted position-absolute top-50 end-0 translate-middle-y pe-3 shadow-none" type="button" id="togglePasswordBtn" title="Mostrar contraseña" aria-label="Mostrar contraseña">
                      <i class="ti ti-eye fs-5"></i>
                    </button>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fs-4 fw-semibold rounded-2 shadow-sm" id="loginBtn">
                  <i class="ti ti-login me-1"></i> Iniciar Sesión
                </button>
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
      const toggleBtn = document.getElementById('togglePasswordBtn');
      const passInput = document.getElementById('password');
      if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function() {
          const isPass = passInput.type === 'password';
          passInput.type = isPass ? 'text' : 'password';
          toggleBtn.innerHTML = isPass ? '<i class="ti ti-eye-off fs-4"></i>' : '<i class="ti ti-eye fs-4"></i>';
        });
      }

      const toastMessage = <?= session()->has('message') ? json_encode(session('message')) : 'null' ?>;
      const toastError   = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
      const toastErrors  = <?= session()->has('errors') ? json_encode(session('errors')) : 'null' ?>;
      const isDark       = document.documentElement.getAttribute('data-bs-theme') === 'dark';

      const systemAlert = Swal.mixin({
        position: 'center',
        showConfirmButton: false,
        buttonsStyling: false,
        timer: 4500,
        timerProgressBar: true,
        background: isDark ? '#0b1114' : '#f8f9fa',
        color: isDark ? '#fff' : '#0b1114',
      });

      const escapeHtml = (str) => String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

      if (toastMessage) {
        systemAlert.fire({ icon: 'success', title: '¡Completado!', text: String(toastMessage), iconColor: '#34c759' });
      }
      if (toastError) {
        systemAlert.fire({ icon: 'error', title: 'Error', text: String(toastError), iconColor: '#b31b34' });
      }
      if (toastErrors) {
        const errorContent = typeof toastErrors === 'object' && toastErrors !== null
          ? (Array.isArray(toastErrors) ? toastErrors : Object.values(toastErrors)).map(escapeHtml).join('<br>')
          : escapeHtml(toastErrors);
        systemAlert.fire({ icon: 'error', title: 'Datos inválidos', html: `<div class="text-center">${errorContent}</div>`, iconColor: '#b31b34' });
      }

      const loginForm = document.getElementById('loginForm');
      const loginBtn  = document.getElementById('loginBtn');
      if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function(e) {
          if (loginBtn.disabled) {
            e.preventDefault();
            return;
          }
          loginBtn.disabled = true;
          loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Iniciando sesión...';
        });
      }

      if (document.getElementById('particles-js') && typeof particlesJS === 'function') {
        particlesJS("particles-js", {
          "particles": {
            "number": { "value": 70, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": "#ffffff" },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.45, "random": false },
            "size": { "value": 3, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#ffffff", "opacity": 0.35, "width": 1 },
            "move": { "enable": true, "speed": 4, "direction": "none", "out_mode": "out" }
          },
          "interactivity": {
            "detect_on": "canvas",
            "events": { "onhover": { "enable": true, "mode": "repulse" }, "resize": true },
            "modes": { "repulse": { "distance": 160, "duration": 0.4 } }
          },
          "retina_detect": true
        });
      }
    });
  </script>
</body>
</html>
