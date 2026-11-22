<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Auditoría reciente</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2">Últimos 200 eventos</span>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#settingsAuditTable" data-cards-target="#settingsAuditCards" placeholder="Buscar por acción, UID, detalle o IP...">
    </div>

    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table text-nowrap mb-0 align-middle table-hover" id="settingsAuditTable">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Acción</th>
            <th>Estudio UID</th>
            <th>Detalle</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-muted">
                <div class="py-3">
                  <i class="ti ti-history d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
                  <span>No hay eventos registrados todavía.</span>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="text-nowrap"><?= esc(format_datetime($log['created_at'] ?? null)) ?></td>
              <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($log['action']) ?></span></td>
              <td><code class="fs-2"><?= esc($log['study_instance_uid'] ?? '-') ?></code></td>
              <td><?= esc($log['details'] ?? '-') ?></td>
              <td><code><?= esc($log['ip_address'] ?? '-') ?></code></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-block d-md-none" id="settingsAuditCards">
      <?php if (empty($logs)): ?>
        <div class="text-center py-4 text-muted">
          <i class="ti ti-history d-block mb-2 text-muted opacity-75 fs-2-5rem"></i>
          <span>No hay eventos registrados todavía.</span>
        </div>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="card border shadow-sm mb-3 mobile-card-item">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold"><?= esc($log['action']) ?></span>
              <span class="text-muted small"><?= esc(format_datetime($log['created_at'] ?? null)) ?></span>
            </div>
            <div class="small mb-1"><?= esc($log['details'] ?? '-') ?></div>
            <div class="text-muted small text-break">IP: <code><?= esc($log['ip_address'] ?? '-') ?></code></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
