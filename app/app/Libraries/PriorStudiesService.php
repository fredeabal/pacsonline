<?php

declare(strict_types=1);

namespace App\Libraries;

use App\Models\MedicalReportModel;
use App\Models\StudyModel;

/**
 * Resuelve y enriquece los estudios previos (Priors) de un paciente a partir de su PatientID,
 * respetando el aislamiento por permisos y evitando consultas N+1.
 */
class PriorStudiesService
{
    /**
     * Obtiene los estudios previos del mismo PatientID excluyendo el estudio actual.
     *
     * @return list<array<string, mixed>>
     */
    public function findForStudy(
        OrthancClient $orthanc,
        string $patientId,
        string $currentStudyUid,
        bool $canViewAll,
        bool $canReview,
        int $currentUserId,
        int $limit = 15,
        ?int $scopedCenterId = null
    ): array {
        $rawStudies = $orthanc->findStudiesByPatientId($patientId, $currentStudyUid, $limit);
        if ($rawStudies === []) {
            return [];
        }

        $uids = [];
        foreach ($rawStudies as $item) {
            $uid = trim((string) ($item['MainDicomTags']['StudyInstanceUID'] ?? ''));
            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        if ($uids === []) {
            return [];
        }

        $recordsByUid = model(StudyModel::class)->mapByStudyUids($uids);
        $reportsByUid = model(MedicalReportModel::class)->mapByStudyUids($uids);

        $priors = [];
        foreach ($rawStudies as $raw) {
            $uid = trim((string) ($raw['MainDicomTags']['StudyInstanceUID'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $record = $recordsByUid[$uid] ?? null;

            // Si el usuario está restringido a un centro, solo ve estudios previos de ese centro.
            if ($scopedCenterId !== null && ($record === null || (int) ($record['center_id'] ?? 0) !== $scopedCenterId)) {
                continue;
            }

            // Si el usuario no tiene permiso global (p. ej. rol paciente), solo ve estudios propios.
            if ($scopedCenterId === null && ! $canViewAll && ($record === null || (int) ($record['owner_user_id'] ?? 0) !== $currentUserId)) {
                continue;
            }

            $merged = merge_local_study_metadata($raw, $record);
            $report = $reportsByUid[$uid] ?? null;

            $reportStatus       = $report !== null ? (string) ($report['status'] ?? 'pending') : 'pending';
            $hasPublishedReport = $reportStatus === 'reported';
            $canViewReport      = $report !== null && ($hasPublishedReport || $canReview);

            $accession    = trim((string) ($merged['MainDicomTags']['AccessionNumber'] ?? ''));
            $dicomStudyId = trim((string) ($merged['MainDicomTags']['StudyID'] ?? ''));

            $priors[] = [
                'uid'                  => $uid,
                'orthanc_id'           => (string) ($merged['ID'] ?? ''),
                'study_date'           => (string) ($merged['MainDicomTags']['StudyDate'] ?? ''),
                'modality'             => extract_study_modality($merged),
                'description'          => (string) ($merged['MainDicomTags']['StudyDescription'] ?? 'Sin descripción'),
                'study_number'         => $accession !== '' ? $accession : ($dicomStudyId !== '' ? $dicomStudyId : '-'),
                'series_count'         => is_array($raw['Series'] ?? null) ? count($raw['Series']) : 0,
                'report_status'        => $reportStatus,
                'has_published_report' => $hasPublishedReport,
                'can_view_report'      => $canViewReport,
                'report'               => $canViewReport ? [
                    'status'           => $reportStatus,
                    'clinical_history' => (string) ($report['clinical_history'] ?? ''),
                    'findings'         => (string) ($report['findings'] ?? ''),
                    'impression'       => (string) ($report['impression'] ?? ''),
                    'reported_at'      => (string) ($report['reported_at'] ?? ($report['updated_at'] ?? '')),
                ] : null,
            ];
        }

        return $priors;
    }
}
