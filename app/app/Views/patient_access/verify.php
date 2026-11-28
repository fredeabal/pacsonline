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
  <meta name="referrer" content="no-referrer" />
  <title>PACSLine | Verificación de Identidad</title>
  <meta name="theme-color" content="#5d87ff" />

  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logos/favicon.svg') ?>" />
  <link rel="alternate icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="apple-touch-icon" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') ?>" />
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
                Tus estudios e informes médicos,<br>seguros y a un clic.
              </h1>
              <p class="text-white fs-5 mb-0 opacity-75">
                Visualiza tus imágenes DICOM en alta resolución y descarga tu informe clínico desde cualquier dispositivo sin necesidad de crear cuenta.
              </p>
            </div>
          </div>

          <!-- Columna derecha: Verificación de identidad -->
          <div class="col-12 col-xl-5 col-xxl-4 bg-body d-flex justify-content-center align-items-center p-4 min-vh-100">
            <div class="auth-max-width w-100 col-sm-8 col-md-6 col-xl-9 px-4">
              <div class="text-nowrap logo-img d-flex align-items-center justify-content-center mb-5">
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo login-logo" alt="PACSLine" />
                <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo login-logo" alt="PACSLine" />
              </div>

              <h3 class="mb-1 fw-bolder">Verificación de Identidad</h3>
              <p class="text-muted mb-4">Confirma tus datos para acceder de forma segura a tu estudio médico.</p>

              <form action="<?= base_url('paciente/' . esc($token, 'url') . '/verify') ?>" method="post" id="patientVerifyForm" autocomplete="off">
                <?= csrf_field() ?>

                <div class="mb-3">
                  <label for="patient_id" class="form-label fw-semibold">Número de identificación</label>
                  <input type="text" class="form-control" id="patient_id" name="patient_id" placeholder="Ej: 12345678A" autocomplete="off" autofocus required>
                </div>

                <div class="mb-4">
                  <label for="patient_birth_date" class="form-label fw-semibold">Fecha de nacimiento</label>
                  <input type="text" class="form-control datepicker-input" id="patient_birth_date" name="patient_birth_date" placeholder="dd/mm/aaaa" autocomplete="off" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fs-4 fw-semibold rounded-2 shadow-sm" id="verifyBtn">
                  <i class="ti ti-lock-open me-1"></i> Acceder a mi estudio
                </button>
              </form>

              <p class="text-center text-muted fs-2 mt-4 mb-0">
                <i class="ti ti-shield-lock me-1"></i>Acceso personal e intransferible protegido conforme a la normativa de protección de datos de salud.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/jquery/dist/jquery.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/particles.js/particles.min.js') ?>"></script>

  <script {csp-script-nonce}>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof jQuery !== 'undefined' && typeof jQuery.fn.datepicker !== 'undefined') {
        jQuery.fn.datepicker.dates['es'] = {
          days: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
          daysShort: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
          daysMin: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
          months: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
          monthsShort: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
          today: 'Hoy',
          clear: 'Limpiar',
          format: 'dd/mm/yyyy',
          titleFormat: 'MM yyyy',
          weekStart: 1
        };
        jQuery('input.datepicker-input').datepicker({
          format: 'dd/mm/yyyy',
          language: 'es',
          autoclose: true,
          todayHighlight: true,
          clearBtn: true,
          orientation: 'bottom auto'
        });
      }
      const toastMessage = <?= session()->has('message') ? json_encode(session('message')) : 'null' ?>;
      const toastError   = <?= session()->has('error') ? json_encode(session('error')) : 'null' ?>;
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

      if (toastMessage) {
        systemAlert.fire({ icon: 'success', title: '¡Completado!', text: String(toastMessage), iconColor: '#34c759' });
      }
      if (toastError) {
        systemAlert.fire({ icon: 'error', title: 'Error de verificación', text: String(toastError), iconColor: '#b31b34' });
      }

      const form = document.getElementById('patientVerifyForm');
      const btn  = document.getElementById('verifyBtn');
      if (form && btn) {
        form.addEventListener('submit', function(e) {
          if (btn.disabled) {
            e.preventDefault();
            return;
          }
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verificando identidad...';
        });
      }

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
