<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
//Endpoint interno llamado por Nginx auth_request para proteger /dicom-web/./////
/////////////////////////////////////////////////////////////////////////////////
class DicomAuthController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Valida si la petición a /dicom-web/ pertenece a un usuario o invitado. //
    /////////////////////////////////////////////////////////////////////////////
    public function check(): ResponseInterface
    {
        $originalUri = $this->request->getHeaderLine('X-Original-URI');

        // 1. Usuario autenticado en el portal
        if (auth()->loggedIn()) {
            $user = auth()->user();
            if ($user !== null && $user->can('studies.view_all')) {
                return $this->response->setStatusCode(200);
            }

            $requestedUid = $this->extractStudyUid($originalUri);
            if ($requestedUid === null) {
                return $this->response->setStatusCode(403);
            }

            $study = model(StudyModel::class)->findByStudyUid($requestedUid);
            if ($study !== null && (int) $study['owner_user_id'] === (int) auth()->id()) {
                return $this->response->setStatusCode(200);
            }

            return $this->response->setStatusCode(403);
        }

        // 2. Paciente verificado con Magic Link (Documento + Fecha de nacimiento)
        $patientAccess = session()->get('patient_access');
        if (is_array($patientAccess) && ! empty($patientAccess['link_id']) && ! empty($patientAccess['study_uid'])) {
            $linkId     = (int) $patientAccess['link_id'];
            $allowedUid = (string) $patientAccess['study_uid'];
            $unlockedAt = (int) ($patientAccess['unlocked_at'] ?? 0);

            if (
                $unlockedAt <= 0
                || (time() - $unlockedAt) > PatientAccessController::SESSION_TTL_SECONDS
                || ! model(PatientAccessLinkModel::class)->isStillActive($linkId)
            ) {
                session()->remove('patient_access');
                return $this->response->setStatusCode(401);
            }

            $requestedUid = $this->extractStudyUid($originalUri);
            if ($requestedUid !== null && $requestedUid === $allowedUid) {
                return $this->response->setStatusCode(200);
            }

            return $this->response->setStatusCode(403);
        }

        return $this->response->setStatusCode(401);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Extrae el StudyInstanceUID de la ruta o query string de /dicom-web/.   //
    /////////////////////////////////////////////////////////////////////////////
    private function extractStudyUid(string $uri): ?string
    {
        if (preg_match('#/studies/([^/?]+)#', $uri, $m)) {
            return $m[1];
        }

        $query = parse_url($uri, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
            if (! empty($params['StudyInstanceUID'])) {
                return (string) $params['StudyInstanceUID'];
            }
            if (! empty($params['0020000D'])) {
                return (string) $params['0020000D'];
            }
        }

        return null;
    }
}
