<?= view('template/header') ?>

<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Editar centro médico</h5>
    <a href="<?= base_url('centers') ?>" class="btn btn-sm btn-outline-primary">
      <i class="ti ti-arrow-left fs-4"></i> <span class="d-none d-md-inline ms-1">Volver</span>
    </a>
  </div>

  <div class="card-body">
    <form action="<?= base_url('centers/update/' . (int) $center['id']) ?>" method="post">
      <?= csrf_field() ?>

      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="active" name="active" value="1" <?= $center['active'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="active">Centro / nodo activo</label>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-7">
          <label for="name" class="form-label fw-semibold">Nombre del Centro / Nodo</label>
          <input type="text" class="form-control" id="name" name="name" value="<?= esc(old('name', $center['name'])) ?>" required>
        </div>
        <div class="col-md-5">
          <label for="ae_title" class="form-label fw-semibold">AE Title</label>
          <input type="text" class="form-control text-uppercase" id="ae_title" name="ae_title" value="<?= esc(old('ae_title', $center['ae_title'])) ?>" required>
        </div>
      </div>

      <?php
        $canReceiveChecked = old('name') !== null ? (bool) old('can_receive') : (bool) ($center['can_receive'] ?? 1);
        $canSendChecked    = old('name') !== null ? (bool) old('can_send') : (bool) ($center['can_send'] ?? 0);
      ?>
      <div class="border rounded-2 p-3 mb-4">
        <div class="row g-3 mb-3 <?= $canSendChecked ? '' : 'd-none' ?>" id="sendNodeFields">
          <div class="col-md-8">
            <label for="dicom_host" class="form-label fw-semibold">IP o Host del nodo destino</label>
            <input type="text" class="form-control" id="dicom_host" name="dicom_host" placeholder="Ej: 192.168.0.50" value="<?= esc((string) old('dicom_host', (string) ($center['dicom_host'] ?? ''))) ?>">
          </div>
          <div class="col-md-4">
            <label for="dicom_port" class="form-label fw-semibold">Puerto DICOM</label>
            <input type="number" class="form-control" id="dicom_port" name="dicom_port" min="1" max="65535" placeholder="Ej: 11112 o 4242" value="<?= esc((string) old('dicom_port', (string) ($center['dicom_port'] ?? '11112'))) ?>">
          </div>
        </div>

        <span class="form-label fw-semibold d-block mb-2">Funciones del nodo</span>
        <div class="d-flex flex-wrap gap-4">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="can_receive" name="can_receive" value="1" <?= $canReceiveChecked ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="can_receive">Recibir estudios</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="can_send" name="can_send" value="1" <?= $canSendChecked ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold" for="can_send">Enviar estudios</label>
          </div>
        </div>
      </div>

      <div class="d-flex flex-wrap justify-content-center gap-2">
        <?php if (! empty($center['can_send']) && ! empty($center['dicom_host']) && ! empty($center['dicom_port'])): ?>
          <button type="button" class="btn btn-sm btn-outline-primary" id="testEchoBtn" data-url="<?= base_url('centers/test-connection/' . (int) $center['id']) ?>">
            <i class="ti ti-plug-connected me-1"></i> Probar conexión (C-Echo)
          </button>
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Cambios
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Seguridad y Token del Gateway</h5>
  </div>
  <div class="card-body text-center">
    <p class="text-muted mb-3">Si el gateway del centro fue comprometido o necesitas reconfigurarlo, puedes rotar su token API. El token anterior dejará de funcionar inmediatamente.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <form action="<?= base_url('centers/rotate-token/' . (int) $center['id']) ?>" method="post" class="d-inline" id="rotateTokenForm">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline-danger">
          <i class="ti ti-refresh me-1"></i> Rotar Token API
        </button>
      </form>
      <form action="<?= base_url('centers/delete/' . (int) $center['id']) ?>" method="post" class="d-inline" id="deleteCenterForm">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-danger">
          <i class="ti ti-trash me-1"></i> Eliminar nodo
        </button>
      </form>
    </div>
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

  const testEchoBtn = document.getElementById('testEchoBtn');
  if (testEchoBtn) {
    testEchoBtn.addEventListener('click', async function() {
      const url = testEchoBtn.getAttribute('data-url');
      const originalHtml = testEchoBtn.innerHTML;
      testEchoBtn.disabled = true;
      testEchoBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Probando...';
      try {
        const body = new URLSearchParams();
        body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        const resp = await fetch(url, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: body
        });
        const data = await resp.json();
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: data.ok ? 'success' : 'error',
            title: data.ok ? 'Conexión exitosa' : 'Error de conexión',
            text: data.message || ''
          });
        } else {
          alert(data.message || (data.ok ? 'Conexión exitosa' : 'Error de conexión'));
        }
      } catch (err) {
        alert('Error al probar la conexión C-Echo.');
      } finally {
        testEchoBtn.disabled = false;
        testEchoBtn.innerHTML = originalHtml;
      }
    });
  }

  const rotateForm = document.getElementById('rotateTokenForm');
  if (rotateForm) {
    rotateForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Rotar token?',
          text: 'El token actual dejará de funcionar.',
          showCancelButton: true,
          confirmButtonText: 'Sí, rotar',
          cancelButtonText: 'Cancelar',
          customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-outline-secondary'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            rotateForm.submit();
          }
        });
      } else if (confirm('¿Rotar token API? El token actual dejará de funcionar.')) {
        rotateForm.submit();
      }
    });
  }

  const deleteCenterForm = document.getElementById('deleteCenterForm');
  if (deleteCenterForm) {
    deleteCenterForm.addEventListener('submit', function(e) {
      e.preventDefault();
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'warning',
          title: '¿Eliminar nodo?',
          text: 'Se eliminará este centro/nodo de forma permanente.',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
          customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-outline-secondary'
          },
          buttonsStyling: false
        }).then(function(result) {
          if (result.isConfirmed) {
            deleteCenterForm.submit();
          }
        });
      } else if (confirm('¿Eliminar este centro/nodo de forma permanente?')) {
        deleteCenterForm.submit();
      }
    });
  }
</script>
<?= $this->endSection() ?>

<?= view('template/footer') ?>
