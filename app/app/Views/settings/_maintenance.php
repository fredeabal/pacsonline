<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Limpieza temporal de CodeIgniter 4</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= esc($tempSizeFormatted ?? '0.0 KB') ?></span>
  </div>
  <div class="card-body text-center">
    <p class="text-muted mb-3">Elimina archivos de logs, sesiones inactivas, caché y registros de debugbar en <code>writable/</code> sin afectar la base de datos ni tu sesión actual.</p>
    <form method="post" action="<?= base_url('settings/clear-temp') ?>" id="clearTempForm" class="d-inline">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-primary" id="clearTempBtn">
        <i class="ti ti-trash me-1"></i> Limpiar logs, sesiones y caché
      </button>
    </form>
  </div>
</div>

<script {csp-script-nonce}>
document.addEventListener('DOMContentLoaded', function() {
  var form = document.getElementById('clearTempForm');
  var btn  = document.getElementById('clearTempBtn');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    if (form.dataset.confirmed === '1') return;
    e.preventDefault();

    if (typeof Swal === 'undefined') {
      if (window.confirm('¿Eliminar logs, sesiones antiguas, caché y debugbar?')) {
        form.dataset.confirmed = '1';
        form.submit();
      }
      return;
    }

    Swal.fire({
      icon: 'warning',
      title: '¿Limpiar archivos temporales?',
      text: 'Se eliminarán logs, sesiones inactivas, caché y registros de debugbar en writable/ sin afectar la base de datos ni tu sesión actual.',
      showCancelButton: true,
      confirmButtonText: 'Sí, limpiar',
      cancelButtonText: 'Cancelar',
      reverseButtons: true
    }).then(function(result) {
      if (result.isConfirmed) {
        form.dataset.confirmed = '1';
        if (btn && !btn.disabled) {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Limpiando...';
        }
        form.submit();
      }
    });
  });
});
</script>
