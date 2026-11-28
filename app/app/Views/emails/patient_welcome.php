<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1e293b; background: #f8fafc; padding: 24px;">
  <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; border: 1px solid #e2e8f0;">
    <h2 style="color: #5d87ff; margin-top: 0;">Bienvenido a PACSLine</h2>
    <p>Hola <strong><?= esc($patientName) ?></strong>,</p>
    <p>Se ha creado tu cuenta en el portal <strong>PACSLine</strong> para que puedas consultar tus estudios médicos, visualizar imágenes DICOM y descargar tus informes.</p>
    <div style="background: #f1f5f9; border-radius: 8px; padding: 14px 18px; margin: 16px 0;">
      <p style="margin: 0 0 6px 0;"><strong>Usuario / Email:</strong> <?= esc($email) ?></p>
      <p style="margin: 0;"><strong>Contraseña temporal:</strong> <code><?= esc($tempPassword) ?></code></p>
    </div>
    <p style="font-size: 13px; color: #475569;">Por seguridad, te recomendamos cambiar esta contraseña temporal desde tu perfil nada más iniciar sesión.</p>
    <p style="margin: 24px 0;">
      <a href="<?= esc($loginUrl, 'attr') ?>" style="background: #5d87ff; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-weight: bold;">
        Acceder al Portal
      </a>
    </p>
    <p style="font-size: 12px; color: #64748b; margin-bottom: 0;">Mensaje automático de PACSLine.</p>
  </div>
</body>
</html>
