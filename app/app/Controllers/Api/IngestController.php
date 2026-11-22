<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\OrthancClient;
use App\Models\AuditLogModel;
use App\Models\MedicalCenterModel;
use App\Models\StudyModel;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Endpoint de ingesta DICOM por HTTPS para gateways locales de centros.       //
/////////////////////////////////////////////////////////////////////////////////
class IngestController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Recibe un archivo DICOM autenticado por token Bearer y lo sube a Orthanc//
    /////////////////////////////////////////////////////////////////////////////
    public function dicom(): ResponseInterface
    {
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (! str_starts_with($authHeader, 'Bearer ')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Token Bearer requerido.']);
        }

        $plainToken  = trim(substr($authHeader, 7));
        $centerModel = model(MedicalCenterModel::class);
        $center      = $centerModel->findActiveByToken($plainToken);

        if ($center === null) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Token inválido o centro inactivo.']);
        }

        $payload = null;
        $file    = $this->request->getFile('file');
        if ($file !== null && $file->isValid()) {
            $payload = file_get_contents($file->getTempName());
        } else {
            $rawBody = $this->request->getBody();
            if (is_string($rawBody) && $rawBody !== '') {
                $payload = $rawBody;
            }
        }

        if ($payload === null || $payload === false || $payload === '') {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Archivo DICOM vacío.']);
        }

        $orthanc = new OrthancClient();
        $result  = $orthanc->uploadDicom($payload);

        if (! $result['ok']) {
            return $this->response->setStatusCode(422)->setJSON([
                'error'  => 'El servidor PACS rechazó el archivo DICOM.',
                'status' => $result['status'],
            ]);
        }

        $orthancId = (string) ($result['data']['ParentStudy'] ?? '');
        $studyUid  = null;

        if ($orthancId !== '') {
            $info = $orthanc->getStudy($orthancId);
            if ($info !== null) {
                $studyUid   = $info['MainDicomTags']['StudyInstanceUID'] ?? null;
                $studyModel = model(StudyModel::class);

                if ($studyUid !== null && $studyModel->findByStudyUid($studyUid) === null) {
                    $studyModel->insert([
                        'orthanc_id'         => $orthancId,
                        'study_instance_uid' => $studyUid,
                        'owner_user_id'      => null,
                        'center_id'          => (int) $center['id'],
                        'patient_name'       => $info['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                        'study_description'  => $info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                        'study_date'         => $info['MainDicomTags']['StudyDate'] ?? null,
                    ]);

                    $centerModel->update((int) $center['id'], [
                        'studies_count' => (int) $center['studies_count'] + 1,
                        'last_seen_at'  => date('Y-m-d H:i:s'),
                    ]);
                } else {
                    $centerModel->update((int) $center['id'], [
                        'last_seen_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        AuditLogModel::record(
            'ingest.dicom_received',
            $studyUid,
            null,
            'Recibido desde centro ' . $center['name'] . ' (' . $center['ae_title'] . ')',
            $this->request
        );

        return $this->response->setStatusCode(200)->setJSON([
            'ok'                 => true,
            'center'             => $center['ae_title'],
            'study_instance_uid' => $studyUid,
        ]);
    }
}
