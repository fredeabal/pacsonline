<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 24px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
    <h2 style="color: #5d87ff; margin-top: 0;">PACS Online — Informe Disponible</h2>
    <p>Hola <strong><?= esc($patientName) ?></strong>,</p>
    <p>Tu informe médico para el estudio <strong><?= esc($studyDescription) ?></strong> ha sido publicado el <?= esc($reportedAt) ?>.</p>
    <p>Ya puedes acceder al portal para visualizar tus imágenes médicas y descargar tu informe en PDF:</p>
    <p style="margin: 24px 0;">
      <a href="<?= esc($portalUrl, 'attr') ?>" style="background: #5d87ff; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: bold;">
        Acceder a Mis Estudios
      </a>
    </p>
    <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">Mensaje automático de PACS Online.</p>
  </div>
</body>
</html>
