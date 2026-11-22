<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo local de metadatos clínicos, propiedad, asignación y prioridad de estudios DICOM.
 */
class StudyModel extends Model
{
    protected $table          = 'studies';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'orthanc_id',
        'study_instance_uid',
        'owner_user_id',
        'assigned_doctor_id',
        'center_id',
        'priority',
        'patient_name',
        'study_description',
        'study_date',
        'clinical_notes',
    ];

    public function findByStudyUid(string $studyUid): ?array
    {
        return $this->where('study_instance_uid', $studyUid)->first();
    }

    /**
     * Precarga registros locales indexados por StudyInstanceUID en una sola consulta.
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
