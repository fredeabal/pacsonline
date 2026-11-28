<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\OrthancClient;
use App\Libraries\PriorStudiesService;
use App\Models\MedicalReportModel;
use App\Models\StudyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\HTTP\QueuedMockCURLRequest;

final class PriorStudiesServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testFindForStudyExcludesCurrentRespectsCenterAndPermissions(): void
    {
        $currentUid  = '1.2.826.0.1.3680043.10.1';
        $priorC1Uid  = '1.2.826.0.1.3680043.10.2';
        $priorC2Uid  = '1.2.826.0.1.3680043.10.3';

        model(StudyModel::class)->insert([
            'orthanc_id'         => 'o-10-2',
            'study_instance_uid' => $priorC1Uid,
            'center_id'          => 1,
            'modality'           => 'MR',
            'patient_name'       => 'Godigna Victor',
            'study_description'  => 'rodilla derecha',
            'study_date'         => '20240517',
        ]);

        model(StudyModel::class)->insert([
            'orthanc_id'         => 'o-10-3',
            'study_instance_uid' => $priorC2Uid,
            'center_id'          => 2,
            'modality'           => 'CT',
            'patient_name'       => 'Godigna Victor',
            'study_description'  => 'torax',
            'study_date'         => '20230110',
        ]);

        model(MedicalReportModel::class)->insert([
            'study_instance_uid' => $priorC1Uid,
            'doctor_user_id'     => 1,
            'status'             => 'reported',
            'findings'           => 'Menisco íntegro.',
            'impression'         => 'Sin lesiones agudas.',
            'reported_at'        => '2024-05-18 10:00:00',
        ]);

        model(MedicalReportModel::class)->insert([
            'study_instance_uid' => $priorC2Uid,
            'doctor_user_id'     => 1,
            'status'             => 'in_review',
            'findings'           => 'Borrador interno.',
            'impression'         => 'Pendiente.',
        ]);

        $orthancPayload = [
            [
                'ID'                   => 'o-10-1',
                'PatientMainDicomTags' => ['PatientID' => '3189530', 'PatientName' => 'GODIGNA VICTOR'],
                'MainDicomTags'        => ['StudyInstanceUID' => $currentUid, 'StudyDate' => '20240705', 'StudyDescription' => 'TOBILLO'],
                'RequestedTags'        => ['ModalitiesInStudy' => 'MR'],
                'Series'               => ['s1', 's2'],
            ],
            [
                'ID'                   => 'o-10-2',
                'PatientMainDicomTags' => ['PatientID' => '3189530', 'PatientName' => 'Godigna Victor'],
                'MainDicomTags'        => ['StudyInstanceUID' => $priorC1Uid, 'StudyDate' => '20240517', 'StudyDescription' => 'rodilla derecha'],
                'RequestedTags'        => ['ModalitiesInStudy' => 'DOC\\MR\\SR'],
                'Series'               => ['s3'],
            ],
            [
                'ID'                   => 'o-10-3',
                'PatientMainDicomTags' => ['PatientID' => '3189530', 'PatientName' => 'GODIGNA VICTOR'],
                'MainDicomTags'        => ['StudyInstanceUID' => $priorC2Uid, 'StudyDate' => '20230110', 'StudyDescription' => 'torax'],
                'RequestedTags'        => ['ModalitiesInStudy' => 'CT'],
                'Series'               => ['s4', 's5'],
            ],
        ];

        // 1. Usuario global (sin centro asignado) sin permiso reports.review: solo ve informe publicado
        $mockGlobal = (new QueuedMockCURLRequest())->queueJson(200, $orthancPayload);
        $service = new PriorStudiesService();
        $globalPriors = $service->findForStudy(
            new OrthancClient($mockGlobal, 'http://orthanc.test:8042'),
            '3189530',
            $currentUid,
            true,
            false,
            99,
            15,
            null
        );

        $this->assertCount(2, $globalPriors);
        $this->assertSame($priorC1Uid, $globalPriors[0]['uid']);
        $this->assertSame('MR', $globalPriors[0]['modality']);
        $this->assertTrue($globalPriors[0]['can_view_report']);
        $this->assertSame('Sin lesiones agudas.', $globalPriors[0]['report']['impression']);

        $this->assertSame($priorC2Uid, $globalPriors[1]['uid']);
        $this->assertFalse($globalPriors[1]['can_view_report']);
        $this->assertNull($globalPriors[1]['report']);

        // 2. Usuario restringido al centro 1: solo ve el estudio del centro 1
        $mockScoped = (new QueuedMockCURLRequest())->queueJson(200, $orthancPayload);
        $scopedPriors = $service->findForStudy(
            new OrthancClient($mockScoped, 'http://orthanc.test:8042'),
            '3189530',
            $currentUid,
            false,
            false,
            99,
            15,
            1
        );

        $this->assertCount(1, $scopedPriors);
        $this->assertSame($priorC1Uid, $scopedPriors[0]['uid']);
    }

    public function testOhifCompareUrlBuildsCommaSeparatedUids(): void
    {
        $url = ohif_compare_url(['1.2.3', '4.5.6', '1.2.3', '']);
        $this->assertSame('/viewer/viewer?StudyInstanceUIDs=1.2.3,4.5.6&lng=es', $url);
    }
}
