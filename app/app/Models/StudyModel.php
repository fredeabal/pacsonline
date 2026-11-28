<?php

declare(strict_types=1);

namespace App\Models;

use App\Libraries\OrthancClient;
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
        'modality',
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

    /**
     * Resuelve la modalidad DICOM para una página de estudios usando primero la columna persistente
     * `studies.modality` (0 peticiones extra) y consultando Orthanc en paralelo solo para los estudios
     * históricos que aún no la tengan guardada, persistiendo el resultado de forma permanente.
     *
     * @param list<array<string, mixed>>          $studies
     * @param array<string, array<string, mixed>> $recordsByUid
     *
     * @return list<array<string, mixed>>
     */
    public function resolveAndPersistModalities(OrthancClient $orthanc, array $studies, array &$recordsByUid): array
    {
        if ($studies === []) {
            return [];
        }

        $missingMap = [];
        $missingPos = [];

        $nonImageModalities = ['SR', 'DOC', 'PR', 'KO', 'AU'];

        foreach ($studies as $idx => $study) {
            $uid = trim((string) ($study['MainDicomTags']['StudyInstanceUID'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $storedModality = strtoupper(trim((string) ($recordsByUid[$uid]['modality'] ?? '')));
            if ($storedModality !== '' && ! in_array($storedModality, $nonImageModalities, true)) {
                $studies[$idx]['modality'] = $storedModality;
                continue;
            }

            $inlineModality = trim((string) ($study['RequestedTags']['ModalitiesInStudy'] ?? ''));
            if ($inlineModality !== '') {
                $resolved = extract_study_modality($study);
                if ($resolved !== '' && $resolved !== 'DICOM' && ! in_array($resolved, $nonImageModalities, true)) {
                    $this->persistStudyModality($uid, $study, $resolved, $recordsByUid);
                    $studies[$idx]['modality'] = $resolved;
                }
                continue;
            }

            $missingPos[] = $idx;
            $missingMap[] = $study;
        }

        if ($missingMap === []) {
            return $studies;
        }

        $enrichedList = $orthanc->enrichStudiesModalities($missingMap);
        foreach ($enrichedList as $posIdx => $enrichedStudy) {
            $origIdx           = $missingPos[$posIdx];
            $studies[$origIdx] = $enrichedStudy;

            $uid = trim((string) ($enrichedStudy['MainDicomTags']['StudyInstanceUID'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $hasTag = trim((string) ($enrichedStudy['RequestedTags']['ModalitiesInStudy'] ?? '')) !== '';
            if (! $hasTag) {
                continue;
            }

            $resolved = extract_study_modality($enrichedStudy);
            if ($resolved !== '' && $resolved !== 'DICOM' && ! in_array($resolved, $nonImageModalities, true)) {
                $this->persistStudyModality($uid, $enrichedStudy, $resolved, $recordsByUid);
                $studies[$origIdx]['modality'] = $resolved;
            }
        }

        return $studies;
    }

    /**
     * Guarda la modalidad resuelta en la fila existente de `studies` o inserta el registro base.
     *
     * @param array<string, mixed>                $orthancStudy
     * @param array<string, array<string, mixed>> $recordsByUid
     */
    private function persistStudyModality(string $uid, array $orthancStudy, string $modality, array &$recordsByUid): void
    {
        try {
            if (isset($recordsByUid[$uid]['id'])) {
                $this->update((int) $recordsByUid[$uid]['id'], ['modality' => $modality]);
                $recordsByUid[$uid]['modality'] = $modality;
                return;
            }

            $payload = [
                'orthanc_id'         => (string) ($orthancStudy['ID'] ?? ''),
                'study_instance_uid' => $uid,
                'priority'           => 'normal',
                'modality'           => $modality,
                'patient_name'       => (string) ($orthancStudy['PatientMainDicomTags']['PatientName'] ?? 'Anónimo'),
                'study_description'  => (string) ($orthancStudy['MainDicomTags']['StudyDescription'] ?? 'Sin descripción'),
                'study_date'         => $orthancStudy['MainDicomTags']['StudyDate'] ?? null,
            ];

            $newId = $this->insert($payload, true);
            if ($newId !== false) {
                $payload['id']      = (int) $newId;
                $recordsByUid[$uid] = $payload;
            }
        } catch (\Throwable) {
            // Si otra petición concurrente insertó el mismo StudyInstanceUID, actualizamos su modalidad.
            $existing = $this->findByStudyUid($uid);
            if ($existing !== null) {
                if (empty($existing['modality'])) {
                    $this->update((int) $existing['id'], ['modality' => $modality]);
                    $existing['modality'] = $modality;
                }
                $recordsByUid[$uid] = $existing;
            }
        }
    }
}
