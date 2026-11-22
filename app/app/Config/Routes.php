<?php

use App\Controllers\Api\IngestController;
use App\Controllers\AuditController;
use App\Controllers\CenterController;
use App\Controllers\DashboardController;
use App\Controllers\DicomAuthController;
use App\Controllers\HealthController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\ShareController;
use App\Controllers\StudyController;
use App\Controllers\TemplateController;
use App\Controllers\UserController;
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->setAutoRoute(false);

service('auth')->routes($routes);

// Healthcheck para monitoreo / reverse proxy
$routes->get('health', [HealthController::class, 'index']);

// Endpoint de ingesta DICOM por HTTPS para gateways de centros médicos (Bearer Token)
$routes->post('api/ingest/dicom', [IngestController::class, 'dicom']);

// Endpoint interno para Nginx auth_request (/dicom-web/)
$routes->get('_dicom_auth', [DicomAuthController::class, 'check']);

// Acceso público para médicos invitados mediante enlace compartido
$routes->get('share/(:segment)', [ShareController::class, 'publicView']);
$routes->post('share/(:segment)/verify', [ShareController::class, 'verifyPin']);

$routes->group('', ['filter' => 'session'], static function ($routes) {
    $routes->get('/', [DashboardController::class, 'index']);

    // Estudios
    $routes->get('studies', [StudyController::class, 'index']);
    $routes->get('studies/upload', [StudyController::class, 'uploadForm']);
    $routes->post('studies/upload', [StudyController::class, 'upload']);
    $routes->post('studies/assign', [StudyController::class, 'assignPatient']);
    $routes->get('studies/case/(:segment)', [StudyController::class, 'caseView']);
    $routes->post('studies/case/(:segment)', [StudyController::class, 'updateCase']);
    $routes->get('studies/case/(:segment)/dicom', [StudyController::class, 'editDicom']);
    $routes->post('studies/case/(:segment)/patient-dicom', [StudyController::class, 'updateDicomPatient']);
    $routes->post('studies/case/(:segment)/notes', [StudyController::class, 'updateNotes']);
    $routes->post('studies/attachment/(:segment)', [StudyController::class, 'uploadAttachment']);
    $routes->post('studies/attachment/(:num)/delete', [StudyController::class, 'deleteAttachment']);
    $routes->get('studies/attachment/(:num)', [StudyController::class, 'downloadAttachment']);
    $routes->get('studies/dicom-doc/(:segment)/(:segment)', [StudyController::class, 'downloadDicomDocument']);
    $routes->get('studies/download/(:segment)', [StudyController::class, 'downloadZip']);
    $routes->post('studies/send/(:segment)', [StudyController::class, 'sendToNode']);
    $routes->post('studies/delete/(:segment)', [StudyController::class, 'delete']);

    // Compartición con PIN
    $routes->get('studies/share/(:segment)/create', [ShareController::class, 'createForm']);
    $routes->get('studies/share/(:segment)', [ShareController::class, 'manage']);
    $routes->post('studies/share', [ShareController::class, 'create']);
    $routes->post('studies/share/(:num)/revoke', [ShareController::class, 'revoke']);

    // Informes médicos (Worklist + Editor + PDF)
    $routes->get('reports', [ReportController::class, 'index']);
    $routes->get('reports/edit/(:segment)', [ReportController::class, 'edit']);
    $routes->post('reports/save/(:segment)', [ReportController::class, 'save']);
    $routes->post('reports/delete/(:segment)', [ReportController::class, 'delete']);
    $routes->get('reports/pdf/(:segment)', [ReportController::class, 'pdf']);

    // Plantillas personales por médico
    $routes->get('templates', [TemplateController::class, 'index']);
    $routes->get('templates/create', [TemplateController::class, 'create']);
    $routes->post('templates', [TemplateController::class, 'store']);
    $routes->get('templates/edit/(:num)', [TemplateController::class, 'edit']);
    $routes->post('templates/update/(:num)', [TemplateController::class, 'update']);
    $routes->post('templates/delete/(:num)', [TemplateController::class, 'delete']);

    // Perfil de usuario y firma médica
    $routes->get('profile', [ProfileController::class, 'index']);
    $routes->post('profile', [ProfileController::class, 'update']);
    $routes->post('profile/password', [ProfileController::class, 'updatePassword']);
    $routes->get('profile/signature', [ProfileController::class, 'signature']);

    // Administración
    $routes->get('users', [UserController::class, 'index']);
    $routes->get('users/create', [UserController::class, 'create']);
    $routes->post('users', [UserController::class, 'store']);
    $routes->get('users/edit/(:num)', [UserController::class, 'edit']);
    $routes->post('users/update/(:num)', [UserController::class, 'update']);
    $routes->post('users/delete/(:num)', [UserController::class, 'delete']);
    $routes->get('centers', [CenterController::class, 'index']);
    $routes->get('centers/create', [CenterController::class, 'create']);
    $routes->post('centers', [CenterController::class, 'store']);
    $routes->get('centers/edit/(:num)', [CenterController::class, 'edit']);
    $routes->post('centers/update/(:num)', [CenterController::class, 'update']);
    $routes->post('centers/test-connection/(:num)', [CenterController::class, 'testConnection']);
    $routes->post('centers/rotate-token/(:num)', [CenterController::class, 'rotateToken']);
    $routes->post('centers/delete/(:num)', [CenterController::class, 'delete']);
    $routes->get('audit', [AuditController::class, 'index']);
    $routes->get('settings', [SettingController::class, 'index']);
    $routes->post('settings/smtp', [SettingController::class, 'saveSmtp']);
    $routes->post('settings/test-email', [SettingController::class, 'testEmail']);
    $routes->get('settings/backup-db', [SettingController::class, 'downloadBackup']);
});
