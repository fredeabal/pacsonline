<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OrthancClient;
use App\Models\MedicalReportModel;
use App\Models\StudyModel;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Panel principal (Dashboard) adaptado según el rol del usuario.              //
/////////////////////////////////////////////////////////////////////////////////
class DashboardController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Renderiza el dashboard con métricas globales de Orthanc según el rol.   //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string
    {
        return view('dashboard/index', array_merge(
            ['title' => 'Dashboard | PACS Online'],
            $this->collectDashboardData()
        ));
    }

    /////////////////////////////////////////////////////////////////////////////
    // Devuelve las métricas del dashboard en JSON para auto-refresco vía fetch.//
    /////////////////////////////////////////////////////////////////////////////
    public function metrics(): ResponseInterface
    {
        $data = $this->collectDashboardData();

        return $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->setJSON([
                'roleMode'      => $data['roleMode'],
                'orthancAlive'  => $data['orthancAlive'],
                'orthancStats'  => $data['orthancStats'],
                'diskStats'     => $data['diskStats'],
                'alerts'        => $data['alerts'],
                'totalStudies'  => $data['totalStudies'],
                'pendingCount'  => $data['pendingCount'],
                'urgentCount'   => $data['urgentCount'],
                'reportedCount' => $data['reportedCount'],
            ]);
    }

    /**
     * Recopila métricas de Orthanc, disco y base de datos según el rol actual.
     *
     * @return array<string, mixed>
     */
    private function collectDashboardData(): array
    {
        $user           = auth()->user();
        $scopedCenterId = user_center_id($user);
        $isSupervisor   = $scopedCenterId === null && ($user?->can('users.manage') ?? false);
        $isMedico       = ($user?->can('reports.review') ?? false) && ! $isSupervisor;
        $isUsuario      = ! $isSupervisor && ! $isMedico && ($user?->can('studies.share') ?? false);
        $roleMode       = $isSupervisor ? 'admin' : ($isMedico ? 'medico' : ($isUsuario ? 'usuario' : 'observador'));

        $scopedCenterName = null;
        if ($scopedCenterId !== null) {
            $centerRow        = model(\App\Models\MedicalCenterModel::class)->find($scopedCenterId);
            $scopedCenterName = is_array($centerRow) ? (string) ($centerRow['name'] ?? '') : null;
        }

        $orthanc     = new OrthancClient();
        $alive       = $orthanc->isAlive();
        $studyModel  = model(StudyModel::class);
        $reportModel = model(MedicalReportModel::class);

        $stats     = $isSupervisor && $alive ? $orthanc->getStatistics() : [];
        $diskStats = null;
        if ($isSupervisor) {
            $storagePath = '/var/www/orthanc-storage';
            if (is_dir($storagePath)) {
                $totalBytes = @disk_total_space($storagePath);
                $freeBytes  = @disk_free_space($storagePath);
                if ($totalBytes !== false && $freeBytes !== false && $totalBytes > 0) {
                    $usedBytes = max(0.0, $totalBytes - $freeBytes);
                    $diskStats = [
                        'total_bytes' => $totalBytes,
                        'free_bytes'  => $freeBytes,
                        'used_bytes'  => $usedBytes,
                        'used_pct'    => round(($usedBytes / $totalBytes) * 100, 1),
                        'free_pct'    => round(($freeBytes / $totalBytes) * 100, 1),
                    ];
                }
            }
        }

        if ($scopedCenterId !== null) {
            $totalStudies  = (int) $studyModel->where('center_id', $scopedCenterId)->countAllResults();
            $reportedCount = (int) $studyModel->builder()
                ->join('medical_reports', "medical_reports.study_instance_uid = studies.study_instance_uid AND medical_reports.status = 'reported'", 'inner')
                ->where('studies.center_id', $scopedCenterId)
                ->countAllResults();
            $pendingCount = max(0, $totalStudies - $reportedCount);
            $urgentCount  = (int) $studyModel->builder()
                ->join('medical_reports', "medical_reports.study_instance_uid = studies.study_instance_uid AND medical_reports.status = 'reported'", 'left')
                ->where('studies.center_id', $scopedCenterId)
                ->where('studies.priority', 'urgent')
                ->where('medical_reports.id IS NULL', null, false)
                ->countAllResults();
        } else {
            $totalStudies  = $alive ? ($stats['CountStudies'] ?? $orthanc->countStudies()) : 0;
            $reportedCount = (int) $reportModel->where('status', 'reported')->countAllResults();
            $pendingCount  = max(0, $totalStudies - $reportedCount);
            $urgentCount   = (int) $studyModel->builder()
                ->join('medical_reports', "medical_reports.study_instance_uid = studies.study_instance_uid AND medical_reports.status = 'reported'", 'left')
                ->where('studies.priority', 'urgent')
                ->where('medical_reports.id IS NULL', null, false)
                ->countAllResults();
        }

        $alerts = [];
        if (! $alive) {
            $alerts[] = [
                'level'   => 'danger',
                'icon'    => 'ti-server-off',
                'title'   => 'Servidor PACS sin conexión',
                'message' => 'No se pudo conectar con Orthanc. Verifica el estado del servicio PACS.',
            ];
        }
        if ($isSupervisor && $diskStats !== null) {
            $warnPct = (float) env('DISK_ALERT_WARN_PCT', 80);
            $critPct = (float) env('DISK_ALERT_CRIT_PCT', 90);
            $usedPct = (float) $diskStats['used_pct'];
            $freeGb  = $diskStats['free_bytes'] / (1024 ** 3);
            $freeFmt = $freeGb >= 1024
                ? number_format($freeGb / 1024, 2, ',', '.') . ' TB'
                : number_format($freeGb, 1, ',', '.') . ' GB';

            if ($usedPct >= $critPct) {
                $alerts[] = [
                    'level'   => 'danger',
                    'icon'    => 'ti-alert-octagon',
                    'title'   => 'Almacenamiento crítico (' . number_format($usedPct, 1, ',', '.') . '%)',
                    'message' => "Quedan solo {$freeFmt} libres en el volumen DICOM. Amplía espacio o libera estudios antiguos.",
                ];
            } elseif ($usedPct >= $warnPct) {
                $alerts[] = [
                    'level'   => 'warning',
                    'icon'    => 'ti-alert-triangle',
                    'title'   => 'Almacenamiento elevado (' . number_format($usedPct, 1, ',', '.') . '%)',
                    'message' => "Quedan {$freeFmt} libres en el volumen DICOM.",
                ];
            }
        }

        return [
            'roleMode'         => $roleMode,
            'scopedCenterName' => $scopedCenterName,
            'orthancAlive'     => $alive,
            'orthancStats'  => $stats,
            'diskStats'     => $diskStats,
            'alerts'        => $alerts,
            'totalStudies'  => $totalStudies,
            'pendingCount'  => $pendingCount,
            'urgentCount'   => $urgentCount,
            'reportedCount' => $reportedCount,
        ];
    }
}
