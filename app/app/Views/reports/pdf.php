<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    @page { size: A4 portrait; margin: 115px 38px 95px 38px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 11.5px; line-height: 1.5; margin: 0; }
    .letterhead-bg {
      position: fixed;
      top: -115px;
      left: -38px;
      width: 210mm;
      height: 297mm;
      z-index: -1;
    }
    .letterhead-bg img { width: 100%; height: 100%; display: block; }
    .header-table { width: 100%; margin-bottom: 14px; }
    .doc-title { font-size: 14px; font-weight: bold; color: #1e293b; text-transform: uppercase; letter-spacing: 0.4px; }
    .qr-box { text-align: right; vertical-align: middle; }
    .qr-caption { font-size: 9px; color: #64748b; display: block; margin-top: 2px; }
    .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; background: rgba(248, 250, 252, 0.9); }
    .meta-table td { padding: 7px 10px; border: 1px solid #e2e8f0; }
    .section-title { font-size: 12px; font-weight: bold; color: #0284c7; margin-top: 14px; margin-bottom: 6px; text-transform: uppercase; }
    .box { padding: 10px; border: 1px solid #e2e8f0; border-radius: 4px; white-space: pre-line; background: rgba(255, 255, 255, 0.85); }
    .signature { margin-top: 32px; border-top: 1px solid #cbd5e1; padding-top: 10px; width: 260px; page-break-inside: avoid; }
  </style>
</head>
<body>
  <?php if (! empty($letterheadDataUri)): ?>
    <div class="letterhead-bg">
      <img src="<?= $letterheadDataUri ?>" alt="Membrete" />
    </div>
  <?php endif; ?>
  <table class="header-table">
    <tr>
      <td style="vertical-align: bottom;">
        <div class="doc-title">Informe de Diagnóstico por Imagen</div>
      </td>
      <?php if (! empty($qrDataUri)): ?>
      <td class="qr-box" width="95">
        <img src="<?= $qrDataUri ?>" width="74" height="74" alt="QR Estudio" />
        <span class="qr-caption">Ver imágenes DICOM</span>
      </td>
      <?php endif; ?>
    </tr>
  </table>

  <table class="meta-table">
    <tr>
      <td><strong>Paciente:</strong> <?= esc($study['patient_name'] ?? 'No especificado') ?></td>
      <td><strong>Fecha estudio:</strong> <?= esc(format_date($study['study_date'] ?? null)) ?></td>
    </tr>
    <tr>
      <td><strong>Estudio:</strong> <?= esc($study['study_description'] ?? 'Estudio DICOM') ?></td>
      <td><strong>Fecha informe:</strong> <?= esc(format_date($report['reported_at'] ?? date('Y-m-d'))) ?></td>
    </tr>
  </table>

  <?php if (! empty($report['clinical_history'])): ?>
    <div class="section-title">Antecedentes / Motivo Clínico</div>
    <div class="box"><?= esc($report['clinical_history']) ?></div>
  <?php endif; ?>

  <div class="section-title">Hallazgos</div>
  <div class="box"><?= esc($report['findings'] ?? '') ?></div>

  <div class="section-title">Conclusión / Impresión Diagnóstica</div>
  <div class="box"><?= esc($report['impression'] ?? '') ?></div>

  <div class="signature">
    <?php if (! empty($signatureDataUri)): ?>
      <img src="<?= $signatureDataUri ?>" style="max-height: 60px; margin-bottom: 6px; display: block;" alt="Firma" />
    <?php endif; ?>
    <strong>Informado por:</strong><br>
    <?= esc($doctor ? ($doctor->full_name ?: $doctor->username) : 'Equipo Médico PACS Online') ?><br>
    <?php if ($doctor && ! empty($doctor->specialty)): ?>
      <span><?= esc($doctor->specialty) ?></span><br>
    <?php endif; ?>
    <?php if ($doctor && ! empty($doctor->license_number)): ?>
      <span>Nº Colegiado: <?= esc($doctor->license_number) ?></span>
    <?php endif; ?>
  </div>
</body>
</html>
