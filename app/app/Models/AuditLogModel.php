<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Model;

/**
 * Modelo de auditoría para registrar acciones relevantes sobre estudios, usuarios y centros.
 */
class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'user_id',
        'share_id',
        'study_instance_uid',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public static function record(
        string $action,
        ?string $studyUid = null,
        ?int $shareId = null,
        ?string $details = null,
        ?RequestInterface $request = null
    ): void {
        $req = $request ?? service('request');
        $ip  = method_exists($req, 'getIPAddress') ? $req->getIPAddress() : null;
        $ua  = method_exists($req, 'getUserAgent') ? substr((string) $req->getUserAgent(), 0, 250) : null;

        model(self::class)->insert([
            'user_id'            => auth()->id(),
            'share_id'           => $shareId,
            'study_instance_uid' => $studyUid,
            'action'             => $action,
            'details'            => $details,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);
    }
}
