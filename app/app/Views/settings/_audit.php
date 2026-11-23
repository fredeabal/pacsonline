<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Auditoría reciente</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2">Últimos 200 eventos</span>
  </div>

  <div class="card-body">
    <div class="input-group mb-3">
      <span class="input-group-text"><i class="ti ti-search"></i></span>
      <input type="text" class="form-control table-search-input" data-target="#settingsAuditTable" data-cards-target="#settingsAuditCards" placeholder="Buscar por usuario, acción, detalle o IP...">
    </div>

    <div class="mb-4 border rounded-1 table-responsive d-none d-md-block">
      <table class="table mb-0 align-middle table-hover" id="settingsAuditTable">
        <thead>
          <tr>
            <th class="text-nowrap">Fecha</th>
            <th class="text-nowrap">Usuario</th>
            <th class="text-nowrap">Acción</th>
            <th>Detalle</th>
            <th class="text-nowrap">IP</th>
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
            <?php foreach ($logs as $log):
              $userDisplay  = trim((string) ($log['user_full_name'] ?? '')) ?: trim((string) ($log['user_username'] ?? ''));
              $userUsername = trim((string) ($log['user_username'] ?? ''));
              $actionLabel  = \App\Models\AuditLogModel::actionLabel((string) $log['action']);
              $studyUid     = trim((string) ($log['study_instance_uid'] ?? ''));
            ?>
            <tr>
              <td class="text-nowrap"><?= esc(format_datetime($log['created_at'] ?? null)) ?></td>
              <td class="text-nowrap">
                <?php if ($userDisplay !== ''): ?>
                  <span class="fw-medium"><?= esc($userDisplay) ?></span><?php if ($userUsername !== '' && $userUsername !== $userDisplay): ?><span class="d-none"> <?= esc($userUsername) ?></span><?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <?php if ($studyUid !== ''): ?>
                  <a href="<?= base_url('studies/case/' . rawurlencode($studyUid)) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold text-decoration-none" title="Ir al estudio (<?= esc($log['action']) ?>)">
                    <?= esc($actionLabel) ?>
                  </a>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2 fw-semibold" title="<?= esc($log['action']) ?>">
                    <?= esc($actionLabel) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="w-100">
                <div class="text-wrap" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word;" title="<?= esc($log['details'] ?? '') ?>"><?= esc($log['details'] ?? '-') ?></div>
              </td>
              <td class="text-nowrap"><code><?= esc($log['ip_address'] ?? '-') ?></code></td>
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
        <?php foreach ($logs as $log):
          $userDisplay  = trim((string) ($log['user_full_name'] ?? '')) ?: trim((string) ($log['user_username'] ?? ''));
          $userUsername = trim((string) ($log['user_username'] ?? ''));
          $actionLabel  = \App\Models\AuditLogModel::actionLabel((string) $log['action']);
          $studyUid     = trim((string) ($log['study_instance_uid'] ?? ''));
        ?>
        <div class="card border shadow-sm mb-3 mobile-card-item">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <?php if ($studyUid !== ''): ?>
                <a href="<?= base_url('studies/case/' . rawurlencode($studyUid)) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2 fw-semibold text-decoration-none" title="Ir al estudio">
                  <?= esc($actionLabel) ?>
                </a>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-2 fw-semibold">
                  <?= esc($actionLabel) ?>
                </span>
              <?php endif; ?>
              <span class="text-muted small"><?= esc(format_datetime($log['created_at'] ?? null)) ?></span>
            </div>
            <?php if ($userDisplay !== ''): ?>
              <div class="small text-muted mb-1">
                Usuario: <strong class="text-body"><?= esc($userDisplay) ?></strong><?php if ($userUsername !== '' && $userUsername !== $userDisplay): ?><span class="d-none"> <?= esc($userUsername) ?></span><?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="small mb-1"><?= esc($log['details'] ?? '-') ?></div>
            <div class="text-muted small text-break">IP: <code><?= esc($log['ip_address'] ?? '-') ?></code></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
