<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Nueva plantilla de informe</h5>
    <a href="<?= base_url('templates') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <form action="<?= base_url('templates') ?>" method="post">
      <?= csrf_field() ?>

      <div class="row g-3 mb-3">
        <div class="col-md-8">
          <label for="name" class="form-label fw-semibold">Nombre de la plantilla</label>
          <input type="text" class="form-control" id="name" name="name" placeholder="Ej: Tórax Normal, TC Craneal sin hallazgos..." value="<?= old('name') ?>" required>
        </div>
        <div class="col-md-4">
          <label for="modality" class="form-label fw-semibold">Modalidad</label>
          <select class="form-select" id="modality" name="modality" required>
            <option value="General">General</option>
            <option value="CT">TC (Tomografía)</option>
            <option value="MR">RM (Resonancia)</option>
            <option value="CR">RX (Radiografía)</option>
            <option value="US">Ecografía</option>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label for="findings" class="form-label fw-semibold">Hallazgos predeterminados</label>
        <textarea class="form-control" id="findings" name="findings" rows="6" required><?= esc(old('findings')) ?></textarea>
      </div>

      <div class="mb-4">
        <label for="impression" class="form-label fw-semibold">Conclusión / Impresión Diagnóstica</label>
        <textarea class="form-control" id="impression" name="impression" rows="3" required><?= esc(old('impression')) ?></textarea>
      </div>

      <div class="d-flex justify-content-center gap-2">
        <a href="<?= base_url('templates') ?>" class="btn btn-sm btn-dark">Cancelar</a>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Plantilla
        </button>
      </div>
    </form>
  </div>
</div>

<?= view('template/footer') ?>
