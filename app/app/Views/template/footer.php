      </div>
    </div>
  </div>

  <script src="<?= base_url('assets/libs/jquery/dist/jquery.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/simplebar/dist/simplebar.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.init.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/theme.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/app.min.js') ?>"></script>
  <script src="<?= base_url('assets/js/theme/sidebarmenu.js') ?>"></script>
  <script src="<?= base_url('assets/libs/select2/dist/js/select2.full.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') ?>"></script>
  <script src="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.js') ?>"></script>

  <script {csp-script-nonce}>
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

    (function() {
      var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
      applyTheme(current);
    })();

    document.querySelectorAll('.dark-layout').forEach(function(el) {
      el.addEventListener('click', function() { applyTheme('dark'); });
    });
    document.querySelectorAll('.light-layout').forEach(function(el) {
      el.addEventListener('click', function() { applyTheme('light'); });
    });

    setTimeout(function() {
      document.querySelectorAll('.alert-dismissible').forEach(function(el) {
        if (typeof bootstrap !== 'undefined') {
          new bootstrap.Alert(el).close();
        } else {
          el.style.display = 'none';
        }
      });
    }, 4000);

    document.addEventListener("DOMContentLoaded", function() {
      const toastMessage = <?= json_encode(session()->getFlashdata('message') ?? session()->getFlashdata('success')) ?>;
      const toastError   = <?= json_encode(session()->getFlashdata('error')) ?>;
      const toastErrors  = <?= json_encode(session()->getFlashdata('errors')) ?>;
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

      // Inicializar Select2 con buscador en todos los <select>
      if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        jQuery('select.form-select').each(function() {
          const $el = jQuery(this);
          $el.select2({
            width: '100%',
            minimumResultsForSearch: 0,
            dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : jQuery(document.body),
            language: {
              noResults: function() { return 'Sin resultados'; }
            }
          }).on('select2:select select2:clear', function() {
            this.dispatchEvent(new Event('change', { bubbles: true }));
          });
        });
      }

      // Inicializar Bootstrap Datepicker en inputs de fecha (.datepicker-input)
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

        jQuery('input.datepicker-input').each(function() {
          const $dp = jQuery(this);
          $dp.datepicker({
            format: 'dd/mm/yyyy',
            language: 'es',
            autoclose: true,
            todayHighlight: true,
            clearBtn: true,
            orientation: 'bottom auto'
          }).on('changeDate clearDate', function() {
            this.dispatchEvent(new Event('change', { bubbles: true }));
          });
        });
      }

      // Buscador global reutilizable para tablas y tarjetas móviles
      document.querySelectorAll('.table-search-input').forEach(function(input) {
        input.addEventListener('input', function() {
          const q              = input.value.toLowerCase().trim();
          const targetSelector = input.getAttribute('data-target');
          const cardsSelector  = input.getAttribute('data-cards-target');

          const table = targetSelector ? document.querySelector(targetSelector) : null;
          if (table) {
            table.querySelectorAll('tbody tr').forEach(function(row) {
              row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
          }

          const cardsWrap = cardsSelector ? document.querySelector(cardsSelector) : null;
          if (cardsWrap) {
            cardsWrap.querySelectorAll('.mobile-card-item').forEach(function(card) {
              card.style.display = card.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
          }
        });
      });

      // Spinner automático en botones de guardar / envío de formularios (.btn[type="submit"])
      document.addEventListener('submit', function(e) {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        const btn = (e.submitter && e.submitter.matches('button[type="submit"].btn'))
          ? e.submitter
          : form.querySelector('button[type="submit"].btn');
        if (!btn || btn.classList.contains('dropdown-item') || btn.querySelector('.spinner-border') || btn.disabled) {
          return;
        }

        setTimeout(function() {
          if (e.defaultPrevented) return;
          if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
          }
          const icon = btn.querySelector('i.ti');
          const spinnerHtml = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>';
          if (icon) {
            icon.outerHTML = spinnerHtml;
          } else {
            btn.insertAdjacentHTML('afterbegin', spinnerHtml);
          }
          btn.disabled = true;
        }, 0);
      });

      window.addEventListener('pageshow', function(event) {
        if (!event.persisted) return;
        document.querySelectorAll('button[type="submit"].btn[data-original-html]').forEach(function(btn) {
          btn.innerHTML = btn.dataset.originalHtml;
          btn.disabled = false;
        });
      });
    });
  </script>
  <?= $this->renderSection('scripts') ?>
</body>
</html>
