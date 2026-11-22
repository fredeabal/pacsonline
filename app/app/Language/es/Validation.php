<?php

declare(strict_types=1);

// Mensajes de validación en español (cortos y concisos)
return [
    // Mensajes del sistema
    'noRuleSets'      => 'No hay reglas de validación configuradas.',
    'ruleNotFound'    => '"{0}" no es una regla válida.',
    'groupNotFound'   => '"{0}" no es un grupo de reglas válido.',
    'groupNotArray'   => 'El grupo "{0}" debe ser un arreglo.',
    'invalidTemplate' => '"{0}" no es una plantilla de validación válida.',

    // Mensajes de reglas
    'alpha'                 => '{field} solo admite letras.',
    'alpha_dash'            => '{field} solo admite letras, números, - y _.',
    'alpha_numeric'         => '{field} solo admite letras y números.',
    'alpha_numeric_punct'   => '{field} contiene caracteres no permitidos.',
    'alpha_numeric_space'   => '{field} solo admite letras, números y espacios.',
    'alpha_space'           => '{field} solo admite letras y espacios.',
    'decimal'               => '{field} debe ser un número decimal.',
    'differs'               => '{field} debe ser distinto de {param}.',
    'equals'                => '{field} debe ser exactamente: {param}.',
    'exact_length'          => '{field} debe tener {param} caracteres.',
    'field_exists'          => '{field} debe existir.',
    'greater_than'          => '{field} debe ser mayor que {param}.',
    'greater_than_equal_to' => '{field} debe ser mayor o igual que {param}.',
    'hex'                   => '{field} debe ser hexadecimal.',
    'in_list'               => 'Valor inválido en {field}.',
    'integer'               => '{field} debe ser un número entero.',
    'is_natural'            => '{field} solo admite dígitos.',
    'is_natural_no_zero'    => '{field} debe ser mayor que cero.',
    'is_not_unique'         => '{field} no existe.',
    'is_unique'             => '{field} ya está registrado.',
    'less_than'             => '{field} debe ser menor que {param}.',
    'less_than_equal_to'    => '{field} debe ser menor o igual que {param}.',
    'matches'               => '{field} no coincide con {param}.',
    'max_byte'              => '{field} supera el tamaño máximo.',
    'max_length'            => '{field}: máximo {param} caracteres.',
    'min_length'            => '{field}: mínimo {param} caracteres.',
    'not_equals'            => '{field} no puede ser {param}.',
    'not_in_list'           => 'Valor no permitido en {field}.',
    'numeric'               => '{field} debe ser numérico.',
    'regex_match'           => 'Formato inválido en {field}.',
    'required'              => 'El campo {field} es obligatorio.',
    'required_with'         => 'El campo {field} es obligatorio.',
    'required_without'      => 'El campo {field} es obligatorio.',
    'string'                => '{field} debe ser texto válido.',
    'timezone'              => '{field} debe ser una zona horaria válida.',
    'valid_base64'          => '{field} debe ser base64 válido.',
    'valid_email'           => 'Ingresa un correo válido en {field}.',
    'valid_emails'          => 'Todos los correos en {field} deben ser válidos.',
    'valid_ip'              => '{field} debe ser una IP válida.',
    'valid_url'             => '{field} debe ser una URL válida.',
    'valid_url_strict'      => '{field} debe ser una URL válida.',
    'valid_date'            => '{field} debe ser una fecha válida.',
    'valid_json'            => '{field} debe ser JSON válido.',

    // Tarjetas de crédito
    'valid_cc_number' => '{field} no es una tarjeta válida.',

    // Archivos
    'uploaded' => 'Debes seleccionar un archivo válido en {field}.',
    'max_size' => '{field} supera el tamaño máximo permitido.',
    'is_image' => '{field} debe ser una imagen válida.',
    'mime_in'  => '{field}: formato de archivo no permitido.',
    'ext_in'   => '{field}: extensión no permitida.',
    'max_dims' => '{field} supera las dimensiones permitidas.',
    'min_dims' => '{field} es demasiado pequeña.',
];
