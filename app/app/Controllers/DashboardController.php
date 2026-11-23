<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OrthancClient;
use App\Models\MedicalReportModel;
use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;

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
        $user         = auth()->user();
        $isSupervisor = $user?->can('users.manage') ?? false;
        $isMedico     = ($user?->can('reports.review') ?? false) && ! $isSupervisor;
        $isUsuario    = ! $isSupervisor && ! $isMedico && ($user?->can('studies.share') ?? false);
        $roleMode     = $isSupervisor ? 'admin' : ($isMedico ? 'medico' : ($isUsuario ? 'usuario' : 'observador'));

        $orthanc          = new OrthancClient();
        $alive            = $orthanc->isAlive();
        $studyModel       = model(StudyModel::class);
        $reportModel      = model(MedicalReportModel::class);
        $patientLinkModel = model(PatientAccessLinkModel::class);

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
        $totalStudies  = $alive ? ($stats['CountStudies'] ?? $orthanc->countStudies()) : 0;
        $reportedCount = (int) $reportModel->where('status', 'reported')->countAllResults();
        $pendingCount  = max(0, $totalStudies - $reportedCount);
        $urgentCount   = (int) $studyModel->builder()
            ->join('medical_reports', "medical_reports.study_instance_uid = studies.study_instance_uid AND medical_reports.status = 'reported'", 'left')
            ->where('studies.priority', 'urgent')
            ->where('medical_reports.id IS NULL', null, false)
            ->countAllResults();

        $recentRaw = $alive ? $orthanc->findStudiesPage(0, 5) : [];
        $uids      = [];
        foreach ($recentRaw as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        $recordsByUid      = $studyModel->mapByStudyUids($uids);
        $reportsByUid      = $reportModel->mapByStudyUids($uids);
        $patientLinksByUid = $patientLinkModel->countActiveByStudyUids($uids);
        $recentStudies     = [];

        foreach ($recentRaw as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid === '') {
                continue;
            }

            $record = $recordsByUid[$uid] ?? null;
            $report = $reportsByUid[$uid] ?? null;
            $status = $report['status'] ?? 'pending';

            $study                          = merge_local_study_metadata($study, $record);
            $study['_record']               = $record;
            $study['_report']               = $report;
            $study['_active_patient_links'] = $patientLinksByUid[$uid] ?? 0;
            $study['_report_status']        = $status;
            $study['_priority']             = $record['priority'] ?? 'normal';
            $recentStudies[]                = $study;
        }

        return view('dashboard/index', [
            'title'         => 'Dashboard | PACS Online',
            'roleMode'      => $roleMode,
            'orthancAlive'  => $alive,
            'orthancStats'  => $stats,
            'diskStats'     => $diskStats,
            'totalStudies'  => $totalStudies,
            'pendingCount'  => $pendingCount,
            'urgentCount'   => $urgentCount,
            'reportedCount' => $reportedCount,
            'recentStudies' => $recentStudies,
        ]);
    }
}
