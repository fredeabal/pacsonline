<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 24px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
    <h2 style="color: #5d87ff; margin-top: 0;">PACS Online — Nuevo Estudio Asignado</h2>
    <p>Hola <strong>Dr./Dra. <?= esc($doctorName) ?></strong>,</p>
    <p>Se te ha asignado un nuevo caso para revisión e informe médico:</p>
    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px 18px; margin: 16px 0;">
      <p style="margin: 0 0 6px 0;"><strong>Paciente:</strong> <?= esc($patientName) ?></p>
      <p style="margin: 0 0 6px 0;"><strong>Estudio:</strong> <?= esc($studyDescription) ?></p>
      <p style="margin: 0;"><strong>Prioridad:</strong>
        <?php if ($isUrgent): ?>
          <span style="color: #dc2626; font-weight: bold;">URGENTE</span>
        <?php else: ?>
          Normal
        <?php endif; ?>
      </p>
    </div>
    <?php if (! empty($clinicalNotes)): ?>
      <p style="font-size: 13px; color: #334155;"><strong>Motivo de consulta:</strong><br><?= nl2br(esc($clinicalNotes)) ?></p>
    <?php endif; ?>
    <p style="margin: 24px 0;">
      <a href="<?= esc($reportUrl, 'attr') ?>" style="background: #5d87ff; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: bold;">
        Abrir en Worklist / Informar
      </a>
    </p>
    <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">Mensaje automático de PACS Online.</p>
  </div>
</body>
</html>
