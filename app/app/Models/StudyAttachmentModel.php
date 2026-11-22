<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo de archivos adjuntos (PDF, imágenes clínicas) vinculados a un estudio DICOM.
 */
class StudyAttachmentModel extends Model
{
    protected $table         = 'study_attachments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'study_instance_uid',
        'uploaded_by_user_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'created_at',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function forStudy(string $studyUid): array
    {
        return $this->where('study_instance_uid', $studyUid)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}
