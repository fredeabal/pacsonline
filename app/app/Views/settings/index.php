<?= view('template/header') ?>

<div class="card border shadow-none position-relative overflow-hidden mb-4">
  <div class="card-body px-4 py-3">
    <div class="row align-items-center">
      <div class="col-12 text-center text-md-start">
        <h4 class="fw-semibold mb-1">Configuración</h4>
        <nav aria-label="breadcrumb" class="d-flex justify-content-center justify-content-md-start">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a class="text-muted text-decoration-none" href="<?= base_url() ?>">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Configuración</li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'pacs' ? 'active' : '' ?>"
            id="tab-pacs-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-pacs"
            type="button"
            role="tab">
      <i class="ti ti-scan me-1"></i> Servidor PACS
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'smtp' ? 'active' : '' ?>"
            id="tab-smtp-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-smtp"
            type="button"
            role="tab">
      <i class="ti ti-mail-fast me-1"></i> Servidor SMTP
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'audit' ? 'active' : '' ?>"
            id="tab-audit-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-audit"
            type="button"
            role="tab">
      <i class="ti ti-history me-1"></i> Auditoría
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $currentTab === 'maintenance' ? 'active' : '' ?>"
            id="tab-maintenance-btn"
            data-bs-toggle="tab"
            data-bs-target="#tab-maintenance"
            type="button"
            role="tab">
      <i class="ti ti-tools me-1"></i> Mantenimiento
    </button>
  </li>
</ul>

<div class="tab-content" id="settingsTabsContent">
  <div class="tab-pane fade <?= $currentTab === 'pacs' ? 'show active' : '' ?>" id="tab-pacs" role="tabpanel">
    <?= view('settings/_pacs', get_defined_vars()) ?>
  </div>
  <div class="tab-pane fade <?= $currentTab === 'smtp' ? 'show active' : '' ?>" id="tab-smtp" role="tabpanel">
    <?= view('settings/_smtp', get_defined_vars()) ?>
  </div>
  <div class="tab-pane fade <?= $currentTab === 'audit' ? 'show active' : '' ?>" id="tab-audit" role="tabpanel">
    <?= view('settings/_audit', get_defined_vars()) ?>
  </div>
  <div class="tab-pane fade <?= $currentTab === 'maintenance' ? 'show active' : '' ?>" id="tab-maintenance" role="tabpanel">
    <?= view('settings/_maintenance', get_defined_vars()) ?>
  </div>
</div>

<?= view('template/footer') ?>
