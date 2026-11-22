<?php /** Pestaña de configuración y estado del servidor PACS Orthanc (AET, puerto DICOM y base de datos). */ ?>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-3 <?= $orthancAlive ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> p-3">
          <i class="ti ti-server fs-7"></i>
        </span>
        <div>
          <span class="text-muted fs-2 d-block">Servidor PACS</span>
          <h5 class="fw-bold mb-0 <?= $orthancAlive ? 'text-success' : 'text-danger' ?>">
            <?= $orthancAlive ? 'Conectado' : 'Sin conexión' ?>
          </h5>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-3 bg-primary-subtle text-primary p-3">
          <i class="ti ti-scan fs-7"></i>
        </span>
        <div>
          <span class="text-muted fs-2 d-block">AE Title activo</span>
          <h5 class="fw-bold mb-0 text-primary"><?= esc($pacsAet) ?></h5>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-3 bg-primary-subtle text-primary p-3">
          <i class="ti ti-plug fs-7"></i>
        </span>
        <div>
          <span class="text-muted fs-2 d-block">Puerto DICOM</span>
          <h5 class="fw-bold mb-0 text-primary"><?= (int) $pacsPort ?></h5>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <span class="rounded-3 bg-primary-subtle text-primary p-3">
          <i class="ti ti-database fs-7"></i>
        </span>
        <div>
          <span class="text-muted fs-2 d-block">Base de datos</span>
          <h5 class="fw-bold mb-0 text-body">SQLite3</h5>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Parámetros de conexión DICOM</h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label for="aet" class="form-label fw-semibold">AE Title del PACS</label>
        <input type="text" class="form-control text-uppercase bg-light" id="aet" value="<?= esc($pacsAet) ?>" readonly>
        <div class="form-text">Definido por <code>ORTHANC_AET</code> en la configuración del servidor.</div>
      </div>

      <div class="col-md-6">
        <label for="dicom_port" class="form-label fw-semibold">Puerto DICOM</label>
        <input type="number" class="form-control bg-light" id="dicom_port" value="<?= (int) $pacsPort ?>" readonly>
        <div class="form-text">Definido por <code>ORTHANC_DICOM_PORT</code> en la configuración del servidor.</div>
      </div>
    </div>
  </div>
</div>
