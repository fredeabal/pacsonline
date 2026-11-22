<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de centros médicos remitentes y nodos DICOM (recepción y envío C-STORE).
 */
class MedicalCenterModel extends Model
{
    protected $table          = 'medical_centers';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'name',
        'ae_title',
        'api_token_hash',
        'active',
        'can_receive',
        'can_send',
        'dicom_host',
        'dicom_port',
        'studies_count',
        'last_seen_at',
    ];

    public function findActiveByToken(string $plainToken): ?array
    {
        if ($plainToken === '') {
            return null;
        }

        $hash = hash('sha256', $plainToken);

        return $this->where('api_token_hash', $hash)
            ->where('active', 1)
            ->where('can_receive', 1)
            ->first();
    }

    /**
     * Devuelve centros activos habilitados para envío saliente (C-STORE).
     */
    public function findSendable(): array
    {
        return $this->where('active', 1)
            ->where('can_send', 1)
            ->where('dicom_host !=', '')
            ->where('dicom_port >', 0)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public static function orthancModalityId(array $center): string
    {
        return 'center_' . (int) ($center['id'] ?? 0);
    }
}
