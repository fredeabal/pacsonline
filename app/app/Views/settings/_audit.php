<div class="card">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Auditoría reciente</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= number_format((int) ($auditData['total'] ?? count($logs ?? []))) ?> eventos</span>
  </div>

  <div class="card-body">
    <?= view('audit/_list', [
      'logs'      => $logs ?? [],
      'auditData' => $auditData ?? [],
      'filters'   => $auditFilters ?? [],
      'baseUrl'   => 'settings',
      'tableId'   => 'settingsAuditTable',
      'cardsId'   => 'settingsAuditCards',
    ]) ?>
  </div>
</div>
