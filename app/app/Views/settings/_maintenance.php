<div class="card mb-4">
  <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
    <h5 class="mb-0 text-primary fw-semibold">Copia de seguridad de base de datos</h5>
    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-2"><?= esc($dbSizeFormatted) ?></span>
  </div>
  <div class="card-body text-center">
    <p class="text-muted mb-3">Descarga una copia en caliente de la base de datos SQLite (usuarios, estudios, enlaces, informes, plantillas y auditoría).</p>
    <a href="<?= base_url('settings/backup-db') ?>" class="btn btn-sm btn-primary">
      <i class="ti ti-download me-1"></i> Descargar Backup SQLite
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header bg-primary-subtle">
    <h5 class="mb-0 text-primary fw-semibold">Backup automático completo (SQLite + Imágenes DICOM)</h5>
  </div>
  <div class="card-body">
    <p class="text-muted mb-2">Para programar respaldos diarios automáticos en tu VM de Proxmox (con rotación cada 14 días y cifrado AES-256 opcional vía <code>BACKUP_PASSPHRASE</code> en <code>.env</code>), añade esta línea al <code>crontab -e</code> de <code>root</code>:</p>
    <pre class="bg-body-tertiary p-3 rounded border mb-0"><code>0 3 * * * /opt/pacs/scripts/backup.sh >> /var/log/pacs-backup.log 2>&1</code></pre>
  </div>
</div>
