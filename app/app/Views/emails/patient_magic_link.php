<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 24px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
    <h2 style="color: #5d87ff; margin-top: 0;">PACS Online — Acceso a tu Estudio</h2>
    <p>Hola <strong><?= esc($patientName) ?></strong>,</p>
    <p>Tu estudio médico (<strong><?= esc($studyDescription) ?></strong>) ya está disponible para consulta y descarga de forma segura.</p>
    <p>Para acceder a las imágenes y a tu informe médico, pulsa el siguiente botón e introduce tu <strong>número de identificación</strong> y tu <strong>fecha de nacimiento</strong>:</p>
    <p style="margin: 24px 0; text-align: center;">
      <a href="<?= esc($magicUrl, 'attr') ?>" style="background: #5d87ff; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; display: inline-block;">
        Acceder a Mi Estudio
      </a>
    </p>
    <p style="font-size: 13px; color: #475569;">
      Por motivos de protección de datos, este enlace es personal e intransferible y caducará el <strong><?= esc($expiresAt) ?></strong>. Te recomendamos descargar tus resultados antes de esa fecha.
    </p>
    <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">Si no esperabas este mensaje, por favor ignóralo. Mensaje automático de PACS Online.</p>
  </div>
</body>
</html>
