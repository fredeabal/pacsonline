<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\OrthancClient;
use App\Models\MedicalReportModel;
use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;

/////////////////////////////////////////////////////////////////////////////////
// Panel principal (Dashboard) adaptado según el rol: Admin, Médico o Paciente.//
/////////////////////////////////////////////////////////////////////////////////
class DashboardController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Renderiza el dashboard con métricas globales de Orthanc o propios.      //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string
    {
        $user       = auth()->user();
        $isAdmin    = $user?->can('users.manage') ?? false;
        $isMedico   = ($user?->can('reports.review') ?? false) && ! $isAdmin;
        $userId     = (int) auth()->id();

        $orthanc     = new OrthancClient();
        $alive       = $orthanc->isAlive();
        $studyModel       = model(StudyModel::class);
        $reportModel      = model(MedicalReportModel::class);
        $patientLinkModel = model(PatientAccessLinkModel::class);

        if (! $isAdmin && ! $isMedico) {
            $ownedRecords = $studyModel
                ->where('owner_user_id', $userId)
                ->orderBy('study_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->findAll();

            $ownedUids    = array_values(array_filter(array_column($ownedRecords, 'study_instance_uid')));
            $reportsByUid = $reportModel->mapByStudyUids($ownedUids);

            $reportedCount = 0;
            $pendingCount  = 0;
            $urgentCount   = 0;

            foreach ($ownedRecords as $rec) {
                $uid    = (string) ($rec['study_instance_uid'] ?? '');
                $report = $reportsByUid[$uid] ?? null;
                $status = $report['status'] ?? 'pending';
                if ($status === 'reported') {
                    $reportedCount++;
                } else {
                    $pendingCount++;
                    if (($rec['priority'] ?? 'normal') === 'urgent') {
                        $urgentCount++;
                    }
                }
            }

            $recentStudies = [];
            foreach (array_slice($ownedRecords, 0, 5) as $rec) {
                $uid    = (string) ($rec['study_instance_uid'] ?? '');
                $study  = ($alive && $uid !== '') ? ($orthanc->findStudyByUid($uid) ?? []) : [];
                $report = $reportsByUid[$uid] ?? null;
                $status = $report['status'] ?? 'pending';

                if (! isset($study['MainDicomTags'])) {
                    $study['MainDicomTags'] = [
                        'StudyInstanceUID' => $uid,
                        'StudyDescription' => $rec['study_description'] ?? '',
                        'StudyDate'        => $rec['study_date'] ?? '',
                    ];
                }
                if (! isset($study['PatientMainDicomTags'])) {
                    $study['PatientMainDicomTags'] = [
                        'PatientName' => $rec['patient_name'] ?? 'Anónimo',
                    ];
                }

                $study                   = merge_local_study_metadata($study, $rec);
                $study['_record']        = $rec;
                $study['_report']        = $report;
                $study['_report_status'] = $status;
                $study['_priority']      = $rec['priority'] ?? 'normal';
                $recentStudies[]         = $study;
            }

            return view('dashboard/index', [
                'title'         => 'Dashboard | PACS Online',
                'roleMode'      => 'paciente',
                'orthancAlive'  => $alive,
                'totalStudies'  => count($ownedRecords),
                'pendingCount'  => $pendingCount,
                'urgentCount'   => $urgentCount,
                'reportedCount' => $reportedCount,
                'recentStudies' => $recentStudies,
            ]);
        }

        $stats = $isAdmin && $alive ? $orthanc->getStatistics() : [];
        $diskStats = null;
        if ($isAdmin) {
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
            $study['_priority']      = $record['priority'] ?? 'normal';
            $recentStudies[]         = $study;
        }

        return view('dashboard/index', [
            'title'         => 'Dashboard | PACS Online',
            'roleMode'      => $isAdmin ? 'admin' : 'medico',
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
