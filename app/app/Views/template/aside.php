    <aside class="left-sidebar with-vertical">
      <div>
        <div class="brand-logo d-flex align-items-center justify-content-between">
          <a href="<?= base_url() ?>" class="text-nowrap logo-img text-decoration-none">
            <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo" alt="PACS Online" />
            <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo" alt="PACS Online" />
          </a>
          <button type="button" class="sidebartoggler ms-auto btn border-0 bg-transparent text-body fs-5 d-block d-xl-none p-1 shadow-none" aria-label="Cerrar menú lateral">
            <i class="ti ti-x"></i>
          </button>
        </div>

        <?php
          $user         = auth()->user();
          $isSupervisor = $user?->can('users.manage') ?? false;
          $canReview    = $user?->can('reports.review') ?? false;
          $canUpload    = $user?->can('studies.upload') ?? false;
          $isMedicoOnly = $canReview && ! $isSupervisor;
        ?>

        <nav class="sidebar-nav scroll-sidebar" data-simplebar>
          <ul id="sidebarnav">
            <li class="nav-small-cap">
              <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
              <span class="hide-menu">Menú</span>
            </li>
            <li class="sidebar-item">
              <a class="sidebar-link" href="<?= base_url() ?>" aria-expanded="false">
                <span><i class="ti ti-layout-dashboard"></i></span>
                <span class="hide-menu">Dashboard</span>
              </a>
            </li>

            <?php if (! $isMedicoOnly): ?>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('studies') ?>" aria-expanded="false">
                  <span><i class="ti ti-report-medical"></i></span>
                  <span class="hide-menu">Estudios DICOM</span>
                </a>
              </li>
            <?php endif; ?>

            <?php if ($canUpload): ?>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('studies/upload') ?>" aria-expanded="false">
                  <span><i class="ti ti-cloud-upload"></i></span>
                  <span class="hide-menu">Subir Estudio</span>
                </a>
              </li>
            <?php endif; ?>

            <?php if ($canReview): ?>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('reports') ?>" aria-expanded="false">
                  <span><i class="ti ti-stethoscope"></i></span>
                  <span class="hide-menu">Informar Estudios</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('templates') ?>" aria-expanded="false">
                  <span><i class="ti ti-template"></i></span>
                  <span class="hide-menu">Mis Plantillas</span>
                </a>
              </li>
            <?php endif; ?>

            <?php if ($isSupervisor): ?>
              <li class="nav-small-cap mt-4">
                <i class="ti ti-dots nav-small-cap-icon fs-4"></i>
                <span class="hide-menu">Administración</span>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('users') ?>" aria-expanded="false">
                  <span><i class="ti ti-users"></i></span>
                  <span class="hide-menu">Usuarios</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('centers') ?>" aria-expanded="false">
                  <span><i class="ti ti-building-hospital"></i></span>
                  <span class="hide-menu">Centros Médicos</span>
                </a>
              </li>
              <li class="sidebar-item">
                <a class="sidebar-link" href="<?= base_url('settings') ?>" aria-expanded="false">
                  <span><i class="ti ti-settings"></i></span>
                  <span class="hide-menu">Configuración</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
    </aside>
    <div class="page-wrapper">
