<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Libraries\OrthancClient;
use App\Models\MedicalCenterModel;
use App\Models\StudyModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\HTTP\QueuedMockCURLRequest;

final class CenterScopingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testUserCenterAccessHelpersEnforceCenterIsolation(): void
    {
        $centerId = (int) model(MedicalCenterModel::class)->insert([
            'name'           => 'Clinica Norte',
            'ae_title'       => 'CNORTE',
            'api_token_hash' => hash('sha256', 'tok_cnorte'),
            'active'         => 1,
        ], true);

        $users = auth()->getProvider();
        $user  = new User([
            'username'  => 'usr_cnorte',
            'email'     => 'usr_cnorte@example.com',
            'password'  => 'Secret123!',
            'center_id' => $centerId,
        ]);
        $users->save($user);
        $saved = $users->findById($users->getInsertID());
        $saved->activate();
        $saved->addGroup('usuario');

        $this->assertSame($centerId, user_center_id($saved));
        $this->assertTrue(user_can_access_study($saved, ['center_id' => $centerId]));
        $this->assertFalse(user_can_access_study($saved, ['center_id' => $centerId + 1]));
        $this->assertFalse(user_can_access_study($saved, null));
    }

    public function testResolveAndPersistModalitiesSkipsNonImageAndPersistsRealModality(): void
    {
        $studyModel = model(StudyModel::class);
        $mrUid      = '1.2.826.0.1.3680043.88.1';
        $srUid      = '1.2.826.0.1.3680043.88.2';

        $studies = [
            [
                'ID'                   => 'orthanc-mr-88',
                'PatientMainDicomTags' => ['PatientName' => 'Godigna Victor'],
                'MainDicomTags'        => ['StudyInstanceUID' => $mrUid, 'StudyDescription' => 'rodilla derecha', 'StudyDate' => '20240705'],
                'RequestedTags'        => ['ModalitiesInStudy' => 'DOC\\MR\\SR'],
            ],
            [
                'ID'                   => 'orthanc-sr-88',
                'PatientMainDicomTags' => ['PatientName' => 'Godigna Victor'],
                'MainDicomTags'        => ['StudyInstanceUID' => $srUid, 'StudyDescription' => 'Prostata', 'StudyDate' => ''],
                'RequestedTags'        => ['ModalitiesInStudy' => 'SR'],
            ],
        ];

        $recordsByUid = [];
        $orthanc      = new OrthancClient(new QueuedMockCURLRequest(), 'http://orthanc.test:8042');
        $resolved     = $studyModel->resolveAndPersistModalities($orthanc, $studies, $recordsByUid);

        $this->assertSame('MR', $resolved[0]['modality']);
        $mrRecord = $studyModel->findByStudyUid($mrUid);
        $this->assertNotNull($mrRecord);
        $this->assertSame('MR', $mrRecord['modality']);

        // Un estudio solo SR (posiblemente en transferencia) no se fija de forma permanente en BD
        $this->assertNull($studyModel->findByStudyUid($srUid));
    }

    public function testFormatHelpersForceUppercaseUniformly(): void
    {
        $this->assertSame('GODIGNA VICTOR', format_patient_name('Godigna^Victor'));
        $this->assertSame('PROSTATA CON CONTRASTE', format_study_description('Prostata con contraste'));
    }

    public function testExistingUnassignedStudyGetsCenterIdWhenIngestedFromGateway(): void
    {
        $plainToken = 'tok_gateway_assign_' . bin2hex(random_bytes(4));
        $centerId   = (int) model(MedicalCenterModel::class)->insert([
            'name'           => 'MacBookPro',
            'ae_title'       => 'MBP',
            'api_token_hash' => hash('sha256', $plainToken),
            'active'         => 1,
            'can_receive'    => 1,
        ], true);

        $uid = '1.2.826.0.1.3680043.55.1';
        model(StudyModel::class)->insert([
            'orthanc_id'         => 'orthanc-55-1',
            'study_instance_uid' => $uid,
            'center_id'          => null,
            'patient_name'       => 'Paciente Historico',
        ]);

        $mock = (new QueuedMockCURLRequest())
            ->queueJson(200, ['ID' => 'inst-55', 'ParentStudy' => 'orthanc-55-1'])
            ->queueJson(200, [
                'ID'                   => 'orthanc-55-1',
                'PatientMainDicomTags' => ['PatientName' => 'Paciente Historico'],
                'MainDicomTags'        => ['StudyInstanceUID' => $uid, 'StudyDescription' => 'ROD', 'StudyDate' => '20240505'],
                'RequestedTags'        => ['ModalitiesInStudy' => 'MR'],
            ]);

        $controller = new class ($mock) extends \App\Controllers\Api\IngestController {
            public function __construct(private readonly QueuedMockCURLRequest $mockClient)
            {
            }

            protected function createOrthancClient(): OrthancClient
            {
                return new OrthancClient($this->mockClient, 'http://orthanc.test:8042');
            }
        };
        $request = \Config\Services::incomingrequest();
        $request->setMethod('POST')
            ->setHeader('Authorization', 'Bearer ' . $plainToken)
            ->setBody('DICOM_BINARY_PAYLOAD');
        $controller->initController($request, \Config\Services::response(), \Config\Services::logger());

        $response = $controller->dicom();
        $this->assertSame(200, $response->getStatusCode());

        $updated = model(StudyModel::class)->findByStudyUid($uid);
        $this->assertNotNull($updated);
        $this->assertSame($centerId, (int) $updated['center_id']);
        $this->assertSame('MR', $updated['modality']);
    }
}
