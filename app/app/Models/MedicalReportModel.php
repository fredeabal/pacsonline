<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de informes médicos asociados a estudios DICOM.
 */
class MedicalReportModel extends Model
{
    protected $table          = 'medical_reports';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'study_instance_uid',
        'doctor_user_id',
        'status',
        'clinical_history',
        'findings',
        'impression',
        'reported_at',
    ];

    public function findByStudyUid(string $studyUid): ?array
    {
        return $this->where('study_instance_uid', $studyUid)->first();
    }

    /**
     * Precarga informes médicos indexados por StudyInstanceUID en una sola consulta.
     *
     * @param list<string> $studyUids
     * @return array<string, array<string, mixed>>
     */
    public function mapByStudyUids(array $studyUids): array
    {
        if ($studyUids === []) {
            return [];
        }

        $rows = $this->whereIn('study_instance_uid', array_values(array_unique($studyUids)))->findAll();
        $map  = [];
        foreach ($rows as $row) {
            $map[(string) $row['study_instance_uid']] = $row;
        }

        return $map;
    }
}
