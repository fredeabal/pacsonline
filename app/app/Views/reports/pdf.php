<?php
  $patientName = format_patient_name($study['patient_name'] ?? null, 'No especificado');
  $studyDesc   = format_study_description($study['study_description'] ?? null, 'Estudio DICOM');
  $studyDate   = format_date($study['study_date'] ?? null);
  $reportDate  = format_date($report['reported_at'] ?? ($report['updated_at'] ?? date('Y-m-d')));
  $studyNumber = trim((string) ($study['study_number'] ?? ''));
  $referring   = trim((string) ($study['referring_physician'] ?? ''));
  if ($referring !== '') {
      $referring = format_patient_name($referring, $referring);
  } elseif (! empty($study['center_name'])) {
      $referring = trim((string) $study['center_name']);
  } else {
      $referring = 'PARTICULAR';
  }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    @page { size: A4 portrait; margin: 118px 48px 66px 48px; }
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 11.5px; line-height: 1.42; margin: 0; }
    .header-bar {
      position: fixed;
      top: -88px;
      left: 0;
      width: 100%;
      border-bottom: 1.5px solid #5d87ff;
      padding-bottom: 8px;
    }
    .header-bar-table { width: 100%; border-collapse: collapse; }
    .header-bar-table td { vertical-align: bottom; padding: 0; }
    .header-logo-img { width: 190px; height: auto; display: block; }
    .header-doc-label {
      text-align: right;
      font-size: 9px;
      font-weight: bold;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      color: #64748b;
    }
    .footer-bar {
      position: fixed;
      bottom: -42px;
      left: 0;
      width: 100%;
      border-top: 1px solid #e2e8f0;
      height: 16px;
    }
    .meta-card {
      width: 100%;
      background: #f8fafc;
      border-left: 3px solid #5d87ff;
      border-top: 1px solid #e2e8f0;
      border-right: 1px solid #e2e8f0;
      border-bottom: 1px solid #e2e8f0;
      border-radius: 3px;
      padding: 9px 12px;
      margin-bottom: 16px;
    }
    .meta-wrap { width: 100%; border-collapse: collapse; }
    .meta-wrap td { vertical-align: top; padding: 0; }
    .meta-table { width: 100%; border-collapse: collapse; }
    .meta-table td { padding: 1px 6px 1px 0; border: none; vertical-align: top; font-size: 11.5px; line-height: 1.18; }
    .meta-label { width: 88px; font-weight: bold; color: #475569; text-transform: uppercase; white-space: nowrap; }
    .meta-value { font-weight: bold; text-transform: uppercase; color: #0f172a; }
    .qr-box { width: 84px; text-align: right; vertical-align: middle; padding-left: 8px; }
    .qr-caption { font-size: 8px; color: #64748b; display: block; margin-top: 2px; text-align: center; }
    .clinical-history {
      font-family: DejaVu Serif, serif;
      font-size: 13.5px;
      line-height: 1.28;
      font-style: italic;
      color: #1e293b;
      background: #f8fafc;
      border-left: 2px solid #cbd5e1;
      padding: 7px 11px;
      margin: 0 0 12px 0;
      text-align: justify;
    }
    .clinical-history p { margin: 0 0 3px 0; }
    .clinical-history p:last-child { margin-bottom: 0; }
    .section-title {
      font-size: 13px;
      font-weight: bold;
      color: #1e3a8a;
      margin-top: 14px;
      margin-bottom: 6px;
      padding-bottom: 3px;
      border-bottom: 1px solid #e2e8f0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .report-content {
      font-family: DejaVu Serif, serif;
      font-size: 14px;
      line-height: 1.28;
      margin-bottom: 10px;
      text-align: justify;
      color: #0f172a;
    }
    .report-content p { margin: 0 0 3px 0; }
    .report-content p:last-child { margin-bottom: 0; }
    .report-content ul, .report-content ol { margin: 3px 0 4px 20px; padding: 0; }
    .report-content li { margin-bottom: 2px; }
    .report-content h1, .report-content h2, .report-content h3, .report-content h4 { margin: 5px 0 3px 0; font-size: 14px; color: #0f172a; font-family: DejaVu Sans, sans-serif; }
    .report-content .ql-align-center { text-align: center; }
    .report-content .ql-align-right { text-align: right; }
    .report-content .ql-align-justify { text-align: justify; }
    .impression-box {
      background: #f8fafc;
      border-left: 3px solid #5d87ff;
      padding: 9px 12px;
      margin-top: 4px;
      font-weight: bold;
      color: #0f172a;
    }
    .signature {
      margin-top: 30px;
      width: 260px;
      page-break-inside: avoid;
      font-size: 10px;
      line-height: 1.4;
    }
    .signature-thanks { color: #64748b; font-style: italic; margin-bottom: 10px; }
    .signature-line { border-top: 1px solid #cbd5e1; padding-top: 6px; }
    .signature-name { font-weight: bold; text-transform: uppercase; color: #0f172a; font-size: 10.5px; }
    .signature-meta { text-transform: uppercase; font-size: 9px; color: #475569; }
    .draft-watermark {
      position: fixed;
      top: 36%;
      left: -10%;
      width: 120%;
      text-align: center;
      font-size: 92px;
      font-weight: bold;
      color: rgba(220, 38, 38, 0.20);
      letter-spacing: 8px;
      text-transform: uppercase;
      transform: rotate(-35deg);
      z-index: 1000;
    }
  </style>
</head>
<body>
  <?php if (($report['status'] ?? '') !== 'reported'): ?>
    <div class="draft-watermark">BORRADOR</div>
  <?php endif; ?>

  <div class="header-bar">
    <table class="header-bar-table">
      <tr>
        <td>
          <?php if (! empty($pacslineLogoDataUri)): ?>
            <img class="header-logo-img" src="<?= $pacslineLogoDataUri ?>" alt="pacsline" />
          <?php endif; ?>
        </td>
        <td class="header-doc-label">Informe de Diagnóstico por Imagen</td>
      </tr>
    </table>
  </div>

  <div class="footer-bar"></div>

  <div class="meta-card">
    <table class="meta-wrap">
      <tr>
        <td>
          <table class="meta-table">
            <tr>
              <td class="meta-label">NOMBRE:</td>
              <td class="meta-value"><?= esc($patientName) ?></td>
            </tr>
            <tr>
              <td class="meta-label">ESTUDIO:</td>
              <td class="meta-value"><?= esc($studyDesc) ?></td>
            </tr>
            <tr>
              <td class="meta-label">REFERIDO:</td>
              <td class="meta-value"><?= esc($referring) ?></td>
            </tr>
            <?php if ($studyNumber !== '' && $studyNumber !== '-'): ?>
            <tr>
              <td class="meta-label">N°:</td>
              <td class="meta-value"><?= esc($studyNumber) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
              <td class="meta-label">FECHA:</td>
              <td class="meta-value"><?= esc($studyDate !== '-' ? $studyDate : $reportDate) ?></td>
            </tr>
          </table>
        </td>
        <?php if (! empty($qrDataUri)): ?>
        <td class="qr-box">
          <img src="<?= $qrDataUri ?>" width="68" height="68" alt="QR Estudio" />
          <span class="qr-caption">Ver imágenes DICOM</span>
        </td>
        <?php endif; ?>
      </tr>
    </table>
  </div>

  <?php if (report_plain_text($report['clinical_history'] ?? '') !== ''): ?>
    <div class="clinical-history"><?= render_report_html($report['clinical_history']) ?></div>
  <?php endif; ?>

  <div class="report-content"><?= render_report_html($report['findings'] ?? '') ?></div>

  <?php if (report_plain_text($report['impression'] ?? '') !== ''): ?>
    <div class="report-content impression-box"><?= render_report_html($report['impression'] ?? '') ?></div>
  <?php endif; ?>

  <div class="signature">
    <div class="signature-thanks">Gracias por su referencia:</div>
    <?php if (! empty($signatureDataUri)): ?>
      <img src="<?= $signatureDataUri ?>" style="max-height: 58px; margin-bottom: 6px; display: block;" alt="Firma" />
    <?php endif; ?>
    <div class="signature-line">
      <?php if (($report['status'] ?? '') !== 'reported'): ?>
        <?php if (! empty($assignedDoctor)): ?>
          <div class="signature-name"><?= esc($assignedDoctor->full_name ?: $assignedDoctor->username) ?></div>
          <div class="signature-meta">Pendiente de firma médica</div>
        <?php else: ?>
          <div class="signature-name">Pendiente de firma médica</div>
        <?php endif; ?>
        <?php if (! empty($transcriber)): ?>
          <div class="signature-meta">Transcrito por: <?= esc($transcriber->full_name ?: $transcriber->username) ?></div>
        <?php endif; ?>
      <?php else: ?>
        <div class="signature-name"><?= esc($doctor ? ($doctor->full_name ?: $doctor->username) : 'Equipo Médico PACS Online') ?></div>
        <?php if ($doctor && ! empty($doctor->specialty)): ?>
          <div class="signature-meta"><?= esc($doctor->specialty) ?></div>
        <?php endif; ?>
        <?php if ($doctor && ! empty($doctor->license_number)): ?>
          <div class="signature-meta">Nº Colegiado: <?= esc($doctor->license_number) ?></div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="signature-meta">Fecha: <?= esc($reportDate) ?></div>
    </div>
  </div>
</body>
</html>
