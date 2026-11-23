<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Limpieza temporal de CodeIgniter 4</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= esc($tempSizeFormatted ?? '0.0 KB') ?></span>
  </div>
  <div class="card-body text-center">
    <p class="text-muted mb-3">Elimina archivos de logs, sesiones inactivas, caché y registros de debugbar en <code>writable/</code> sin afectar la base de datos ni tu sesión actual.</p>
    <form method="post" action="<?= base_url('settings/clear-temp') ?>" class="d-inline" onsubmit="return confirm('¿Eliminar logs, sesiones antiguas, caché y debugbar?');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="ti ti-trash me-1"></i> Limpiar logs, sesiones y caché
      </button>
    </form>
  </div>
</div>
