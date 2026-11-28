<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DicomAuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testUnauthenticatedDicomRequestReturns401(): void
    {
        $result = $this->withHeaders([
            'X-Original-URI' => '/dicom-web/studies',
        ])->get('_dicom_auth');

        $result->assertStatus(401);
    }

    public function testPatientAccessSessionAllowsOnlyMatchingStudyUid(): void
    {
        $allowedUid = '1.2.826.0.1.3680043.99.1';
        $otherUid   = '1.2.826.0.1.3680043.99.2';

        $linkId = model(PatientAccessLinkModel::class)->insert([
            'study_instance_uid'      => $allowedUid,
            'created_by_user_id'      => 1,
            'token'                   => 'tok_patient_99',
            'patient_id_hash'         => password_hash('12345678A', PASSWORD_DEFAULT),
            'patient_birth_date_hash' => password_hash('1990-05-15', PASSWORD_DEFAULT),
            'expires_at'              => date('Y-m-d H:i:s', strtotime('+30 days')),
        ], true);

        $sessionData = [
            'patient_access' => [
                'link_id'     => (int) $linkId,
                'study_uid'   => $allowedUid,
                'unlocked_at' => time(),
            ],
        ];

        $okResult = $this->withSession($sessionData)
            ->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$allowedUid}/metadata"])
            ->get('_dicom_auth');
        $okResult->assertStatus(200);

        $forbiddenResult = $this->withSession($sessionData)
            ->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$otherUid}/metadata"])
            ->get('_dicom_auth');
        $forbiddenResult->assertStatus(403);

        model(PatientAccessLinkModel::class)->update((int) $linkId, ['revoked_at' => date('Y-m-d H:i:s')]);

        $revokedResult = $this->withSession($sessionData)
            ->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$allowedUid}/metadata"])
            ->get('_dicom_auth');
        $revokedResult->assertStatus(401);
    }

    public function testCenterScopedUserCannotQueryAllStudiesAndOnlyViewsOwnCenter(): void
    {
        $centerId1 = (int) model(\App\Models\MedicalCenterModel::class)->insert([
            'name'           => 'Clinica Centro 1',
            'ae_title'       => 'CENTRO1',
            'api_token_hash' => hash('sha256', 'tok_c1'),
            'active'         => 1,
        ], true);

        $centerId2 = (int) model(\App\Models\MedicalCenterModel::class)->insert([
            'name'           => 'Clinica Centro 2',
            'ae_title'       => 'CENTRO2',
            'api_token_hash' => hash('sha256', 'tok_c2'),
            'active'         => 1,
        ], true);

        $users      = auth()->getProvider();
        $scopedUser = new User([
            'username'  => 'obs_centro_test',
            'email'     => 'obs_centro_test@example.com',
            'password'  => 'Secret123!',
            'center_id' => $centerId1,
        ]);
        $users->save($scopedUser);
        $saved = $users->findById($users->getInsertID());
        $saved->activate();
        $saved->addGroup('observador');

        $ownUid   = '1.2.826.0.1.3680043.77.1';
        $otherUid = '1.2.826.0.1.3680043.77.2';

        model(StudyModel::class)->insert([
            'orthanc_id'         => 'orthanc-77-1',
            'study_instance_uid' => $ownUid,
            'center_id'          => $centerId1,
            'patient_name'       => 'Paciente Centro 1',
        ]);

        model(StudyModel::class)->insert([
            'orthanc_id'         => 'orthanc-77-2',
            'study_instance_uid' => $otherUid,
            'center_id'          => $centerId2,
            'patient_name'       => 'Paciente Centro 2',
        ]);

        auth()->login($saved);

        try {
            $listResult = $this->withHeaders(['X-Original-URI' => '/dicom-web/studies'])
                ->get('_dicom_auth');
            $listResult->assertStatus(403);

            $ownResult = $this->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$ownUid}/metadata"])
                ->get('_dicom_auth');
            $ownResult->assertStatus(200);

            $otherResult = $this->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$otherUid}/metadata"])
                ->get('_dicom_auth');
            $otherResult->assertStatus(403);
        } finally {
            auth()->logout();
        }
    }
}
