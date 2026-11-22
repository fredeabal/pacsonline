<?php

declare(strict_types=1);

// Mensajes de autenticación (override de CodeIgniter Shield en español corto y conciso)
return [
    // Excepciones
    'unknownAuthenticator'  => 'Autenticador inválido.',
    'unknownUserProvider'   => 'Proveedor de usuarios no disponible.',
    'invalidUser'           => 'Usuario no encontrado.',
    'bannedUser'            => 'Cuenta suspendida.',
    'logOutBannedUser'      => 'Sesión cerrada: cuenta suspendida.',
    'badAttempt'            => 'Credenciales incorrectas.',
    'noPassword'            => 'El usuario no tiene contraseña.',
    'invalidPassword'       => 'Contraseña incorrecta.',
    'noToken'               => 'Falta el token en la cabecera {0}.',
    'badToken'              => 'Token de acceso inválido.',
    'oldToken'              => 'Token de acceso caducado.',
    'noUserEntity'          => 'Usuario no proporcionado.',
    'invalidEmail'          => 'No se encontró una cuenta con el correo "{0}".',
    'unableSendEmailToUser' => 'No se pudo enviar el correo a "{0}".',
    'throttled'             => 'Demasiados intentos. Espera {0} s.',
    'notEnoughPrivilege'    => 'Sin permisos suficientes.',

    // JWT
    'invalidJWT'     => 'Token inválido.',
    'expiredJWT'     => 'Token caducado.',
    'beforeValidJWT' => 'Token aún no disponible.',

    // Campos
    'email'           => 'Correo electrónico',
    'username'        => 'Usuario',
    'password'        => 'Contraseña',
    'passwordConfirm' => 'Confirmar contraseña',
    'haveAccount'     => '¿Ya tienes cuenta?',
    'token'           => 'Token',

    // Botones
    'confirm' => 'Confirmar',
    'send'    => 'Enviar',

    // Registro
    'register'         => 'Registrarse',
    'registerDisabled' => 'El registro no está habilitado.',
    'registerSuccess'  => '¡Registro completado!',

    // Login / Magic Link
    'login'              => 'Iniciar sesión',
    'needAccount'        => '¿Necesitas una cuenta?',
    'rememberMe'         => 'Recordarme',
    'forgotPassword'     => '¿Olvidaste tu contraseña?',
    'useMagicLink'       => 'Usar enlace de acceso',
    'magicLinkSubject'   => 'Tu enlace de acceso',
    'magicTokenNotFound' => 'Enlace inválido.',
    'magicLinkExpired'   => 'El enlace ha caducado.',
    'checkYourEmail'     => '¡Revisa tu correo!',
    'magicLinkDetails'   => 'Enviamos un enlace de acceso a tu correo. Válido por {0} minutos.',
    'magicLinkDisabled'  => 'El acceso por enlace mágico está deshabilitado.',
    'successLogout'      => 'Sesión cerrada.',
    'backToLogin'        => 'Volver al inicio de sesión',

    // Contraseñas
    'errorPasswordLength'       => 'Mínimo {0, number} caracteres.',
    'suggestPasswordLength'     => 'Usa al menos 8 caracteres.',
    'errorPasswordCommon'       => 'La contraseña es muy común.',
    'suggestPasswordCommon'     => 'Elige una contraseña menos común.',
    'errorPasswordPersonal'     => 'No incluyas datos personales en la contraseña.',
    'suggestPasswordPersonal'   => 'Evita usar tu nombre o correo.',
    'errorPasswordTooSimilar'   => 'La contraseña es muy similar al usuario.',
    'suggestPasswordTooSimilar' => 'No uses tu nombre de usuario en la contraseña.',
    'errorPasswordPwned'        => 'Esta contraseña ha sido filtrada y no es segura.',
    'suggestPasswordPwned'      => 'Elige una contraseña distinta.',
    'errorPasswordEmpty'        => 'La contraseña es obligatoria.',
    'errorPasswordTooLongBytes' => 'La contraseña supera el máximo de {param} bytes.',
    'passwordChangeSuccess'     => 'Contraseña actualizada.',
    'userDoesNotExist'          => 'Usuario no encontrado.',
    'resetTokenExpired'         => 'El enlace de recuperación ha caducado.',

    // Email Globals
    'emailInfo'      => 'Detalles de la solicitud:',
    'emailIpAddress' => 'Dirección IP:',
    'emailDevice'    => 'Dispositivo:',
    'emailDate'      => 'Fecha:',

    // 2FA
    'email2FATitle'       => 'Verificación en dos pasos',
    'confirmEmailAddress' => 'Confirma tu correo electrónico.',
    'emailEnterCode'      => 'Confirma tu correo',
    'emailConfirmCode'    => 'Ingresa el código de 6 dígitos enviado a tu correo.',
    'email2FASubject'     => 'Tu código de verificación',
    'email2FAMailBody'    => 'Tu código de verificación es:',
    'invalid2FAToken'     => 'Código incorrecto.',
    'need2FA'             => 'Completa la verificación en dos pasos.',
    'needVerification'    => 'Verifica tu correo para activar tu cuenta.',

    // Activación
    'emailActivateTitle'    => 'Activación de cuenta',
    'emailActivateBody'     => 'Enviamos un código de confirmación a tu correo. Ingrésalo a continuación.',
    'emailActivateSubject'  => 'Tu código de activación',
    'emailActivateMailBody' => 'Usa este código para activar tu cuenta:',
    'invalidActivateToken'  => 'Código incorrecto.',
    'needActivate'          => 'Confirma el código enviado a tu correo.',
    'activationBlocked'     => 'Debes activar tu cuenta antes de entrar.',

    // Grupos y permisos
    'unknownGroup'      => 'Grupo inválido: {0}.',
    'missingTitle'      => 'El grupo requiere un título.',
    'unknownPermission' => 'Permiso inválido: {0}.',
];
