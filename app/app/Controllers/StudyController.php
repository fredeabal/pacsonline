<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\NotificationService;
use App\Libraries\OrthancClient;
use App\Models\AuditLogModel;
use App\Models\MedicalCenterModel;
use App\Models\MedicalReportModel;
use App\Models\StudyAttachmentModel;
use App\Models\StudyModel;
use App\Models\StudyShareModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/////////////////////////////////////////////////////////////////////////////////
// Listado, búsqueda, subida, asignación, adjuntos y envío DICOM de estudios.  //
/////////////////////////////////////////////////////////////////////////////////
class StudyController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Lista estudios paginados desde Orthanc o propios según rol y filtros.  //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        $user = auth()->user();
        if ($user !== null && $user->can('reports.review') && ! $user->can('users.manage')) {
            return redirect()->to('/reports');
        }

        $orthanc     = new OrthancClient();
        $studyModel  = model(StudyModel::class);
        $shareModel  = model(StudyShareModel::class);
        $reportModel = model(MedicalReportModel::class);

        $user       = auth()->user();
        $canViewAll = $user?->can('studies.view_all') ?? false;
        $canManage  = $user?->can('users.manage') ?? false;
        $userId     = auth()->id();

        $patients = $canManage ? $this->usersByGroup('paciente') : [];

        $perPage  = 25;
        $page     = max(1, (int) ($this->request->getGet('page') ?? 1));
        $search     = trim((string) ($this->request->getGet('q') ?? ''));
        $dateFilter = resolve_date_filter(
            $this->request->getGet('date_preset'),
            $this->request->getGet('date_from'),
            $this->request->getGet('date_to')
        );
        $datePreset = $dateFilter['preset'];
        $dateFrom   = $dateFilter['dateFrom'];
        $dateTo     = $dateFilter['dateTo'];
        $offset     = ($page - 1) * $perPage;

        $rawStudies   = [];
        $hasNext      = false;
        $totalStudies = null;

        if ($canViewAll) {
            $baseQuery = [];
            $dateRange = build_dicom_date_range($dateFrom, $dateTo);
            if ($dateRange !== null) {
                $baseQuery['StudyDate'] = $dateRange;
            }
            $fetched      = $orthanc->searchStudiesPage($offset, $perPage + 1, $search, $baseQuery);
            $hasNext      = count($fetched) > $perPage;
            $rawStudies   = $hasNext ? array_slice($fetched, 0, $perPage) : $fetched;
            $totalStudies = ($search === '' && $dateRange === null) ? $orthanc->countStudies() : null;
        } else {
            $ownedRows = $studyModel
                ->where('owner_user_id', $userId)
                ->orderBy('id', 'DESC')
                ->findAll($perPage + 1, $offset);
            $hasNext = count($ownedRows) > $perPage;
            if ($hasNext) {
                $ownedRows = array_slice($ownedRows, 0, $perPage);
            }
            foreach ($ownedRows as $row) {
                $found = $orthanc->findStudyByUid((string) ($row['study_instance_uid'] ?? ''));
                if ($found === null || ! study_matches_date_range($found, $dateFrom, $dateTo)) {
                    continue;
                }
                if ($search !== '' && ! $this->studyMatchesText($found, $search)) {
                    continue;
                }
                $rawStudies[] = $found;
            }
        }

        $uids = [];
        foreach ($rawStudies as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        $recordsByUid = $studyModel->mapByStudyUids($uids);
        $reportsByUid = $reportModel->mapByStudyUids($uids);
        $sharesByUid  = $shareModel->countActiveByStudyUids($uids);

        $studies = [];
        foreach ($rawStudies as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid === '') {
                continue;
            }

            $study['_active_shares'] = $sharesByUid[$uid] ?? 0;
            $study['_record']        = $recordsByUid[$uid] ?? null;
            $study['_report']        = $reportsByUid[$uid] ?? null;
            $studies[]               = $study;
        }

        return view('studies/index', [
            'title'        => 'Estudios DICOM | PACS Online',
            'studies'      => $studies,
            'patients'     => $patients,
            'page'         => $page,
            'perPage'      => $perPage,
            'hasNext'      => $hasNext,
            'totalStudies' => $totalStudies,
            'search'       => $search,
            'datePreset'   => $datePreset,
            'dateFrom'     => $dateFrom,
            'dateTo'       => $dateTo,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el formulario de subida manual de archivos DICOM o ZIP.         //
    /////////////////////////////////////////////////////////////////////////////
    public function uploadForm(): string|RedirectResponse
    {
        if (! auth()->user()?->can('studies.upload')) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para subir estudios.');
        }

        $patients = $this->usersByGroup('paciente');
        $doctors  = $this->usersByGroup('medico');

        $centers = model(MedicalCenterModel::class)->where('active', 1)->orderBy('name', 'ASC')->findAll();

        return view('studies/upload', [
            'title'    => 'Subir Estudio | PACS Online',
            'patients' => $patients,
            'doctors'  => $doctors,
            'centers'  => $centers,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Procesa la subida de archivos DICOM/ZIP hacia Orthanc y registra datos. //
    /////////////////////////////////////////////////////////////////////////////
    public function upload(): RedirectResponse
    {
        if (! auth()->user()?->can('studies.upload')) {
            return redirect()->back()->with('error', 'Sin permiso para subir estudios.');
        }

        $rules = [
            'dicom_file' => [
                'label' => 'Archivo DICOM o ZIP',
                'rules' => 'uploaded[dicom_file]|ext_in[dicom_file,dcm,dicom,zip]|max_size[dicom_file,524288]',
            ],
            'patient_user_id' => [
                'label' => 'Paciente',
                'rules' => 'permit_empty|integer',
            ],
            'center_id' => [
                'label' => 'Centro',
                'rules' => 'permit_empty|integer',
            ],
            'assigned_doctor_id' => [
                'label' => 'Médico',
                'rules' => 'permit_empty|integer',
            ],
            'priority' => [
                'label' => 'Prioridad',
                'rules' => 'permit_empty|in_list[normal,urgent]',
            ],
            'new_patient_name' => [
                'label' => 'Nombre del paciente',
                'rules' => 'permit_empty|max_length[150]',
            ],
            'new_patient_email' => [
                'label' => 'Correo del paciente',
                'rules' => 'permit_empty|valid_email|is_unique[auth_identities.secret]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $file = $this->request->getFile('dicom_file');
        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Archivo no válido.');
        }

        $post            = $this->validator->getValidated();
        $patientUserId   = ! empty($post['patient_user_id']) ? (int) $post['patient_user_id'] : null;
        $centerId        = ! empty($post['center_id']) ? (int) $post['center_id'] : null;
        $doctorId        = ! empty($post['assigned_doctor_id']) ? (int) $post['assigned_doctor_id'] : null;
        $priority        = $post['priority'] ?: 'normal';
        $createdNote     = '';

        if (! empty($post['new_patient_email'])) {
            $tempPass  = bin2hex(random_bytes(8));
            $baseUser  = preg_replace('/[^a-z0-9_]/', '', strtolower(explode('@', $post['new_patient_email'])[0])) ?: 'paciente';
            $username  = $baseUser . '_' . random_int(100, 999);
            $usersProv = auth()->getProvider();

            $newUser = new User([
                'username'  => $username,
                'full_name' => $post['new_patient_name'] ?: $username,
                'email'     => $post['new_patient_email'],
                'password'  => $tempPass,
            ]);
            $usersProv->save($newUser);
            $savedPatient = $usersProv->findById($usersProv->getInsertID());
            if ($savedPatient !== null) {
                $savedPatient->activate();
                $savedPatient->addGroup('paciente');
                $patientUserId = (int) $savedPatient->id;

                $emailSent   = (new NotificationService())->sendPatientWelcomeEmail($savedPatient, $tempPass);
                $createdNote = $emailSent
                    ? ' Paciente creado y credenciales enviadas.'
                    : ' Paciente creado (SMTP no activo; asigna clave en Usuarios).';
            }
        }

        $meta = [
            'owner_user_id'      => $patientUserId,
            'center_id'          => $centerId,
            'assigned_doctor_id' => $doctorId,
            'priority'           => $priority,
        ];

        $orthanc = new OrthancClient();
        $ext     = strtolower($file->getClientExtension());

        if ($ext === 'zip') {
            $summary = $orthanc->uploadZip($file->getTempName());
            foreach ($summary['study_ids'] as $orthancId) {
                $this->registerStudy($orthanc, $orthancId, $meta);
            }

            if ($summary['uploaded'] > 0) {
                $msg = "{$summary['uploaded']} instancias subidas." . $createdNote;
                if ($summary['failed'] > 0) {
                    $msg .= " ({$summary['failed']} con error).";
                }
                return redirect()->to('/studies')->with('message', $msg);
            }

            return redirect()->back()->with('error', 'Sin archivos DICOM válidos en el ZIP.');
        }

        $payload = file_get_contents($file->getTempName());
        if ($payload === false) {
            return redirect()->back()->with('error', 'Error al leer el archivo.');
        }

        $result = $orthanc->uploadDicom($payload);
        if ($result['ok']) {
            if (! empty($result['data']['ParentStudy'])) {
                $this->registerStudy($orthanc, (string) $result['data']['ParentStudy'], $meta);
            }
            return redirect()->to('/studies')->with('message', 'Estudio subido.' . $createdNote);
        }

        return redirect()->back()->with('error', 'Error del servidor PACS (HTTP ' . $result['status'] . ').');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Asigna un paciente propietario a un estudio desde el listado rápido.    //
    /////////////////////////////////////////////////////////////////////////////
    public function assignPatient(): RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para asignar.');
        }

        $rules = [
            'study_instance_uid' => [
                'label' => 'Estudio',
                'rules' => 'required|max_length[128]',
            ],
            'orthanc_id' => [
                'label' => 'ID de Orthanc',
                'rules' => 'required|max_length[128]',
            ],
            'patient_user_id' => [
                'label' => 'Paciente',
                'rules' => 'permit_empty|integer',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $data      = $this->validator->getValidated();
        $uid       = $data['study_instance_uid'];
        $patientId = ! empty($data['patient_user_id']) ? (int) $data['patient_user_id'] : null;

        $studyModel = model(StudyModel::class);
        $record     = $studyModel->findByStudyUid($uid);

        if ($record !== null) {
            $studyModel->update((int) $record['id'], ['owner_user_id' => $patientId]);
        } else {
            $orthanc = new OrthancClient();
            $info    = $orthanc->getStudy($data['orthanc_id']);
            $studyModel->insert([
                'orthanc_id'         => $data['orthanc_id'],
                'study_instance_uid' => $uid,
                'owner_user_id'      => $patientId,
                'patient_name'       => $info['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                'study_description'  => $info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                'study_date'         => $info['MainDicomTags']['StudyDate'] ?? null,
            ]);
        }

        AuditLogModel::record('study.assigned', $uid, null, 'Paciente asignado ID: ' . ($patientId ?? 'ninguno'), $this->request);

        return redirect()->to('/studies')->with('message', 'Paciente asignado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Descarga el archivo ZIP completo del estudio desde Orthanc (streaming). //
    /////////////////////////////////////////////////////////////////////////////
    public function downloadZip(string $studyUid): ResponseInterface|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $study      = model(StudyModel::class)->findByStudyUid($studyUid);
        $canViewAll = $user->can('studies.view_all');
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $canViewAll && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para descargar.');
        }

        $orthancId = $study['orthanc_id'] ?? null;
        $orthanc   = new OrthancClient();

        if (empty($orthancId)) {
            $found     = $orthanc->findStudyByUid($studyUid);
            $orthancId = $found['ID'] ?? null;
        }

        if (empty($orthancId)) {
            return redirect()->back()->with('error', 'Estudio no encontrado en PACS.');
        }

        $filename    = 'estudio-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $studyUid) . '.zip';
        $headersSent = false;

        $streamed = $orthanc->streamStudyArchive(
            (string) $orthancId,
            static function () use ($filename, &$headersSent): void {
                $headersSent = true;
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: no-store');
            },
            static function (string $chunk): int {
                echo $chunk;
                flush();
                return strlen($chunk);
            }
        );

        if (! $streamed) {
            if ($headersSent) {
                return $this->response->setStatusCode(502);
            }
            return redirect()->back()->with('error', 'Error al generar el ZIP.');
        }

        AuditLogModel::record('study.downloaded', $studyUid, null, 'Descarga DICOM ZIP', $this->request);

        return $this->response->setStatusCode(200);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra el detalle completo del caso (metadatos, informe y adjuntos).   //
    /////////////////////////////////////////////////////////////////////////////
    public function caseView(string $studyUid): string|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $study      = model(StudyModel::class)->findByStudyUid($studyUid);
        $canViewAll = $user->can('studies.view_all');
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $canViewAll && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para ver este caso.');
        }

        $orthanc          = new OrthancClient();
        $orthancStudyInfo = null;
        if ($study === null) {
            $item = $orthanc->findStudyByUid($studyUid);
            if ($item !== null) {
                $orthancStudyInfo = $item;
                $study = [
                    'orthanc_id'         => $item['ID'] ?? '',
                    'study_instance_uid' => $studyUid,
                    'patient_id'         => $item['PatientMainDicomTags']['PatientID'] ?? '-',
                    'patient_name'       => $item['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                    'study_description'  => $item['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                    'study_date'         => $item['MainDicomTags']['StudyDate'] ?? '-',
                    'patient_age'        => extract_patient_age($item, $item['MainDicomTags']['StudyDate'] ?? null),
                    'patient_sex'        => $item['PatientMainDicomTags']['PatientSex'] ?? '',
                    'modality'           => extract_study_modality($item),
                    'priority'           => 'normal',
                ];
            }
        } else {
            $info = ! empty($study['orthanc_id']) ? $orthanc->getStudy((string) $study['orthanc_id']) : null;
            if ($info === null) {
                $info = $orthanc->findStudyByUid($studyUid);
            }
            $orthancStudyInfo    = $info;
            $study['patient_id']  = $info['PatientMainDicomTags']['PatientID'] ?? '-';
            $study['patient_sex'] = $info['PatientMainDicomTags']['PatientSex'] ?? '';
            $study['modality']    = $info !== null ? extract_study_modality($info) : extract_study_modality($study);
            if (empty($study['study_date']) && ! empty($info['MainDicomTags']['StudyDate'])) {
                $study['study_date'] = $info['MainDicomTags']['StudyDate'];
            }
            $study['patient_age'] = $info !== null ? extract_patient_age($info, (string) ($study['study_date'] ?? '')) : '-';
        }

        $study['patient_birth_date'] = $this->formatDicomBirthDateForInput(
            (string) ($orthancStudyInfo['PatientMainDicomTags']['PatientBirthDate'] ?? '')
        );

        $patients = $user->can('users.manage') ? $this->usersByGroup('paciente') : [];
        $doctors  = $user->can('users.manage') ? $this->usersByGroup('medico') : [];

        $attachments  = model(StudyAttachmentModel::class)->forStudy($studyUid);
        $report       = model(MedicalReportModel::class)->findByStudyUid($studyUid);
        $studySeries  = $orthancStudyInfo !== null ? $orthanc->getStudySeries($orthancStudyInfo) : [];
        $dicomDocs    = $orthancStudyInfo !== null ? $orthanc->findStudyDicomDocuments($orthancStudyInfo, $studySeries) : [];

        $accession   = trim((string) ($orthancStudyInfo['MainDicomTags']['AccessionNumber'] ?? ''));
        $dicomStudyId = trim((string) ($orthancStudyInfo['MainDicomTags']['StudyID'] ?? ''));
        $study['study_number'] = $accession !== '' ? $accession : ($dicomStudyId !== '' ? $dicomStudyId : '-');
        $study['series_count'] = $orthancStudyInfo !== null
            ? (string) $orthanc->countImageSeries($orthancStudyInfo, $studySeries)
            : '-';

        $centerName = '';
        if (! empty($study['center_id'])) {
            $center     = model(\App\Models\MedicalCenterModel::class)->find((int) $study['center_id']);
            $centerName = trim((string) ($center['name'] ?? ''));
        }
        if ($centerName === '') {
            $centerName = trim((string) ($orthancStudyInfo['MainDicomTags']['InstitutionName'] ?? ''));
        }
        $study['center_name'] = $centerName !== '' ? $centerName : '-';

        return view('studies/case', [
            'title'              => 'Ficha del Caso | PACS Online',
            'studyUid'           => $studyUid,
            'study'              => $study,
            'patients'           => $patients,
            'doctors'            => $doctors,
            'attachments'        => $attachments,
            'dicomDocs'          => $dicomDocs,
            'report'             => $report,
            'isOwner'            => $isOwner,
            'canEditCaseContent' => $user->can('users.manage') || $isOwner,
            'sendableNodes'      => $user->can('users.manage')
                ? model(\App\Models\MedicalCenterModel::class)->findSendable()
                : [],
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Actualiza paciente, médico asignado, prioridad y centro del caso.       //
    /////////////////////////////////////////////////////////////////////////////
    public function updateCase(string $studyUid): RedirectResponse
    {
        if (! auth()->user()?->can('users.manage')) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar.');
        }

        $rules = [
            'orthanc_id' => [
                'label' => 'ID de Orthanc',
                'rules' => 'permit_empty|max_length[128]',
            ],
            'patient_user_id' => [
                'label' => 'Paciente',
                'rules' => 'permit_empty|integer',
            ],
            'assigned_doctor_id' => [
                'label' => 'Médico',
                'rules' => 'permit_empty|integer',
            ],
            'priority' => [
                'label' => 'Prioridad',
                'rules' => 'required|in_list[normal,urgent]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $data       = $this->validator->getValidated();
        $studyModel = model(StudyModel::class);
        $record     = $studyModel->findByStudyUid($studyUid);

        $previousDoctorId = (int) ($record['assigned_doctor_id'] ?? 0);
        $newDoctorId      = ! empty($data['assigned_doctor_id']) ? (int) $data['assigned_doctor_id'] : null;

        $updateData = [
            'owner_user_id'      => ! empty($data['patient_user_id']) ? (int) $data['patient_user_id'] : null,
            'assigned_doctor_id' => $newDoctorId,
            'priority'           => $data['priority'],
        ];

        if ($record !== null) {
            $studyModel->update((int) $record['id'], $updateData);
        } else {
            $orthanc = new OrthancClient();
            $info    = ! empty($data['orthanc_id']) ? $orthanc->getStudy($data['orthanc_id']) : null;
            $studyModel->insert(array_merge($updateData, [
                'orthanc_id'         => $data['orthanc_id'] ?: '',
                'study_instance_uid' => $studyUid,
                'patient_name'       => $info['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                'study_description'  => $info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                'study_date'         => $info['MainDicomTags']['StudyDate'] ?? null,
            ]));
        }

        if ($newDoctorId !== null && $newDoctorId !== $previousDoctorId) {
            $savedStudy = $studyModel->findByStudyUid($studyUid);
            if ($savedStudy !== null) {
                (new NotificationService())->sendStudyAssignedEmail($studyUid, $savedStudy, $newDoctorId);
            }
        }

        AuditLogModel::record('study.case_updated', $studyUid, null, 'Prioridad: ' . $data['priority'], $this->request);

        return redirect()->to('/studies/case/' . urlencode($studyUid))->with('message', 'Caso actualizado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra la página dedicada para editar etiquetas DICOM del estudio.     //
    /////////////////////////////////////////////////////////////////////////////
    public function editDicom(string $studyUid): string|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null || ! $user->inGroup('admin')) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Solo un administrador puede modificar los datos DICOM.');
        }

        $studyModel = model(StudyModel::class);
        $record     = $studyModel->findByStudyUid($studyUid);
        $orthanc    = new OrthancClient();

        $orthancId = trim((string) ($record['orthanc_id'] ?? ''));
        $info      = $orthancId !== '' ? $orthanc->getStudy($orthancId) : null;
        if ($info === null) {
            $info      = $orthanc->findStudyByUid($studyUid);
            $orthancId = (string) ($info['ID'] ?? '');
        }

        if ($orthancId === '' || $info === null) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'No se encontró el estudio en Orthanc para editar.');
        }

        $pTags = is_array($info['PatientMainDicomTags'] ?? null) ? $info['PatientMainDicomTags'] : [];
        $sTags = is_array($info['MainDicomTags'] ?? null) ? $info['MainDicomTags'] : [];

        return view('studies/dicom_edit', [
            'title'      => 'Editar datos DICOM | PACS Online',
            'studyUid'   => $studyUid,
            'orthancId'  => $orthancId,
            'dicom'      => [
                'patient_name'             => (string) ($pTags['PatientName'] ?? ($record['patient_name'] ?? '')),
                'patient_id'               => (string) ($pTags['PatientID'] ?? ''),
                'patient_birth_date'       => $this->formatDicomBirthDateForInput((string) ($pTags['PatientBirthDate'] ?? '')),
                'patient_sex'              => strtoupper(trim((string) ($pTags['PatientSex'] ?? ''))),
                'study_description'        => (string) ($sTags['StudyDescription'] ?? ($record['study_description'] ?? '')),
                'study_date'               => $this->formatDicomBirthDateForInput((string) ($sTags['StudyDate'] ?? ($record['study_date'] ?? ''))),
                'accession_number'         => (string) ($sTags['AccessionNumber'] ?? ''),
                'referring_physician_name' => (string) ($sTags['ReferringPhysicianName'] ?? ''),
                'institution_name'         => (string) ($sTags['InstitutionName'] ?? ''),
            ],
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Modifica las etiquetas DICOM del paciente y estudio en Orthanc (admin). //
    /////////////////////////////////////////////////////////////////////////////
    public function updateDicomPatient(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null || ! $user->inGroup('admin')) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Solo un administrador puede modificar los datos DICOM.');
        }

        $rules = [
            'orthanc_id' => [
                'label' => 'ID de Orthanc',
                'rules' => 'permit_empty|max_length[128]',
            ],
            'patient_name' => [
                'label' => 'Nombre del paciente',
                'rules' => 'required|min_length[1]|max_length[120]',
            ],
            'patient_id' => [
                'label' => 'ID del paciente',
                'rules' => 'required|min_length[1]|max_length[64]',
            ],
            'patient_birth_date' => [
                'label' => 'Fecha de nacimiento',
                'rules' => 'permit_empty|regex_match[/^(?:\d{2}\/\d{2}\/\d{4}|\d{4}-\d{2}-\d{2})$/]',
            ],
            'patient_sex' => [
                'label' => 'Sexo',
                'rules' => 'permit_empty|in_list[M,F,O]',
            ],
            'study_description' => [
                'label' => 'Descripción del estudio',
                'rules' => 'permit_empty|max_length[160]',
            ],
            'study_date' => [
                'label' => 'Fecha del estudio',
                'rules' => 'permit_empty|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            ],
            'accession_number' => [
                'label' => 'Número de acceso',
                'rules' => 'permit_empty|max_length[64]',
            ],
            'referring_physician_name' => [
                'label' => 'Médico solicitante',
                'rules' => 'permit_empty|max_length[120]',
            ],
            'institution_name' => [
                'label' => 'Centro / Institución',
                'rules' => 'permit_empty|max_length[120]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data       = $this->validator->getValidated();
        $studyModel = model(StudyModel::class);
        $record     = $studyModel->findByStudyUid($studyUid);
        $orthanc    = new OrthancClient();

        $orthancId = trim((string) ($record['orthanc_id'] ?? ''));
        if ($orthancId === '') {
            $orthancId = trim((string) ($data['orthanc_id'] ?? ''));
        }
        $info = $orthancId !== '' ? $orthanc->getStudy($orthancId) : null;
        if ($info === null) {
            $info      = $orthanc->findStudyByUid($studyUid);
            $orthancId = (string) ($info['ID'] ?? '');
        }

        if ($orthancId === '' || $info === null) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'No se encontró el estudio en el PACS para modificar.');
        }

        $oldName = (string) ($info['PatientMainDicomTags']['PatientName'] ?? ($record['patient_name'] ?? 'Anónimo'));
        $oldPid  = trim((string) ($info['PatientMainDicomTags']['PatientID'] ?? '-'));

        $newName = trim((string) $data['patient_name']);
        $newPid  = trim((string) $data['patient_id']);

        $patientTags = [
            'PatientName' => $newName,
            'PatientID'   => $newPid,
        ];

        $birthDate = normalize_date_filter((string) ($data['patient_birth_date'] ?? ''));
        if ($birthDate !== '') {
            [$y, $m, $d] = array_map('intval', explode('-', $birthDate));
            if (! checkdate($m, $d, $y)) {
                return redirect()->back()->withInput()->with('errors', [
                    'patient_birth_date' => 'La fecha de nacimiento no es válida.',
                ]);
            }
            $patientTags['PatientBirthDate'] = str_replace('-', '', $birthDate);
        }

        $sex = trim((string) ($data['patient_sex'] ?? ''));
        if ($sex !== '') {
            $patientTags['PatientSex'] = $sex;
        }

        $sTags     = is_array($info['MainDicomTags'] ?? null) ? $info['MainDicomTags'] : [];
        $studyTags = [];

        $newDesc = trim((string) ($data['study_description'] ?? ''));
        if ($newDesc !== '' || (string) ($sTags['StudyDescription'] ?? '') !== '') {
            $studyTags['StudyDescription'] = $newDesc;
        }

        $newStudyDate = trim((string) ($data['study_date'] ?? ''));
        if ($newStudyDate !== '') {
            $studyTags['StudyDate'] = str_replace('-', '', $newStudyDate);
        }

        $newAcc = trim((string) ($data['accession_number'] ?? ''));
        if ($newAcc !== '' || (string) ($sTags['AccessionNumber'] ?? '') !== '') {
            $studyTags['AccessionNumber'] = $newAcc;
        }

        $newRef = trim((string) ($data['referring_physician_name'] ?? ''));
        if ($newRef !== '' || (string) ($sTags['ReferringPhysicianName'] ?? '') !== '') {
            $studyTags['ReferringPhysicianName'] = $newRef;
        }

        $newInst = trim((string) ($data['institution_name'] ?? ''));
        if ($newInst !== '' || (string) ($sTags['InstitutionName'] ?? '') !== '') {
            $studyTags['InstitutionName'] = $newInst;
        }

        $parentPatientId = trim((string) ($info['ParentPatient'] ?? ''));
        $patientInfo     = $parentPatientId !== '' ? $orthanc->getPatient($parentPatientId) : null;
        $patientStudies  = is_array($patientInfo['Studies'] ?? null) ? $patientInfo['Studies'] : [];
        $modifyAtPatient = $newPid === $oldPid && $parentPatientId !== '' && count($patientStudies) > 1;
        $targetPatient   = $newPid !== $oldPid ? $orthanc->findPatientByPatientId($newPid) : null;

        if ($modifyAtPatient) {
            $result = $orthanc->modifyPatient($parentPatientId, $patientTags);
            if ($result['ok'] && $studyTags !== []) {
                $studyResult = $orthanc->modifyStudy($orthancId, $studyTags);
                if (! $studyResult['ok']) {
                    return redirect()->to('/studies/case/' . urlencode($studyUid))
                        ->with('error', 'Se actualizaron los datos del paciente, pero falló la actualización del estudio: ' . ($studyResult['error'] ?? 'Error desconocido'));
                }
            }
        } elseif ($targetPatient !== null) {
            // El PatientID destino ya existe en Orthanc: primero asociamos el estudio con sus tags actuales
            // y luego actualizamos el paciente destino para mantener consistencia en todos sus estudios.
            $existingPatientTags = is_array($targetPatient['MainDicomTags'] ?? null)
                ? array_filter($targetPatient['MainDicomTags'], static fn ($v): bool => is_string($v) && $v !== '')
                : ['PatientID' => $newPid];
            $moveResult = $orthanc->modifyStudy($orthancId, array_merge($existingPatientTags, $studyTags));
            if ($moveResult['ok']) {
                $targetPatientOrthancId = (string) ($targetPatient['ID'] ?? '');
                $result                 = $targetPatientOrthancId !== ''
                    ? $orthanc->modifyPatient($targetPatientOrthancId, $patientTags)
                    : $moveResult;
                $modifyAtPatient        = true;
            } else {
                $result = $moveResult;
            }
        } else {
            $result = $orthanc->modifyStudy($orthancId, array_merge($patientTags, $studyTags));
        }

        if (! $result['ok']) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'No se pudo modificar el estudio en el PACS: ' . ($result['error'] ?? 'Error desconocido'));
        }

        $refreshed    = $orthanc->findStudyByUid($studyUid);
        $newOrthancId = (string) ($refreshed['ID'] ?? ($modifyAtPatient ? $orthancId : ($result['id'] ?? $orthancId)));

        if ($modifyAtPatient) {
            $newPatientId = (string) ($result['id'] ?? $parentPatientId);
            $newPatient   = $orthanc->getPatient($newPatientId);
            foreach ((array) ($newPatient['Studies'] ?? []) as $pStudyId) {
                $pStudy = $orthanc->getStudy((string) $pStudyId);
                $pUid   = (string) ($pStudy['MainDicomTags']['StudyInstanceUID'] ?? '');
                if ($pUid === '') {
                    continue;
                }
                $existing = $studyModel->findByStudyUid($pUid);
                if ($existing !== null) {
                    $studyModel->update((int) $existing['id'], [
                        'orthanc_id'   => (string) $pStudyId,
                        'patient_name' => $newName,
                    ]);
                }
            }
        }

        $finalStudyDesc = $newDesc !== '' ? $newDesc : ($info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción');
        $finalStudyDate = $studyTags['StudyDate'] ?? ($info['MainDicomTags']['StudyDate'] ?? null);

        if ($record !== null) {
            $studyModel->update((int) $record['id'], [
                'orthanc_id'        => $newOrthancId,
                'patient_name'      => $newName,
                'study_description' => $finalStudyDesc,
                'study_date'        => $finalStudyDate,
            ]);
        } else {
            $studyModel->insert([
                'orthanc_id'         => $newOrthancId,
                'study_instance_uid' => $studyUid,
                'patient_name'       => $newName,
                'study_description'  => $finalStudyDesc,
                'study_date'         => $finalStudyDate,
                'priority'           => 'normal',
            ]);
        }

        AuditLogModel::record(
            'study.dicom_patient_updated',
            $studyUid,
            null,
            sprintf('DICOM: "%s" (%s) -> "%s" (%s) | Desc: %s', $oldName, $oldPid, $newName, $newPid, $finalStudyDesc),
            $this->request
        );

        return redirect()->to('/studies/case/' . urlencode($studyUid))
            ->with('message', 'Datos DICOM actualizados correctamente.');
    }

    /**
     * Convierte una fecha DICOM (YYYYMMDD) al formato HTML input[type=date] (YYYY-MM-DD).
     */
    private function formatDicomBirthDateForInput(string $dicomDate): string
    {
        $clean = preg_replace('/\D/', '', $dicomDate) ?? '';
        if (strlen($clean) !== 8) {
            return '';
        }

        return substr($clean, 0, 4) . '-' . substr($clean, 4, 2) . '-' . substr($clean, 6, 2);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Guarda las notas clínicas asociadas al estudio.                         //
    /////////////////////////////////////////////////////////////////////////////
    public function updateNotes(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $studyModel = model(StudyModel::class);
        $record     = $studyModel->findByStudyUid($studyUid);
        $isOwner    = $record !== null && (int) $record['owner_user_id'] === (int) $user->id;

        if (! $user->can('users.manage') && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para editar notas.');
        }

        $rules = [
            'clinical_notes' => [
                'label' => 'Motivo de consulta',
                'rules' => 'permit_empty|max_length[5000]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $notes = trim((string) ($this->validator->getValidated()['clinical_notes'] ?? ''));

        if ($record !== null) {
            $studyModel->update((int) $record['id'], ['clinical_notes' => $notes !== '' ? $notes : null]);
        } else {
            $orthanc = new OrthancClient();
            $info    = $orthanc->findStudyByUid($studyUid);
            $studyModel->insert([
                'orthanc_id'         => $info['ID'] ?? '',
                'study_instance_uid' => $studyUid,
                'patient_name'       => $info['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                'study_description'  => $info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                'study_date'         => $info['MainDicomTags']['StudyDate'] ?? null,
                'clinical_notes'     => $notes !== '' ? $notes : null,
            ]);
        }

        AuditLogModel::record('study.notes_updated', $studyUid, null, 'Motivo de consulta actualizado', $this->request);

        return redirect()->to('/studies/case/' . urlencode($studyUid))->with('message', 'Motivo guardado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Sube un documento adjunto (PDF/imagen) vinculado al estudio.            //
    /////////////////////////////////////////////////////////////////////////////
    public function uploadAttachment(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $study   = model(StudyModel::class)->findByStudyUid($studyUid);
        $isOwner = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $user->can('users.manage') && ! $user->can('reports.review') && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para adjuntar.');
        }

        $files = $this->request->getFileMultiple('attachments') ?? [];
        if ($files === []) {
            $single = $this->request->getFile('attachment');
            if ($single !== null) {
                $files = [$single];
            }
        }

        $files = array_values(array_filter($files, static fn ($f): bool => $f !== null && $f->getError() !== UPLOAD_ERR_NO_FILE));
        if ($files === []) {
            return redirect()->back()->with('error', 'Selecciona al menos un archivo.');
        }

        $allowedExt  = ['pdf', 'png', 'jpg', 'jpeg'];
        $allowedMime = ['application/pdf', 'image/png', 'image/jpeg'];
        $maxBytes    = 20 * 1024 * 1024;

        $targetDir = WRITEPATH . 'uploads/attachments';
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $attModel = model(StudyAttachmentModel::class);
        $uploaded = 0;
        $errors   = [];

        foreach ($files as $file) {
            if (! $file->isValid() || $file->hasMoved()) {
                $errors[] = $file->getClientName() . ': archivo no válido.';
                continue;
            }

            $ext          = strtolower((string) $file->getClientExtension());
            $detectedMime = (string) ($file->getMimeType() ?: 'application/octet-stream');

            if (! in_array($ext, $allowedExt, true) || ! in_array($detectedMime, $allowedMime, true)) {
                $errors[] = $file->getClientName() . ': solo PDF, PNG o JPG.';
                continue;
            }

            if ($file->getSize() > $maxBytes) {
                $errors[] = $file->getClientName() . ': máximo 20 MB.';
                continue;
            }

            $storedName = $file->getRandomName();
            $file->move($targetDir, $storedName);

            $attModel->insert([
                'study_instance_uid'  => $studyUid,
                'uploaded_by_user_id' => (int) auth()->id(),
                'original_name'       => $file->getClientName(),
                'stored_name'         => $storedName,
                'mime_type'           => $detectedMime,
                'file_size'           => $file->getSize(),
                'created_at'          => date('Y-m-d H:i:s'),
            ]);

            AuditLogModel::record('study.attachment_uploaded', $studyUid, null, $file->getClientName(), $this->request);
            $uploaded++;
        }

        $redirect = redirect()->to('/studies/case/' . urlencode($studyUid));
        if ($errors !== []) {
            $redirect = $redirect->with('errors', $errors);
        }
        if ($uploaded > 0) {
            $msg = $uploaded === 1 ? 'Archivo adjuntado.' : $uploaded . ' archivos adjuntados.';
            return $redirect->with('message', $msg);
        }

        return $redirect->with('error', 'No se pudo adjuntar.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Descarga o previsualiza un documento adjunto del estudio.               //
    /////////////////////////////////////////////////////////////////////////////
    public function downloadAttachment(int $attachmentId): ResponseInterface|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $att = model(StudyAttachmentModel::class)->find($attachmentId);
        if ($att === null) {
            return redirect()->back()->with('error', 'Archivo no encontrado.');
        }

        $study      = model(StudyModel::class)->findByStudyUid($att['study_instance_uid']);
        $canViewAll = $user->can('studies.view_all');
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $canViewAll && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso.');
        }

        $path = WRITEPATH . 'uploads/attachments/' . basename($att['stored_name']);
        if (! is_file($path)) {
            return redirect()->back()->with('error', 'Archivo no disponible.');
        }

        return $this->response
            ->setHeader('Content-Type', $att['mime_type'] ?: 'application/octet-stream')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($att['original_name']) . '"')
            ->setBody((string) file_get_contents($path));
    }

    /////////////////////////////////////////////////////////////////////////////
    // Extrae y sirve un PDF encapsulado dentro de una instancia DICOM.        //
    /////////////////////////////////////////////////////////////////////////////
    public function downloadDicomDocument(string $studyUid, string $instanceId): ResponseInterface|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $study      = model(StudyModel::class)->findByStudyUid($studyUid);
        $canViewAll = $user->can('studies.view_all');
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $canViewAll && ! $isOwner) {
            return redirect()->to('/studies')->with('error', 'Sin permiso.');
        }

        $orthanc  = new OrthancClient();
        $instance = $orthanc->getInstance($instanceId);
        if ($instance === null) {
            return redirect()->back()->with('error', 'Documento DICOM no encontrado.');
        }

        // Verificar que la instancia pertenece realmente al estudio solicitado.
        $parentSeriesId = (string) ($instance['ParentSeries'] ?? '');
        $parentSeries   = $parentSeriesId !== '' ? $orthanc->getSeries($parentSeriesId) : null;
        $parentStudyId  = (string) ($parentSeries['ParentStudy'] ?? '');
        $parentStudy    = $parentStudyId !== '' ? $orthanc->getStudy($parentStudyId) : null;
        $actualStudyUid = (string) ($parentStudy['MainDicomTags']['StudyInstanceUID'] ?? '');

        if ($actualStudyUid === '' || $actualStudyUid !== $studyUid) {
            return redirect()->to('/studies')->with('error', 'Documento DICOM no corresponde al estudio.');
        }

        $pdf = $orthanc->getInstancePdf($instanceId);
        if ($pdf === null) {
            return redirect()->back()->with('error', 'Documento DICOM no disponible en formato PDF.');
        }

        $tags     = $orthanc->getInstanceSimplifiedTags($instanceId) ?? [];
        $rawTitle = trim((string) ($tags['DocumentTitle'] ?? ''));
        if ($rawTitle === '') {
            $rawTitle = trim((string) ($parentSeries['MainDicomTags']['SeriesDescription'] ?? ''));
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $rawTitle !== '' ? $rawTitle : 'Informe_DICOM');
        $safeName = trim((string) $safeName, '_');
        if ($safeName === '') {
            $safeName = 'Informe_DICOM';
        }
        if (! str_ends_with(strtolower($safeName), '.pdf')) {
            $safeName .= '.pdf';
        }

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->setBody($pdf);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina un documento adjunto del disco y de la base de datos.           //
    /////////////////////////////////////////////////////////////////////////////
    public function deleteAttachment(int $attachmentId): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $attModel = model(StudyAttachmentModel::class);
        $att      = $attModel->find($attachmentId);
        if ($att === null) {
            return redirect()->back()->with('error', 'Archivo no encontrado.');
        }

        $study      = model(StudyModel::class)->findByStudyUid($att['study_instance_uid']);
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;
        $isUploader = (int) $att['uploaded_by_user_id'] === (int) $user->id;

        if (! $user->can('users.manage') && ! $isOwner && ! $isUploader) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para eliminar.');
        }

        $path = WRITEPATH . 'uploads/attachments/' . basename((string) $att['stored_name']);
        if (is_file($path)) {
            @unlink($path);
        }

        $attModel->delete($attachmentId);
        AuditLogModel::record('study.attachment_deleted', $att['study_instance_uid'], null, $att['original_name'], $this->request);

        return redirect()->to('/studies/case/' . urlencode($att['study_instance_uid']))->with('message', 'Archivo eliminado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina un estudio completo de Orthanc y sus registros locales.         //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null || ! $user->can('users.manage')) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para eliminar.');
        }

        $studyModel = model(StudyModel::class);
        $study      = $studyModel->findByStudyUid($studyUid);
        $orthancId  = $study['orthanc_id'] ?? null;
        $orthanc    = new OrthancClient();

        if (empty($orthancId)) {
            $found     = $orthanc->findStudyByUid($studyUid);
            $orthancId = $found['ID'] ?? null;
        }

        if (! empty($orthancId) && ! $orthanc->deleteStudy((string) $orthancId)) {
            return redirect()->back()->with('error', 'Error al eliminar del PACS.');
        }

        $attModel = model(StudyAttachmentModel::class);
        foreach ($attModel->where('study_instance_uid', $studyUid)->findAll() as $att) {
            $path = WRITEPATH . 'uploads/attachments/' . basename((string) $att['stored_name']);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $attModel->where('study_instance_uid', $studyUid)->delete();

        model(MedicalReportModel::class)->where('study_instance_uid', $studyUid)->delete();
        model(StudyShareModel::class)->where('study_instance_uid', $studyUid)->delete();
        $studyModel->where('study_instance_uid', $studyUid)->delete();

        AuditLogModel::record('study.deleted', $studyUid, null, 'Estudio eliminado por administrador', $this->request);

        return redirect()->to('/studies')->with('message', 'Estudio eliminado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Envía un estudio vía C-STORE DICOM a un nodo destino de /centers.       //
    /////////////////////////////////////////////////////////////////////////////
    public function sendToNode(string $studyUid): ResponseInterface|RedirectResponse
    {
        $respond = function (bool $ok, string $message, int $status = 200): ResponseInterface|RedirectResponse {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode($status)->setJSON([
                    'ok'        => $ok,
                    'message'   => $message,
                    'csrf_hash' => csrf_hash(),
                ]);
            }

            return redirect()->back()->with($ok ? 'message' : 'error', $message);
        };

        $user = auth()->user();
        if ($user === null || ! $user->can('users.manage')) {
            return $respond(false, 'Sin permiso para enviar estudios.', 403);
        }

        $centerId = (int) $this->request->getPost('center_id');
        $center   = model(\App\Models\MedicalCenterModel::class)->find($centerId);
        if ($center === null || empty($center['active']) || empty($center['can_send']) || empty($center['dicom_host']) || empty($center['dicom_port'])) {
            return $respond(false, 'Nodo destino no válido o sin envío habilitado.', 422);
        }

        $study     = model(StudyModel::class)->findByStudyUid($studyUid);
        $orthancId = $study['orthanc_id'] ?? null;
        $orthanc   = new OrthancClient();

        if (empty($orthancId)) {
            $found     = $orthanc->findStudyByUid($studyUid);
            $orthancId = $found['ID'] ?? null;
        }

        if (empty($orthancId)) {
            return $respond(false, 'Estudio no encontrado en Orthanc.', 404);
        }

        $modalityId = \App\Models\MedicalCenterModel::orthancModalityId($center);
        $putRes     = $orthanc->putModality($modalityId, [
            'AET'  => (string) $center['ae_title'],
            'Host' => (string) $center['dicom_host'],
            'Port' => (int) $center['dicom_port'],
        ]);

        if (empty($putRes['ok'])) {
            return $respond(false, 'No se pudo preparar el nodo en Orthanc: ' . ($putRes['error'] ?? 'Error desconocido'), 502);
        }

        $storeRes = $orthanc->storeToModality($modalityId, [(string) $orthancId], true);
        if (empty($storeRes['ok'])) {
            AuditLogModel::record(
                'study.send_failed',
                $studyUid,
                null,
                "Fallo envío a {$center['ae_title']} ({$center['dicom_host']}:{$center['dicom_port']}): " . ($storeRes['error'] ?? 'Error'),
                $this->request
            );
            return $respond(false, 'Error al enviar estudio a ' . $center['ae_title'] . ': ' . ($storeRes['error'] ?? 'Error desconocido'), 502);
        }

        $count = (int) ($storeRes['instances_count'] ?? 0);
        AuditLogModel::record(
            'study.sent_dicom',
            $studyUid,
            null,
            "Enviado a {$center['name']} ({$center['ae_title']} @ {$center['dicom_host']}:{$center['dicom_port']}) ({$count} instancias)",
            $this->request
        );

        return $respond(true, "Estudio enviado correctamente a {$center['name']} ({$center['ae_title']}) — {$count} instancia(s).");
    }

    private function registerStudy(OrthancClient $orthanc, string $orthancId, array $meta = []): void
    {
        $info = $orthanc->getStudy($orthancId);
        if ($info === null) {
            return;
        }

        $uid = $info['MainDicomTags']['StudyInstanceUID'] ?? '';
        if ($uid === '') {
            return;
        }

        $studyModel = model(StudyModel::class);
        if ($studyModel->findByStudyUid($uid) === null) {
            $studyModel->insert([
                'orthanc_id'         => $orthancId,
                'study_instance_uid' => $uid,
                'owner_user_id'      => $meta['owner_user_id'] ?? null,
                'center_id'          => $meta['center_id'] ?? null,
                'assigned_doctor_id' => $meta['assigned_doctor_id'] ?? null,
                'priority'           => $meta['priority'] ?? 'normal',
                'patient_name'       => $info['PatientMainDicomTags']['PatientName'] ?? 'Anónimo',
                'study_description'  => $info['MainDicomTags']['StudyDescription'] ?? 'Sin descripción',
                'study_date'         => $info['MainDicomTags']['StudyDate'] ?? null,
            ]);

            if (! empty($meta['assigned_doctor_id'])) {
                $savedStudy = $studyModel->findByStudyUid($uid);
                if ($savedStudy !== null) {
                    (new NotificationService())->sendStudyAssignedEmail($uid, $savedStudy, (int) $meta['assigned_doctor_id']);
                }
            }
        }

        AuditLogModel::record('study.uploaded', $uid, null, 'Subida manual por admin al PACS', $this->request);
    }

    /**
     * Obtiene usuarios pertenecientes a un grupo concreto indexados por ID.
     *
     * @return array<int, User>
     */
    private function usersByGroup(string $group): array
    {
        $rows = auth()->getProvider()
            ->select('users.*')
            ->join('auth_groups_users', 'auth_groups_users.user_id = users.id')
            ->where('auth_groups_users.group', $group)
            ->orderBy('users.id', 'ASC')
            ->findAll();

        $result = [];
        foreach ($rows as $u) {
            $result[(int) $u->id] = $u;
        }

        return $result;
    }

    /**
     * Comprueba si un estudio de Orthanc coincide con el texto de búsqueda (paciente, ID o nº de caso).
     *
     * @param array<string, mixed> $study
     */
    private function studyMatchesText(array $study, string $search): bool
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return true;
        }

        $fields = [
            (string) ($study['PatientMainDicomTags']['PatientName'] ?? ''),
            (string) ($study['PatientMainDicomTags']['PatientID'] ?? ''),
            (string) ($study['MainDicomTags']['AccessionNumber'] ?? ''),
            (string) ($study['MainDicomTags']['StudyID'] ?? ''),
        ];

        foreach ($fields as $value) {
            if ($value !== '' && mb_stripos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
