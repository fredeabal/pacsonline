<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Configuración de correo (SMTP)</h5>
    <span class="badge <?= $smtp['enabled'] ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?> fs-2">
      <?= $smtp['enabled'] ? 'Notificaciones activas' : 'Desactivado' ?>
    </span>
  </div>

  <div class="card-body">
    <form action="<?= base_url('settings/smtp') ?>" method="post">
      <?= csrf_field() ?>

      <div class="form-check form-switch mb-4">
        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" value="1" <?= $smtp['enabled'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="enabled">Enviar notificación automática al paciente cuando se publique un informe</label>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label for="host" class="form-label fw-semibold">Servidor SMTP (Host)</label>
          <input type="text" class="form-control" id="host" name="host" value="<?= esc(old('host', $smtp['host'])) ?>" placeholder="smtp.tu-dominio.com" required>
        </div>

        <div class="col-md-3">
          <label for="port" class="form-label fw-semibold">Puerto</label>
          <input type="number" class="form-control" id="port" name="port" value="<?= esc(old('port', (string) $smtp['port'])) ?>" required>
        </div>

        <div class="col-md-3">
          <label for="crypto" class="form-label fw-semibold">Cifrado</label>
          <select class="form-select" id="crypto" name="crypto">
            <option value="tls" <?= $smtp['crypto'] === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
            <option value="ssl" <?= $smtp['crypto'] === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
            <option value="none" <?= $smtp['crypto'] === '' ? 'selected' : '' ?>>Ninguno</option>
          </select>
        </div>

        <div class="col-md-6">
          <label for="user" class="form-label fw-semibold">Usuario SMTP</label>
          <input type="text" class="form-control" id="user" name="user" value="<?= esc(old('user', $smtp['user'])) ?>">
        </div>

        <div class="col-md-6">
          <label for="pass" class="form-label fw-semibold">Contraseña SMTP</label>
          <input type="password" class="form-control" id="pass" name="pass" placeholder="<?= $smtp['hasPass'] ? '•••••••• (dejar vacío para mantener)' : 'Introduce la contraseña' ?>">
        </div>

        <div class="col-md-6">
          <label for="from_email" class="form-label fw-semibold">Correo remitente</label>
          <input type="email" class="form-control" id="from_email" name="from_email" value="<?= esc(old('from_email', $smtp['fromEmail'])) ?>" placeholder="informes@tuclinica.com" required>
        </div>

        <div class="col-md-6">
          <label for="from_name" class="form-label fw-semibold">Nombre remitente</label>
          <input type="text" class="form-control" id="from_name" name="from_name" value="<?= esc(old('from_name', $smtp['fromName'])) ?>" required>
        </div>
      </div>

      <div class="d-flex justify-content-center mt-4">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ti ti-device-floppy me-1"></i> Guardar Configuración
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Probar envío de correo</h5>
  </div>
  <div class="card-body">
    <form action="<?= base_url('settings/test-email') ?>" method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label for="test_email" class="form-label fw-semibold">Correo destinatario de prueba</label>
        <input type="email" class="form-control" id="test_email" name="test_email" placeholder="tu@correo.com" required>
      </div>
      <div class="d-flex justify-content-center">
        <button type="submit" class="btn btn-sm btn-outline-primary">
          <i class="ti ti-send me-1"></i> Enviar Prueba
        </button>
      </div>
    </form>
  </div>
</div>
