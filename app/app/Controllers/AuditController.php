<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\RedirectResponse;

/////////////////////////////////////////////////////////////////////////////////
// Consulta de registros de auditoría del sistema (accesos, envíos, cambios).  //
/////////////////////////////////////////////////////////////////////////////////
class AuditController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista los últimos 200 eventos de auditoría registrados en el sistema.   //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('audit.view')) {
            return redirect()->to('/')->with('error', 'Sin permiso.');
        }

        $logs = model(AuditLogModel::class)
            ->orderBy('created_at', 'DESC')
            ->findAll(200);

        return view('audit/index', [
            'title' => 'Auditoría | PACS Online',
            'logs'  => $logs,
        ]);
    }
}
