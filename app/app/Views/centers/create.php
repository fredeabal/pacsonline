<?= view('template/header') ?>

<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Registrar centro médico</h5>
    <a href="<?= base_url('centers') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <form action="<?= base_url('centers') ?>" method="post">
      <?= csrf_field() ?>

      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <label for="name" class="form-label fw-semibold">Nombre del Centro / Nodo</label>
          <input type="text" class="form-control" id="name" name="name" placeholder="Ej: Clínica Diagnóstica Norte u OsiriX Consulta" value="<?= old('name') ?>" required>
        </div>
        <div class="col-md-5">
          <label for="ae_title" class="form-label fw-semibold">AE Title</label>
          <input type="text" class="form-control text-uppercase" id="ae_title" name="ae_title" placeholder="Ej: CLINICA_NORTE" value="<?= old('ae_title') ?>" required>
          <div class="form-text">Identificador DICOM único sin espacios.</div>
        </div>
      </div>

      <div class="border rounded-2 p-3 mb-3">
        <div class="row g-3 mb-3 <?= old('can_send') ? '' : 'd-none' ?>" id="sendNodeFields">
          <div class="col-md-8">
            <label for="dicom_host" class="form-label fw-semibold">IP o Host del nodo destino</label>
            <input type="text" class="form-control" id="dicom_host" name="dicom_host" placeholder="Ej: 192.168.0.50" value="<?= old('dicom_host') ?>">
          </div>
          <div class="col-md-4">
            <label for="dicom_port" class="form-label fw-semibold">Puerto DICOM</label>
            <input type="number" class="form-control" id="dicom_port" name="dicom_port" min="1" max="65535" placeholder="Ej: 11112 o 4242" value="<?= old('dicom_port', '11112') ?>">
          </div>
        </div>

        <span class="form-label fw-semibold d-block mb-2">Funciones del nodo</span>
        <div class="d-flex flex-wrap gap-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="can_receive" name="can_receive" value="1" <?= old('can_receive', '1') ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="can_receive">Recibir estudios</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="can_send" name="can_send" value="1" <?= old('can_send') ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="can_send">Enviar estudios</label>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-4">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-building-hospital me-1"></i> Registrar Centro / Nodo
        </button>
      </div>
    </form>
  </div>
</div>

<?= $this->section('scripts') ?>
<script {csp-script-nonce}>
  const canSendCheck = document.getElementById('can_send');
  const sendFields   = document.getElementById('sendNodeFields');
  const hostInput    = document.getElementById('dicom_host');
  const portInput    = document.getElementById('dicom_port');

  function syncSendFields() {
    const enabled = canSendCheck && canSendCheck.checked;
    if (sendFields) sendFields.classList.toggle('d-none', !enabled);
    if (hostInput) hostInput.required = enabled;
    if (portInput) portInput.required = enabled;
  }

  if (canSendCheck) {
    canSendCheck.addEventListener('change', syncSendFields);
    syncSendFields();
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
