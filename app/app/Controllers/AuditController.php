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

        $filters = [
            'category' => (string) ($this->request->getGet('category') ?? 'all'),
            'date'     => (string) ($this->request->getGet('date') ?? 'all'),
            'q'        => (string) ($this->request->getGet('q') ?? ''),
        ];
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));

        $auditData = model(AuditLogModel::class)->paginatedWithUsers($filters, 50, $page);

        return view('audit/index', [
            'title'     => 'Auditoría | PACSLine',
            'logs'      => $auditData['logs'],
            'auditData' => $auditData,
            'filters'   => $filters,
        ]);
    }
}
