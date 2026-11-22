<?php

declare(strict_types=1);

namespace App\Controllers;

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

        $query = [];
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

        $fetched    = $orthanc->findStudiesPage($offset, $perPage + 1, $query);
        $hasNext    = count($fetched) > $perPage;
        $studies    = $hasNext ? array_slice($fetched, 0, $perPage) : $fetched;
        $totalCount = ($query === [] && $scope === 'all' && $filter === 'all') ? $orthanc->countStudies() : null;

        $uids = [];
        foreach ($studies as $study) {
            $uid = $study['MainDicomTags']['StudyInstanceUID'] ?? '';
            if ($uid !== '') {
                $uids[] = $uid;
            }
        }

        $recordsByUid = $studyModel->mapByStudyUids($uids);
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
            if ($scope === 'mine' && (int) ($record['assigned_doctor_id'] ?? 0) !== $userId) {
                continue;
            }

            $report = $reportsByUid[$uid] ?? null;
            $status = $report['status'] ?? 'pending';

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
            'title'               => 'Informar Estudios | PACS Online',
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
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso para informar.');
        }

        $study  = model(StudyModel::class)->findByStudyUid($studyUid);
        $report = model(MedicalReportModel::class)->findByStudyUid($studyUid);
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
        $templates   = $tplModel->forUser((int) auth()->id());
        $attachments = model(StudyAttachmentModel::class)->forStudy($studyUid);

        return view('reports/edit', [
            'title'       => 'Redactar Informe Médico | PACS Online',
            'studyUid'    => $studyUid,
            'study'       => $study,
            'report'      => $report,
            'templates'   => $templates,
            'attachments' => $attachments,
        ]);
    }

    /////////////////////////////////////////////////////////////////////////////
    // Guarda o finaliza el informe médico y notifica al paciente si procede.  //
    /////////////////////////////////////////////////////////////////////////////
    public function save(string $studyUid): RedirectResponse
    {
        if (! auth()->user()?->can('reports.review')) {
            return redirect()->to('/')->with('error', 'Sin permiso para guardar informes.');
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
                'rules' => 'required|min_length[3]|max_length[10000]',
            ],
            'action_type' => [
                'label' => 'Acción',
                'rules' => 'required|in_list[draft,publish]',
            ],
        ];

        if (! $this->validateData($this->request->getPost(), $rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data        = $this->validator->getValidated();
        $isPublish   = $data['action_type'] === 'publish';
        $status      = $isPublish ? 'reported' : 'in_review';
        $reportModel = model(MedicalReportModel::class);
        $existing    = $reportModel->findByStudyUid($studyUid);

        $payload = [
            'study_instance_uid' => $studyUid,
            'doctor_user_id'     => auth()->id(),
            'status'             => $status,
            'clinical_history'   => $data['clinical_history'],
            'findings'           => $data['findings'],
            'impression'         => $data['impression'],
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
            $isPublish ? 'Informe médico publicado' : 'Borrador guardado',
            $this->request
        );

        if ($isPublish) {
            $study = model(StudyModel::class)->findByStudyUid($studyUid);
            (new NotificationService())->sendReportPublishedEmail($studyUid, $study, $payload);
        }

        $msg = $isPublish ? 'Informe publicado.' : 'Borrador guardado.';

        return redirect()->to('/reports')->with('message', $msg);
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

        $canReview  = $user->can('reports.review');
        $canViewAll = $user->can('studies.view_all');
        $isOwner    = $study !== null && (int) $study['owner_user_id'] === (int) $user->id;

        if (! $canReview && ! $canViewAll && ! $isOwner) {
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
        if (! $isAdmin && ! $isAuthor) {
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
}
