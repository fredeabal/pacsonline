<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical" data-sidebartype="full" data-sidebar-position="fixed" data-header-position="fixed">
<?php
  $currentUser  = auth()->user();
  $avatarUrl    = base_url('assets/images/profile/default-avatar.svg');
  $displayName  = $currentUser ? (!empty($currentUser->username) ? $currentUser->username : 'Usuario') : 'Usuario';
  $displayEmail = $currentUser ? ($currentUser->email ?? '') : '';
  $userGroups   = $currentUser ? $currentUser->getGroups() : [];
  $displayRole  = !empty($userGroups) ? (config('AuthGroups')->groups[$userGroups[0]]['title'] ?? ucfirst($userGroups[0])) : 'Usuario';
?>
<script {csp-script-nonce}>
  (function() {
    var saved = localStorage.getItem('theme');
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', theme);
    try { localStorage.setItem('i18nextLng', 'es'); } catch (e) {}
  })();
</script>

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="PACS Online — Gestión de estudios DICOM, compartición segura y segunda opinión médica." />

  <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/images/logos/favicon.svg') ?>" />
  <link rel="alternate icon" type="image/png" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="apple-touch-icon" href="<?= base_url('assets/images/logos/favicon.png') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/styles.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/custom.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/custom.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/sweetalert2/dist/sweetalert2.min.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/select2/dist/css/select2.min.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/libs/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css') ?>" />

  <title><?= esc($title ?? 'PACS Online') ?></title>
</head>

<body>
  <div id="main-wrapper">
    <?= view('template/aside') ?>
    <header class="topbar">
      <div class="with-vertical">
        <nav class="navbar navbar-expand-lg p-0">
          <ul class="navbar-nav">
            <li class="nav-item nav-icon-hover-bg rounded-circle ms-n2">
              <button type="button" class="nav-link sidebartoggler btn border-0 bg-transparent shadow-none" id="headerCollapse" aria-label="Alternar menú lateral">
                <i class="ti ti-menu-2"></i>
              </button>
            </li>
          </ul>

          <div class="d-block d-lg-none py-3">
            <a href="<?= base_url() ?>" class="text-nowrap logo-img text-decoration-none d-flex align-items-center">
              <img src="<?= base_url('assets/images/logos/pacs_line_logo_light.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_light.svg') ?>" class="light-logo" alt="PACS Online" style="height: 32px; width: auto;" />
              <img src="<?= base_url('assets/images/logos/pacs_line_logo_dark.svg') ?>?v=<?= filemtime(FCPATH . 'assets/images/logos/pacs_line_logo_dark.svg') ?>" class="dark-logo" alt="PACS Online" style="height: 32px; width: auto;" />
            </a>
          </div>

          <div class="d-flex align-items-center justify-content-end ms-auto" id="navbarNav">
            <ul class="navbar-nav flex-row ms-auto align-items-center justify-content-center gap-2">
              <li class="nav-item nav-icon-hover-bg rounded-circle">
                <button type="button" class="nav-link dark-layout btn border-0 bg-transparent shadow-none" title="Modo oscuro" aria-label="Cambiar a modo oscuro" style="display: flex;">
                  <i class="ti ti-moon fs-6"></i>
                </button>
                <button type="button" class="nav-link light-layout btn border-0 bg-transparent shadow-none" title="Modo claro" aria-label="Cambiar a modo claro" style="display: none;">
                  <i class="ti ti-sun fs-6"></i>
                </button>
              </li>

              <li class="nav-item dropdown">
                <button type="button" class="nav-link pe-0 btn border-0 bg-transparent shadow-none" id="drop1" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menú de cuenta">
                  <div class="d-flex align-items-center">
                    <div class="user-profile-img">
                      <img src="<?= esc($avatarUrl) ?>" class="rounded-circle object-fit-cover" width="35" height="35" alt="Avatar" />
                    </div>
                  </div>
                </button>
                <div class="dropdown-menu content-dd dropdown-menu-end dropdown-menu-animate-up" aria-labelledby="drop1">
                  <div class="profile-dropdown position-relative" data-simplebar>
                    <div class="py-3 px-7 pb-0">
                      <h5 class="mb-0 fs-5 fw-semibold">Mi Cuenta</h5>
                    </div>
                    <div class="d-flex align-items-center py-9 mx-7 border-bottom">
                      <img src="<?= esc($avatarUrl) ?>" class="rounded-circle object-fit-cover" width="64" height="64" alt="Avatar" />
                      <div class="ms-3">
                        <h5 class="mb-1 fs-3"><?= esc($displayName) ?></h5>
                        <span class="mb-1 d-block text-muted"><?= esc($displayRole) ?></span>
                        <p class="mb-0 d-flex align-items-center gap-2 fs-2 text-muted">
                          <i class="ti ti-mail fs-4"></i> <?= esc($displayEmail) ?>
                        </p>
                      </div>
                    </div>
                    <div class="d-grid gap-2 py-4 px-7 pt-4">
                      <a href="<?= base_url('profile') ?>" class="btn btn-sm btn-primary w-100">Mi Perfil</a>
                      <a href="<?= url_to('logout') ?>" class="btn btn-sm btn-outline-primary w-100">Cerrar Sesión</a>
                    </div>
                  </div>
                </div>
              </li>
            </ul>
          </div>
        </nav>
      </div>
    </header>
    <div class="body-wrapper">
      <div class="container-fluid">
