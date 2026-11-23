<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de enlaces mágicos (Magic Links) con verificación de identidad para pacientes.
 */
class PatientAccessLinkModel extends Model
{
    public const MAX_FAILED_ATTEMPTS = 5;

    protected $table         = 'patient_access_links';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'study_instance_uid',
        'created_by_user_id',
        'token',
        'patient_id_hash',
        'patient_birth_date_hash',
        'patient_email',
        'allow_download',
        'views_count',
        'failed_attempts',
        'locked_at',
        'last_accessed_at',
        'expires_at',
        'revoked_at',
    ];

    public function findActiveByToken(string $token): ?array
    {
        $link = $this->where('token', $token)
            ->where('revoked_at', null)
            ->where('locked_at', null)
            ->first();

        if ($link === null) {
            return null;
        }

        if (! empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time()) {
            return null;
        }

        return $link;
    }

    public function isStillActive(int $linkId): bool
    {
        $link = $this->find($linkId);
        if ($link === null || ! empty($link['revoked_at']) || ! empty($link['locked_at'])) {
            return false;
        }

        if (! empty($link['expires_at']) && strtotime((string) $link['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Cuenta enlaces activos (no revocados, no bloqueados, no expirados) por StudyInstanceUID.
     *
     * @param list<string> $studyUids
     * @return array<string, int>
     */
    public function countActiveByStudyUids(array $studyUids): array
    {
        if ($studyUids === []) {
            return [];
        }

        $rows = $this->select('study_instance_uid, COUNT(*) as total')
            ->whereIn('study_instance_uid', array_values(array_unique($studyUids)))
            ->where('revoked_at', null)
            ->where('locked_at', null)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->groupBy('study_instance_uid')
            ->findAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['study_instance_uid']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Normaliza un número de documento (DNI/NIE/Cédula/Pasaporte): solo alfanumérico en mayúsculas.
     */
    public static function normalizePatientId(string $raw): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', trim($raw));
        return strtoupper((string) $clean);
    }

    /**
     * Normaliza una fecha de nacimiento (DICOM YYYYMMDD, ISO YYYY-MM-DD o DD/MM/YYYY) a YYYY-MM-DD.
     */
    public static function normalizeBirthDate(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $year  = 0;
        $month = 0;
        $day   = 0;

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $trimmed, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            $day   = (int) $m[3];
        } elseif (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $trimmed, $m)) {
            $year  = (int) $m[1];
            $month = (int) $m[2];
            $day   = (int) $m[3];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $trimmed, $m)) {
            $day   = (int) $m[1];
            $month = (int) $m[2];
            $year  = (int) $m[3];
        } else {
            return null;
        }

        if ($year < 1900 || $year > (int) date('Y') || ! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
