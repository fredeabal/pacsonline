<?php
/**
 * Paginador estilo Bootstrap (idéntico a deca bootstrap_full) para listados paginados de Orthanc.
 *
 * @var int      $currentPage
 * @var int      $perPage
 * @var bool     $hasNext
 * @var int|null $totalStudies
 * @var string   $baseUrl
 * @var string   $extraParams
 */
$currentPage = max(1, (int) ($currentPage ?? 1));
$perPage     = max(1, (int) ($perPage ?? 25));
$hasNext     = (bool) ($hasNext ?? false);
$extraParams = (string) ($extraParams ?? '');
$totalPages  = isset($totalStudies) && $totalStudies !== null
    ? max(1, (int) ceil((int) $totalStudies / $perPage))
    : ($hasNext ? $currentPage + 1 : $currentPage);

$hasPrev  = $currentPage > 1;
$hasNextP = $hasNext || $currentPage < $totalPages;
$start    = max(1, $currentPage - 2);
$end      = min($totalPages, $currentPage + 2);

$pageUrl = static fn (int $p): string => base_url($baseUrl . '?page=' . $p . $extraParams);
?>

<div class="d-flex flex-column align-items-center gap-2 mt-3">
  <nav aria-label="Navegación de páginas">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <?php if ($hasPrev) : ?>
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
      <?php else : ?>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevrons-left fs-4"></i></span>
        </li>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevron-left fs-4"></i></span>
        </li>
      <?php endif ?>

      <?php if ($hasNextP) : ?>
        <li class="page-item">
          <a class="page-link" href="<?= $pageUrl($currentPage + 1) ?>" aria-label="Siguiente" title="Siguiente">
            <i class="ti ti-chevron-right fs-4"></i>
          </a>
        </li>
        <?php if (isset($totalStudies) && $totalStudies !== null) : ?>
          <li class="page-item">
            <a class="page-link" href="<?= $pageUrl($totalPages) ?>" aria-label="Última página" title="Última página">
              <i class="ti ti-chevrons-right fs-4"></i>
            </a>
          </li>
        <?php else : ?>
          <li class="page-item disabled">
            <span class="page-link" aria-hidden="true"><i class="ti ti-chevrons-right fs-4"></i></span>
          </li>
        <?php endif ?>
      <?php else : ?>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevron-right fs-4"></i></span>
        </li>
        <li class="page-item disabled">
          <span class="page-link" aria-hidden="true"><i class="ti ti-chevrons-right fs-4"></i></span>
        </li>
      <?php endif ?>
    </ul>
  </nav>

  <?php if (isset($totalStudies) && $totalStudies !== null) : ?>
    <div class="text-muted small">
      Página <?= $currentPage ?> de <?= number_format($totalPages) ?> · Total en PACS: <strong><?= number_format((int) $totalStudies) ?></strong> estudios
    </div>
  <?php endif ?>
</div>
