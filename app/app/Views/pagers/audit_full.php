<?php
/**
 * Paginador estilo Bootstrap para el registro de auditoría.
 *
 * @var int    $currentPage
 * @var int    $totalPages
 * @var int    $total
 * @var string $baseUrl
 * @var string $extraParams
 */
$currentPage = max(1, (int) ($currentPage ?? 1));
$totalPages  = max(1, (int) ($totalPages ?? 1));
$total       = max(0, (int) ($total ?? 0));
$extraParams = (string) ($extraParams ?? '');

if ($totalPages <= 1 && $total === 0) {
    return;
}

$hasPrev = $currentPage > 1;
$hasNext = $currentPage < $totalPages;
$start   = max(1, $currentPage - 2);
$end     = min($totalPages, $currentPage + 2);

$pageUrl = static fn (int $p): string => base_url($baseUrl . '?page=' . $p . $extraParams);
?>

<div class="d-flex flex-column align-items-center gap-2 mt-3">
  <?php if ($totalPages > 1): ?>
  <nav aria-label="Navegación de páginas de auditoría">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <?php if ($hasPrev): ?>
        <li class="page-item">
          <a class="page-link" href="<?= $pageUrl(1) ?>" aria-label="Primera página" title="Primera página">
            <i class="ti ti-chevrons-left fs-4"></i>
          </a>
        </li>
        <li class="page-item">
          <a class="page-link" href="<?= $pageUrl($currentPage - 1) ?>" aria-label="Anterior" title="Anterior">
            <i class="ti ti-chevron-left fs-4"></i>
          </a>
        </li>
      <?php else: ?>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevrons-left fs-4"></i></span>
        </li>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevron-left fs-4"></i></span>
        </li>
      <?php endif ?>

      <?php if ($hasNext): ?>
        <li class="page-item">
          <a class="page-link" href="<?= $pageUrl($currentPage + 1) ?>" aria-label="Siguiente" title="Siguiente">
            <i class="ti ti-chevron-right fs-4"></i>
          </a>
        </li>
        <li class="page-item">
          <a class="page-link" href="<?= $pageUrl($totalPages) ?>" aria-label="Última página" title="Última página">
            <i class="ti ti-chevrons-right fs-4"></i>
          </a>
        </li>
      <?php else: ?>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevron-right fs-4"></i></span>
        </li>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevrons-right fs-4"></i></span>
        </li>
      <?php endif ?>
    </ul>
  </nav>
  <?php endif ?>

  <div class="text-muted small">
    Página <?= $currentPage ?> de <?= number_format($totalPages) ?> · Total: <strong><?= number_format($total) ?></strong> eventos
  </div>
</div>
