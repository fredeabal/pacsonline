<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\AiAssistantService;
use App\Libraries\NotificationService;
use App\Libraries\OrthancClient;
use App\Libraries\ReportPdfService;
use App\Models\AuditLogModel;
use App\Models\MedicalReportModel;
use App\Models\ReportTemplateModel;
use App\Models\StudyAttachmentModel;
use App\Models\StudyModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/////////////////////////////////////////////////////////////////////////////////
// Worklist médico, emisión/edición de informes clínicos y exportación a PDF. //
/////////////////////////////////////////////////////////////////////////////////
class ReportController extends BaseController
{
    /////////////////////////////////////////////////////////////////////////////
    // Muestra el worklist médico con filtros por estado, modalidad y fecha.   //
    /////////////////////////////////////////////////////////////////////////////
    public function index(): string|RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso para acceder a informes.');
        }

        $orthanc        = new OrthancClient();
        $reportModel    = model(MedicalReportModel::class);
        $studyModel     = model(StudyModel::class);
        $attModel       = model(StudyAttachmentModel::class);
        $filter         = (string) ($this->request->getGet('status') ?? 'all');
        $scope          = (string) ($this->request->getGet('scope') ?? 'all');
        $modalityFilter = strtoupper(trim((string) ($this->request->getGet('modality') ?? 'ALL')));
        $search         = trim((string) ($this->request->getGet('q') ?? ''));
        $dateFilter     = resolve_date_filter(
            $this->request->getGet('date_preset'),
            $this->request->getGet('date_from'),
            $this->request->getGet('date_to')
        );
        $datePreset     = $dateFilter['preset'];
        $dateFrom       = $dateFilter['dateFrom'];
        $dateTo         = $dateFilter['dateTo'];
        $page           = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage        = 25;
        $offset         = ($page - 1) * $perPage;
        $userId         = (int) auth()->id();

        $attachmentCounts = [];
        foreach ($attModel->select('study_instance_uid, COUNT(*) as total')->groupBy('study_instance_uid')->findAll() as $attRow) {
            $attachmentCounts[(string) $attRow['study_instance_uid']] = (int) $attRow['total'];
        }

        $scopedCenterId = user_center_id();
        $query          = [];
        if ($search !== '') {
            $tagKey         = preg_match('/\d/', $search) === 1 ? 'PatientID' : 'PatientName';
            $query[$tagKey] = '*' . $search . '*';
        }
        if ($modalityFilter !== 'ALL') {
            $query['ModalitiesInStudy'] = $modalityFilter;
        }
        $dateRange = build_dicom_date_range($dateFrom, $dateTo);
        if ($dateRange !== null) {
            $query['StudyDate'] = $dateRange;
        }

        if ($filter === 'in_review') {
            $draftRows = $reportModel
                ->whereIn('status', ['in_review', 'draft'])
                ->orderBy('updated_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->findAll();

            $draftUids = array_values(array_unique(array_filter(array_map(
                static fn (array $r): string => trim((string) ($r['study_instance_uid'] ?? '')),
                $draftRows
            ))));

            $draftRecords = $draftUids !== [] ? $studyModel->mapByStudyUids($draftUids) : [];
            $filteredUids = [];
            foreach ($draftUids as $dUid) {
                $rec = $draftRecords[$dUid] ?? null;
                if ($scopedCenterId !== null && (int) ($rec['center_id'] ?? 0) !== $scopedCenterId) {
                    continue;
                }
                if ($dateFrom !== '' && ! empty($rec['study_date']) && (string) $rec['study_date'] < str_replace('-', '', $dateFrom)) {
                    continue;
                }
                if ($dateTo !== '' && ! empty($rec['study_date']) && (string) $rec['study_date'] > str_replace('-', '', $dateTo)) {
                    continue;
                }
                if ($search !== '' && $rec !== null) {
                    $haystack = mb_strtolower(
                        ((string) ($rec['patient_name'] ?? '')) . ' '
                        . ((string) ($rec['study_description'] ?? '')) . ' '
                        . $dUid
                    );
                    if (! str_contains($haystack, mb_strtolower($search))) {
                        continue;
                    }
                }
                $filteredUids[] = $dUid;
            }

            $totalCount = count($filteredUids);
            $pageUids   = array_slice($filteredUids, $offset, $perPage + 1);
            $hasNext    = count($pageUids) > $perPage;
            if ($hasNext) {
                $pageUids = array_slice($pageUids, 0, $perPage);
            }

            $studies = [];
            foreach ($pageUids as $dUid) {
                $rec   = $draftRecords[$dUid] ?? null;
                $oid   = trim((string) ($rec['orthanc_id'] ?? ''));
                $found = $oid !== '' ? $orthanc->getStudy($oid) : null;
                if ($found === null) {
                    $found = $orthanc->findStudyByUid($dUid);
                }
                if ($found !== null) {
                    $studies[] = $found;
                }
            }
        } elseif ($scopedCenterId !== null) {
            $applyCenterFilters = static function (StudyModel $model) use ($scopedCenterId, $dateFrom, $dateTo, $search): StudyModel {
                $model->where('center_id', $scopedCenterId);
                if ($dateFrom !== '') {
                    $model->where('study_date >=', str_replace('-', '', $dateFrom));
                }
                if ($dateTo !== '') {
                    $model->where('study_date <=', str_replace('-', '', $dateTo));
                }
                if ($search !== '') {
                    $model->groupStart()
                        ->like('patient_name', $search, 'both', null, true)
                        ->orLike('study_description', $search, 'both', null, true)
                        ->orLike('study_instance_uid', $search, 'both', null, true)
                        ->groupEnd();
                }

                return $model;
            };

            $centerRows = $applyCenterFilters(model(StudyModel::class))
                ->orderBy('study_date', 'DESC')
                ->orderBy('id', 'DESC')
                ->findAll($perPage + 1, $offset);
            $hasNext = count($centerRows) > $perPage;
            if ($hasNext) {
                $centerRows = array_slice($centerRows, 0, $perPage);
            }
            $studies = [];
            foreach ($centerRows as $row) {
                $oid   = trim((string) ($row['orthanc_id'] ?? ''));
                $found = $oid !== '' ? $orthanc->getStudy($oid) : null;
                if ($found === null) {
                    $found = $orthanc->findStudyByUid((string) ($row['study_instance_uid'] ?? ''));
                }
                if ($found === null) {
                    continue;
                }
                $studies[] = $found;
            }
            $totalCount = ($scope === 'all' && $filter === 'all' && $modalityFilter === 'ALL')
                ? (int) $applyCenterFilters(model(StudyModel::class))->countAllResults()
                : null;
        } else {
            $fetched    = $orthanc->findStudiesPage($offset, $perPage + 1, $query);
            $hasNext    = count($fetched) > $perPage;
            $studies    = $hasNext ? array_slice($fetched, 0, $perPage) : $fetched;
            $totalCount = ($query === [] && $scope === 'all' && $filter === 'all') ? $orthanc->countStudies() : null;
        }

        $uids = [];
        foreach ($studies as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        $recordsByUid = $studyModel->mapByStudyUids($uids);
        $studies      = $studyModel->resolveAndPersistModalities($orthanc, $studies, $recordsByUid);
        $reportsByUid = $reportModel->mapByStudyUids($uids);

        $availableModalities = ['MR' => true, 'CT' => true, 'CR' => true, 'DX' => true, 'US' => true, 'SC' => true];
        $rows                = [];
        foreach ($studies as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid === '') {
                continue;
            }

            $modality = extract_study_modality($study);
            $availableModalities[$modality] = true;

            $record = $recordsByUid[$uid] ?? null;
            $study  = merge_local_study_metadata($study, $record);
            if ($scope === 'mine' && (int) ($record['assigned_doctor_id'] ?? 0) !== $userId) {
                continue;
            }

            $report = $reportsByUid[$uid] ?? null;
            $status = $report['status'] ?? 'pending';
            if ($status === 'draft') {
                $status = 'in_review';
            }

            if ($filter !== 'all' && $status !== $filter) {
                continue;
            }

            if ($modalityFilter !== 'ALL' && $modality !== $modalityFilter) {
                continue;
            }

            $rows[] = [
                'study'             => $study,
                'uid'               => $uid,
                'modality'          => $modality,
                'attachments_count' => $attachmentCounts[$uid] ?? 0,
                'record'            => $record,
                'report'            => $report,
                'status'            => $status,
                'priority'          => $record['priority'] ?? 'normal',
            ];
        }

        // Ordenar poniendo casos urgentes primero
        usort($rows, static function (array $a, array $b): int {
            $pa = ($a['priority'] === 'urgent') ? 0 : 1;
            $pb = ($b['priority'] === 'urgent') ? 0 : 1;

            return $pa <=> $pb;
        });

        $modalities = array_keys($availableModalities);
        sort($modalities);

        return view('reports/index', [
            'title'               => 'Informar Estudios | PACSLine',
            'rows'                => $rows,
            'filter'              => $filter,
            'scope'               => $scope,
            'modalityFilter'      => $modalityFilter,
            'availableModalities' => $modalities,
            'page'                => $page,
            'perPage'             => $perPage,
            'hasNext'             => $hasNext,
            'totalStudies'        => $totalCount,
            'search'              => $search,
            'datePreset'          => $datePreset,
            'dateFrom'            => $dateFrom,
            'dateTo'              => $dateTo,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Muestra la pantalla de redacción/edición de informe para un estudio.    //
    /////////////////////////////////////////////////////////////////////////////
    public function edit(string $studyUid): string|RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso para informar.');
        }

        $study          = model(StudyModel::class)->findByStudyUid($studyUid);
        $scopedCenterId = user_center_id($user);
        if ($scopedCenterId !== null && ! user_can_access_study($user, $study)) {
            return redirect()->to('/reports')->with('error', 'Sin permiso para informar estudios de otro centro.');
        }

        $report = model(MedicalReportModel::class)->findByStudyUid($studyUid);
        if (! user_can_modify_report($user, $report, $study)) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Este informe ya fue firmado y publicado; solo el médico firmante puede modificarlo.');
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
            $study['modality']    = $info !== null ? extract_study_modality($info) : extract_study_modality($study);
            if (empty($study['study_date']) && ! empty($info['MainDicomTags']['StudyDate'])) {
                $study['study_date'] = $info['MainDicomTags']['StudyDate'];
            }
            $study['patient_age'] = $info !== null ? extract_patient_age($info, (string) ($study['study_date'] ?? '')) : '-';
        }

        if ($study !== null) {
            $accession             = trim((string) ($orthancStudyInfo['MainDicomTags']['AccessionNumber'] ?? ''));
            $dicomStudyId          = trim((string) ($orthancStudyInfo['MainDicomTags']['StudyID'] ?? ''));
            $study['study_number'] = $accession !== '' ? $accession : ($dicomStudyId !== '' ? $dicomStudyId : '-');

            $centerName = '';
            if (! empty($study['center_id'])) {
                $center     = model(\App\Models\MedicalCenterModel::class)->find((int) $study['center_id']);
                $centerName = trim((string) ($center['name'] ?? ''));
            }
            if ($centerName === '') {
                $centerName = trim((string) ($orthancStudyInfo['MainDicomTags']['InstitutionName'] ?? ''));
            }
            $study['center_name'] = $centerName !== '' ? $centerName : '-';
        }

        $tplModel = model(ReportTemplateModel::class);
        $tplModel->seedDefaultsForUser((int) auth()->id());
        $templates    = $tplModel->forUser((int) auth()->id());
        $attachments  = model(StudyAttachmentModel::class)->forStudy($studyUid);
        $priorStudies = (new \App\Libraries\PriorStudiesService())->findForStudy(
            $orthanc,
            (string) ($study['patient_id'] ?? ''),
            $studyUid,
            $scopedCenterId === null,
            true,
            (int) auth()->id(),
            15,
            $scopedCenterId
        );

        $assignedDoctor = ! empty($study['assigned_doctor_id'])
            ? auth()->getProvider()->findById((int) $study['assigned_doctor_id'])
            : null;

        return view('reports/edit', [
            'title'          => 'Redactar Informe Médico | PACSLine',
            'studyUid'       => $studyUid,
            'study'          => $study,
            'report'         => $report,
            'templates'      => $templates,
            'attachments'    => $attachments,
            'priorStudies'   => $priorStudies,
            'canSignReport'  => user_can_sign_report($user, $study),
            'assignedDoctor' => $assignedDoctor,
            'aiEnabled'      => (new AiAssistantService())->isAvailable(),
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Guarda o finaliza el informe médico y notifica al paciente si procede.  //
    /////////////////////////////////////////////////////////////////////////////
    public function save(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso para guardar informes.');
        }

        $localStudy = model(StudyModel::class)->findByStudyUid($studyUid);
        if (user_center_id($user) !== null && ! user_can_access_study($user, $localStudy)) {
            return redirect()->to('/reports')->with('error', 'Sin permiso para guardar informes de otro centro.');
        }

        $rules = [
            'clinical_history' => [
                'label' => 'Antecedentes clínicos',
                'rules' => 'permit_empty|max_length[5000]',
            ],
            'findings' => [
                'label' => 'Hallazgos',
                'rules' => 'required|min_length[5]|max_length[20000]',
            ],
            'impression' => [
                'label' => 'Conclusión',
                'rules' => 'permit_empty|max_length[10000]',
            ],
            'action_type' => [
                'label' => 'Acción',
                'rules' => 'required|in_list[draft,publish]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data            = $this->validator->getValidated();
        $clinicalHistory = sanitize_report_html($data['clinical_history'] ?? '');
        $findings        = sanitize_report_html($data['findings']);
        $impression      = sanitize_report_html($data['impression']);

        if (mb_strlen(report_plain_text($findings)) < 5) {
            return redirect()->back()->withInput()->with('errors', ['findings' => 'El campo Hallazgos debe tener al menos 5 caracteres.']);
        }
        $impressionPlain = report_plain_text($impression);
        if ($impressionPlain !== '' && mb_strlen($impressionPlain) < 3) {
            return redirect()->back()->withInput()->with('errors', ['impression' => 'La Conclusión debe tener al menos 3 caracteres.']);
        }

        $isPublish   = $data['action_type'] === 'publish';
        $status      = $isPublish ? 'reported' : 'in_review';
        $reportModel = model(MedicalReportModel::class);
        $existing    = $reportModel->findByStudyUid($studyUid);

        if (! user_can_modify_report($user, $existing, $localStudy)) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Este informe ya fue firmado y publicado; solo el médico firmante puede modificarlo.');
        }

        if ($isPublish && ! user_can_sign_report($user, $localStudy)) {
            return redirect()->back()->withInput()
                ->with('error', 'Solo el médico asignado (con rol Médico) puede firmar y publicar el informe. Puedes guardarlo como borrador.');
        }

        $payload = [
            'study_instance_uid' => $studyUid,
            'doctor_user_id'     => $isPublish ? (int) auth()->id() : null,
            'created_by_user_id' => $existing['created_by_user_id'] ?? (int) auth()->id(),
            'updated_by_user_id' => (int) auth()->id(),
            'status'             => $status,
            'clinical_history'   => report_plain_text($clinicalHistory) !== '' ? $clinicalHistory : '',
            'findings'           => $findings,
            'impression'         => $impression,
            'reported_at'        => $isPublish ? date('Y-m-d H:i:s') : ($existing['reported_at'] ?? null),
        ];

        if ($existing !== null) {
            $reportModel->update((int) $existing['id'], $payload);
        } else {
            $reportModel->insert($payload);
        }

        AuditLogModel::record(
            $isPublish ? 'report.published' : 'report.saved',
            $studyUid,
            null,
            $isPublish ? 'Informe médico firmado y publicado' : 'Borrador guardado (pendiente de firma médica)',
            $this->request
        );

        if ($isPublish) {
            $study = model(StudyModel::class)->findByStudyUid($studyUid);
            (new NotificationService())->sendReportPublishedEmail($studyUid, $study, $payload);
        }

        $msg    = $isPublish ? 'Informe publicado.' : 'Borrador guardado.';
        $target = $isPublish ? '/reports' : '/reports/edit/' . urlencode($studyUid);

        return redirect()->to($target)->with('message', $msg);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Genera y descarga el informe médico en formato PDF con membrete.        //
    /////////////////////////////////////////////////////////////////////////////
    public function pdf(string $studyUid): ResponseInterface|RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $study  = model(StudyModel::class)->findByStudyUid($studyUid);
        $report = model(MedicalReportModel::class)->findByStudyUid($studyUid);
        if (! user_can_modify_report($user, $report, $study)) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Este informe ya fue firmado y publicado; solo el médico firmante puede modificarlo.');
        }

        $canReview = $user->can('reports.review');
        if (! user_can_access_study($user, $study)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para descargar.');
        }

        if ($report === null || (! $canReview && $report['status'] !== 'reported')) {
            return redirect()->to('/studies')->with('error', 'Informe aún no publicado.');
        }

        $pdfBytes = (new ReportPdfService())->renderBytes($studyUid, $study, $report);

        AuditLogModel::record('report.pdf_downloaded', $studyUid, null, 'Descarga de informe PDF', $this->request);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="informe-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $studyUid) . '.pdf"')
            ->setBody($pdfBytes);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Elimina un informe médico (solo administradores).                       //
    /////////////////////////////////////////////////////////////////////////////
    public function delete(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->to('/login');
        }

        $reportModel = model(MedicalReportModel::class);
        $report      = $reportModel->findByStudyUid($studyUid);
        if ($report === null) {
            return redirect()->to('/reports')->with('error', 'Informe no encontrado.');
        }

        $isAdmin  = $user->can('users.manage');
        $isAuthor = $user->can('reports.review') && (int) ($report['doctor_user_id'] ?? 0) === (int) $user->id;
        $study    = model(StudyModel::class)->findByStudyUid($studyUid);
        if ((! $isAdmin && ! $isAuthor) || ! user_can_access_study($user, $study)) {
            return redirect()->to('/reports')->with('error', 'Sin permiso para eliminar este informe.');
        }

        $reportModel->delete((int) $report['id']);

        AuditLogModel::record(
            'report.deleted',
            $studyUid,
            null,
            'Informe médico eliminado',
            $this->request
        );

        $target = $this->request->getPost('return_to') === 'case'
            ? '/studies/case/' . urlencode($studyUid)
            : '/reports';

        return redirect()->to($target)->with('message', 'Informe eliminado.');
    }

    /////////////////////////////////////////////////////////////////////////////
    // Transcribe audio de dictado médico usando el servicio Whisper local.    //
    /////////////////////////////////////////////////////////////////////////////
    public function transcribe(): ResponseInterface
    {
        if (! auth()->user()?->can('reports.review')) {
            return $this->response->setStatusCode(403)->setJSON([
                'error'     => 'Sin permiso para transcribir informes.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $file = $this->request->getFile('audio');
        if ($file === null || ! $file->isValid() || $file->getSize() <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                'error'     => 'No se recibió un archivo de audio válido.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        if ($file->getSize() > 15 * 1024 * 1024) {
            return $this->response->setStatusCode(413)->setJSON([
                'error'     => 'El audio supera el límite de 15 MB.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $whisperBase = rtrim((string) (env('WHISPER_URL') ?: 'http://whisper:8000'), '/');
        $mimeType    = $file->getMimeType() ?: 'audio/webm';
        $origName    = $file->getClientName() ?: 'dictado.webm';

        $ch = curl_init($whisperBase . '/transcribe');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_POSTFIELDS     => [
                'file'     => new \CURLFile($file->getTempName(), $mimeType, $origName),
                'language' => 'es',
            ],
        ]);

        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
            $detail = $curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode);
            return $this->response->setStatusCode(502)->setJSON([
                'error'     => 'No se pudo transcribir con Whisper local (' . $detail . ').',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        $decoded = json_decode($raw, true);
        $text    = is_array($decoded) ? trim((string) ($decoded['text'] ?? '')) : '';

        return $this->response->setJSON([
            'text'      => $text,
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Asistente IA: genera conclusión, ordena el informe o resume al paciente.//
    /////////////////////////////////////////////////////////////////////////////
    public function aiAssist(): ResponseInterface
    {
        if (! auth()->user()?->can('reports.review')) {
            return $this->response->setStatusCode(403)->setJSON([
                'error'     => 'Sin permiso para usar el asistente de IA.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        @set_time_limit(130);

        $action = trim((string) $this->request->getPost('action'));
        $res    = (new AiAssistantService())->assist($action, [
            'modality'          => (string) $this->request->getPost('modality'),
            'study_description' => (string) $this->request->getPost('study_description'),
            'clinical_history'  => (string) $this->request->getPost('clinical_history'),
            'findings'          => (string) $this->request->getPost('findings'),
        ]);

        if (! $res['ok']) {
            return $this->response->setStatusCode(422)->setJSON([
                'error'     => $res['error'] ?? 'No se pudo procesar la solicitud de IA.',
                'csrf_hash' => csrf_hash(),
            ]);
        }

        return $this->response->setJSON([
            'text'      => $res['text'] ?? '',
            'csrf_hash' => csrf_hash(),
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Importa texto de un PDF (DICOM encapsulado o adjunto) al borrador.      //
    /////////////////////////////////////////////////////////////////////////////
    public function importPdf(string $studyUid): RedirectResponse
    {
        $user = auth()->user();
        if (! $user?->can('reports.review')) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Sin permiso para importar informes.');
        }

        $study          = model(StudyModel::class)->findByStudyUid($studyUid);
        $scopedCenterId = user_center_id($user);
        if ($scopedCenterId !== null && ! user_can_access_study($user, $study)) {
            return redirect()->to('/studies')->with('error', 'Sin permiso para acceder a este estudio.');
        }

        $reportModel = model(MedicalReportModel::class);
        $existing    = $reportModel->findByStudyUid($studyUid);
        if (! user_can_modify_report($user, $existing, $study)) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Este informe ya fue firmado y publicado; solo el médico firmante puede modificarlo.');
        }

        $source   = trim((string) ($this->request->getPost('source') ?? ''));
        $pdfBytes = null;
        $docLabel = 'PDF';

        if ($source === 'dicom') {
            $instanceId = trim((string) ($this->request->getPost('instance_id') ?? ''));
            if ($instanceId === '') {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'Documento DICOM no especificado.');
            }

            $orthanc   = new OrthancClient();
            $studyInfo = ! empty($study['orthanc_id']) ? $orthanc->getStudy((string) $study['orthanc_id']) : null;
            if ($studyInfo === null) {
                $studyInfo = $orthanc->findStudyByUid($studyUid);
            }
            if ($studyInfo === null) {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'Estudio no encontrado en Orthanc.');
            }

            $validDoc = null;
            foreach ($orthanc->findStudyDicomDocuments($studyInfo) as $doc) {
                if ((string) ($doc['instance_id'] ?? '') === $instanceId) {
                    $validDoc = $doc;
                    break;
                }
            }
            if ($validDoc === null) {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'El PDF indicado no pertenece a este estudio.');
            }

            $pdfBytes = $orthanc->getInstancePdf($instanceId);
            $docLabel = (string) ($validDoc['title'] ?? 'Informe_DICOM.pdf');
        } elseif ($source === 'attachment') {
            $attId = (int) ($this->request->getPost('attachment_id') ?? 0);
            $att   = $attId > 0 ? model(StudyAttachmentModel::class)->find($attId) : null;
            if ($att === null || (string) ($att['study_instance_uid'] ?? '') !== $studyUid) {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'Adjunto PDF no encontrado en este estudio.');
            }

            $ext  = strtolower((string) pathinfo((string) ($att['original_name'] ?? ''), PATHINFO_EXTENSION));
            $mime = strtolower((string) ($att['mime_type'] ?? ''));
            if ($ext !== 'pdf' && $mime !== 'application/pdf') {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'Solo se pueden importar archivos PDF.');
            }

            $path = WRITEPATH . 'uploads/attachments/' . basename((string) $att['stored_name']);
            if (! is_file($path)) {
                return redirect()->to('/studies/case/' . urlencode($studyUid))
                    ->with('error', 'El archivo PDF no está disponible en disco.');
            }

            $pdfBytes = @file_get_contents($path) ?: null;
            $docLabel = (string) ($att['original_name'] ?? 'Adjunto.pdf');
        } else {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'Origen de importación no válido.');
        }

        if ($pdfBytes === null || $pdfBytes === '') {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'No se pudo leer el contenido del archivo PDF.');
        }

        try {
            $parser  = new \Smalot\PdfParser\Parser();
            $pdf     = $parser->parseContent($pdfBytes);
            $rawText = trim((string) $pdf->getText());
        } catch (\Throwable $e) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'No se pudo extraer texto del PDF (' . $e->getMessage() . ').');
        }

        $rawText = str_replace(["\r\n", "\r"], "\n", $rawText);
        $rawText = (string) preg_replace("/\n{3,}/", "\n\n", $rawText);
        $lines   = array_values(array_filter(array_map('trim', explode("\n", $rawText)), static fn (string $l): bool => $l !== ''));

        if ($lines === []) {
            return redirect()->to('/studies/case/' . urlencode($studyUid))
                ->with('error', 'El PDF no contiene texto seleccionable (puede ser un documento escaneado como imagen).');
        }

        $htmlBlocks = array_map(
            static fn (string $line): string => '<p>' . esc($line) . '</p>',
            $lines
        );
        $findingsHtml = implode('', $htmlBlocks);

        $payload = [
            'study_instance_uid' => $studyUid,
            'doctor_user_id'     => $existing['doctor_user_id'] ?? null,
            'created_by_user_id' => $existing['created_by_user_id'] ?? (int) auth()->id(),
            'updated_by_user_id' => (int) auth()->id(),
            'status'             => ($existing !== null && ($existing['status'] ?? '') === 'reported') ? 'reported' : 'in_review',
            'clinical_history'   => $existing['clinical_history'] ?? ($study['clinical_notes'] ?? ''),
            'findings'           => $findingsHtml,
            'impression'         => '',
            'reported_at'        => $existing['reported_at'] ?? null,
        ];

        if ($existing !== null) {
            $reportModel->update((int) $existing['id'], $payload);
        } else {
            $reportModel->insert($payload);
        }

        AuditLogModel::record(
            'report.imported_pdf',
            $studyUid,
            null,
            'Texto importado al informe desde PDF: ' . $docLabel,
            $this->request
        );

        return redirect()->to('/reports/edit/' . urlencode($studyUid))
            ->with('message', 'Texto importado desde "' . $docLabel . '". Revísalo antes de guardar o publicar.');
    }
}
