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
    public string $defaultGroup = 'observador';

    public array $groups = [
        'admin' => [
            'title'       => 'Supervisor',
            'description' => 'Control total: configuración, usuarios, informes médicos, plantillas, edición DICOM, reenvío y eliminación.',
        ],
        'medico' => [
            'title'       => 'Médico',
            'description' => 'Revisa estudios en worklist, emite informes médicos, gestiona plantillas y enlaces de paciente.',
        ],
        'usuario' => [
            'title'       => 'Usuario',
            'description' => 'Visualiza estudios, reenvía a nodos DICOM, crea links de envío y realiza tareas operativas básicas sin eliminar.',
        ],
        'observador' => [
            'title'       => 'Observador',
            'description' => 'Solo consulta: visualiza casos, visor DICOM e informes médicos publicados.',
        ],
    ];

    public array $permissions = [
        'studies.view'        => 'Puede ver estudios',
        'studies.view_all'    => 'Puede ver todos los estudios del sistema',
        'studies.upload'      => 'Puede subir estudios manualmente',
        'studies.assign'      => 'Puede asignar médico, prioridad y editar motivo de consulta',
        'studies.send'        => 'Puede reenviar estudios a nodos DICOM',
        'studies.share'       => 'Puede crear y gestionar links de envío a pacientes',
        'studies.attachments' => 'Puede subir documentos adjuntos a los casos',
        'studies.edit_dicom'  => 'Puede modificar etiquetas DICOM en Orthanc',
        'studies.delete'      => 'Puede eliminar estudios, adjuntos y enlaces revocados',
        'reports.review'      => 'Puede revisar estudios, redactar borradores y gestionar plantillas',
        'reports.sign'        => 'Puede firmar y publicar informes médicos',
        'reports.view_own'    => 'Puede ver y descargar informes médicos',
        'users.manage'        => 'Puede gestionar usuarios y roles',
        'audit.view'          => 'Puede consultar registros de auditoría',
        'settings.manage'     => 'Puede configurar parámetros del sistema',
    ];

    public array $matrix = [
        'admin' => [
            'studies.*',
            'reports.review',
            'reports.view_own',
            'users.*',
            'audit.*',
            'settings.*',
        ],
        'medico' => [
            'studies.view',
            'studies.view_all',
            'studies.share',
            'studies.attachments',
            'reports.review',
            'reports.sign',
            'reports.view_own',
        ],
        'usuario' => [
            'studies.view',
            'studies.view_all',
            'studies.upload',
            'studies.assign',
            'studies.send',
            'studies.share',
            'studies.attachments',
            'reports.view_own',
        ],
        'observador' => [
            'studies.view',
            'studies.view_all',
            'reports.view_own',
        ],
    ];
}
