<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de enlaces compartidos con expiración y PIN para acceso externo a estudios.
 */
class StudyShareModel extends Model
{
    protected $table          = 'study_shares';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'study_instance_uid',
        'created_by_user_id',
        'token',
        'recipient_label',
        'pin_hash',
        'allow_download',
        'views_count',
        'expires_at',
        'revoked_at',
    ];

    public function findActiveByToken(string $token): ?array
    {
        $share = $this->where('token', $token)
            ->where('revoked_at', null)
            ->first();

        if ($share === null) {
            return null;
        }

        if (! empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
            return null;
        }

        return $share;
    }

    public function isStillActive(int $shareId): bool
    {
        $share = $this->find($shareId);
        if ($share === null || ! empty($share['revoked_at'])) {
            return false;
        }

        if (! empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Cuenta enlaces no revocados por cada StudyInstanceUID en una sola consulta.
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
            ->groupBy('study_instance_uid')
            ->findAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['study_instance_uid']] = (int) $row['total'];
        }

        return $counts;
    }
}
