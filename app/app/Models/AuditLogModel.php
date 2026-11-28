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
        ?RequestInterface $request = null,
        ?int $userId = null
    ): void {
        $req = $request ?? service('request');
        $ip  = method_exists($req, 'getIPAddress') ? $req->getIPAddress() : null;
        $ua  = method_exists($req, 'getUserAgent') ? substr((string) $req->getUserAgent(), 0, 250) : null;

        model(self::class)->insert([
            'user_id'            => $userId ?? auth()->id(),
            'share_id'           => $shareId,
            'study_instance_uid' => $studyUid,
            'action'             => $action,
            'details'            => $details,
            'ip_address'         => $ip,
            'user_agent'         => $ua,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve los últimos registros de auditoría junto con el nombre y username del usuario.
     *
     * @return list<array<string, mixed>>
     */
    public function recentWithUsers(int $limit = 200): array
    {
        return $this->select('audit_logs.*, users.username AS user_username, users.full_name AS user_full_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.created_at', 'DESC')
            ->findAll($limit);
    }

    /**
     * Devuelve registros de auditoría paginados y filtrados en servidor.
     *
     * @param array{category?: string, date?: string, q?: string} $filters
     * @return array{logs: list<array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
     */
    public function paginatedWithUsers(array $filters = [], int $perPage = 50, int $page = 1): array
    {
        $perPage = max(1, min(200, $perPage));
        $page    = max(1, $page);

        $countBuilder = $this->builder();
        $countBuilder->join('users', 'users.id = audit_logs.user_id', 'left');
        $this->applyAuditFilters($countBuilder, $filters);
        $total = (int) $countBuilder->countAllResults();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $dataBuilder = $this->builder();
        $dataBuilder->select('audit_logs.*, users.username AS user_username, users.full_name AS user_full_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left');
        $this->applyAuditFilters($dataBuilder, $filters);
        $logs = $dataBuilder->orderBy('audit_logs.created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'logs'       => $logs,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Aplica filtros de categoría, rango de fecha y búsqueda textual al query builder.
     *
     * @param \CodeIgniter\Database\BaseBuilder $builder
     * @param array{category?: string, date?: string, q?: string} $filters
     */
    private function applyAuditFilters($builder, array $filters): void
    {
        $category = (string) ($filters['category'] ?? 'all');
        switch ($category) {
            case 'auth':
                $builder->like('audit_logs.action', 'auth.', 'after');
                break;
            case 'study':
                $builder->groupStart()
                    ->like('audit_logs.action', 'study.', 'after')
                    ->orLike('audit_logs.action', 'ingest.', 'after')
                    ->groupEnd();
                break;
            case 'report':
                $builder->like('audit_logs.action', 'report.', 'after');
                break;
            case 'patient':
                $builder->groupStart()
                    ->like('audit_logs.action', 'patient_access.', 'after')
                    ->orLike('audit_logs.action', 'notification.', 'after')
                    ->groupEnd();
                break;
            case 'admin':
                $builder->groupStart()
                    ->like('audit_logs.action', 'user.', 'after')
                    ->orLike('audit_logs.action', 'center.', 'after')
                    ->orLike('audit_logs.action', 'settings.', 'after')
                    ->orLike('audit_logs.action', 'profile.', 'after')
                    ->groupEnd();
                break;
            case 'errors':
                $builder->groupStart()
                    ->like('audit_logs.action', '_failed', 'before')
                    ->orWhereIn('audit_logs.action', [
                        'patient_access.failed',
                        'patient_access.locked',
                        'patient_access.throttled',
                    ])
                    ->groupEnd();
                break;
        }

        $date = (string) ($filters['date'] ?? 'all');
        $cutoff = match ($date) {
            'today' => date('Y-m-d 00:00:00'),
            '24h'   => date('Y-m-d H:i:s', time() - 86400),
            '7d'    => date('Y-m-d H:i:s', time() - 7 * 86400),
            '30d'   => date('Y-m-d H:i:s', time() - 30 * 86400),
            default => null,
        };
        if ($cutoff !== null) {
            $builder->where('audit_logs.created_at >=', $cutoff);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $builder->groupStart()
                ->like('users.username', $q)
                ->orLike('users.full_name', $q)
                ->orLike('audit_logs.action', $q)
                ->orLike('audit_logs.details', $q)
                ->orLike('audit_logs.ip_address', $q)
                ->groupEnd();
        }
    }

    /**
     * Traduce el código interno de acción de auditoría a una etiqueta corta legible.
     */
    public static function actionLabel(string $action): string
    {
        static $labels = [
            'notification.email_sent'           => 'Aviso enviado',
            'notification.email_failed'         => 'Error envío aviso',
            'notification.welcome_sent'         => 'Bienvenida enviada',
            'notification.welcome_failed'       => 'Error bienvenida',
            'notification.doctor_assigned_sent' => 'Aviso a médico',
            'notification.doctor_assigned_failed' => 'Error aviso médico',
            'patient_access.email_sent'         => 'Enlace enviado',
            'patient_access.email_failed'       => 'Error envío enlace',
            'patient_access.created'            => 'Enlace creado',
            'patient_access.updated'            => 'Enlace editado',
            'patient_access.revoked'            => 'Enlace revocado',
            'patient_access.deleted'            => 'Enlace eliminado',
            'patient_access.accessed'           => 'Acceso paciente',
            'patient_access.throttled'          => 'Acceso limitado',
            'patient_access.locked'             => 'Enlace bloqueado',
            'patient_access.failed'             => 'PIN incorrecto',
            'patient_access.report_downloaded'  => 'PDF por paciente',
            'patient_access.zip_downloaded'     => 'ZIP por paciente',
            'user.created'                      => 'Usuario creado',
            'user.updated'                      => 'Usuario editado',
            'user.deleted'                      => 'Usuario eliminado',
            'report.published'                  => 'Informe publicado',
            'report.saved'                      => 'Borrador informe',
            'report.pdf_downloaded'             => 'PDF descargado',
            'report.deleted'                    => 'Informe eliminado',
            'settings.smtp_updated'             => 'SMTP guardado',
            'settings.temp_cleared'             => 'Limpieza temporal',
            'center.created'                    => 'Centro creado',
            'center.updated'                    => 'Centro editado',
            'center.echo_ok'                    => 'C-ECHO correcto',
            'center.echo_failed'                => 'C-ECHO fallido',
            'center.token_rotated'              => 'Token rotado',
            'center.deleted'                    => 'Centro eliminado',
            'profile.updated'                   => 'Perfil editado',
            'profile.password_changed'          => 'Clave cambiada',
            'study.assigned'                    => 'Paciente asignado',
            'study.downloaded'                  => 'ZIP descargado',
            'study.case_updated'                => 'Caso editado',
            'study.dicom_patient_updated'       => 'DICOM editado',
            'study.notes_updated'               => 'Notas guardadas',
            'study.attachment_uploaded'         => 'Adjunto subido',
            'study.attachment_deleted'          => 'Adjunto eliminado',
            'study.deleted'                     => 'Estudio eliminado',
            'study.send_failed'                 => 'Error envío DICOM',
            'study.sent_dicom'                  => 'DICOM enviado',
            'study.uploaded'                    => 'Estudio subido',
            'ingest.dicom_received'             => 'DICOM recibido',
            'auth.login'                        => 'Inicio de sesión',
            'auth.login_failed'                 => 'Login fallido',
            'auth.logout'                       => 'Cierre de sesión',
        ];

        return $labels[$action] ?? $action;
    }
}
