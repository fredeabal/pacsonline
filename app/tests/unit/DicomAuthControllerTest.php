<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\StudyModel;
use App\Models\StudyShareModel;
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

    public function testGuestShareSessionAllowsOnlyMatchingStudyUid(): void
    {
        $allowedUid = '1.2.826.0.1.3680043.99.1';
        $otherUid   = '1.2.826.0.1.3680043.99.2';

        $shareId = model(StudyShareModel::class)->insert([
            'study_instance_uid' => $allowedUid,
            'created_by_user_id' => 1,
            'token'              => 'tok_guest_99',
            'expires_at'         => date('Y-m-d H:i:s', strtotime('+1 day')),
        ], true);

        $sessionData = [
            'share_access' => [
                'share_id'  => (int) $shareId,
                'study_uid' => $allowedUid,
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

        model(StudyShareModel::class)->update((int) $shareId, ['revoked_at' => date('Y-m-d H:i:s')]);

        $revokedResult = $this->withSession($sessionData)
            ->withHeaders(['X-Original-URI' => "/dicom-web/studies/{$allowedUid}/metadata"])
            ->get('_dicom_auth');
        $revokedResult->assertStatus(401);
    }

    public function testPatientCannotQueryAllStudiesWithoutUid(): void
    {
        $users   = auth()->getProvider();
        $patient = new User([
            'username' => 'pac_dicom_test',
            'email'    => 'pac_dicom_test@example.com',
            'password' => 'Secret123!',
        ]);
        $users->save($patient);
        $saved = $users->findById($users->getInsertID());
        $saved->activate();
        $saved->addGroup('paciente');

        $ownUid   = '1.2.826.0.1.3680043.77.1';
        $otherUid = '1.2.826.0.1.3680043.77.2';

        model(StudyModel::class)->insert([
            'orthanc_id'         => 'orthanc-77-1',
            'study_instance_uid' => $ownUid,
            'owner_user_id'      => (int) $saved->id,
            'patient_name'       => 'Paciente Test',
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
