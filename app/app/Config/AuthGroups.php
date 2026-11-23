<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * The group that a newly registered user is added to.
     */
    public string $defaultGroup = 'paciente';

    public array $groups = [
        'admin' => [
            'title'       => 'Administrador',
            'description' => 'Configuración, usuarios, auditoría y subida/asignación manual de estudios.',
        ],
        'medico' => [
            'title'       => 'Médico Informante',
            'description' => 'Revisa estudios en worklist y emite informes médicos.',
        ],
        'paciente' => [
            'title'       => 'Paciente',
            'description' => 'Visualiza únicamente sus propios estudios, informes médicos y comparte sus casos.',
        ],
    ];

    public array $permissions = [
        'studies.view'     => 'Puede ver estudios propios',
        'studies.view_all' => 'Puede ver todos los estudios del sistema',
        'studies.upload'   => 'Puede subir estudios manualmente',
        'reports.review'   => 'Puede revisar estudios y emitir informes médicos',
        'reports.view_own' => 'Puede ver y descargar sus propios informes médicos',
        'users.manage'     => 'Puede gestionar usuarios y roles',
        'audit.view'       => 'Puede consultar registros de auditoría',
        'settings.manage'  => 'Puede configurar parámetros del sistema',
    ];

    public array $matrix = [
        'admin' => [
            'studies.*',
            'reports.view_own',
            'users.*',
            'audit.*',
            'settings.*',
        ],
        'medico' => [
            'studies.view',
            'studies.view_all',
            'reports.review',
        ],
        'paciente' => [
            'studies.view',
            'reports.view_own',
        ],
    ];
}
