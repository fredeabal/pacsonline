<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * --------------------------------------------------------------------
 * Auditoría de inicios y cierres de sesión (CodeIgniter Shield)
 * --------------------------------------------------------------------
 */
Events::on('login', static function ($user): void {
    try {
        $userId   = isset($user->id) ? (int) $user->id : null;
        $identity = trim((string) ($user->username ?? $user->email ?? ''));
        $details  = $identity !== '' ? "Inicio de sesión ({$identity})" : 'Inicio de sesión';
        \App\Models\AuditLogModel::record('auth.login', null, null, $details, null, $userId);
    } catch (\Throwable $e) {
        log_message('error', 'Audit login error: ' . $e->getMessage());
    }
});

Events::on('failedLogin', static function (array $credentials): void {
    try {
        $identity = trim((string) ($credentials['username'] ?? $credentials['email'] ?? ''));
        $details  = $identity !== '' ? "Intento fallido ({$identity})" : 'Intento de inicio de sesión fallido';
        \App\Models\AuditLogModel::record('auth.login_failed', null, null, $details);
    } catch (\Throwable $e) {
        log_message('error', 'Audit failedLogin error: ' . $e->getMessage());
    }
});

Events::on('logout', static function ($user): void {
    if ($user === null) {
        return;
    }
    try {
        $userId   = isset($user->id) ? (int) $user->id : null;
        $identity = trim((string) ($user->username ?? $user->email ?? ''));
        $details  = $identity !== '' ? "Cierre de sesión ({$identity})" : 'Cierre de sesión';
        \App\Models\AuditLogModel::record('auth.logout', null, null, $details, null, $userId);
    } catch (\Throwable $e) {
        log_message('error', 'Audit logout error: ' . $e->getMessage());
    }
});
