<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\AuditLogModel;
use App\Models\MedicalReportModel;
use App\Models\PatientAccessLinkModel;
use App\Models\StudyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class StudyAndShareModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testStudyModelFindByUid(): void
    {
        $model = model(StudyModel::class);
        $uid   = '1.2.826.0.1.3680043.10.1';

        $model->insert([
            'orthanc_id'         => 'orthanc-abc',
            'study_instance_uid' => $uid,
            'owner_user_id'      => 5,
            'patient_name'       => 'Juan Perez',
            'study_description'  => 'TC Torax',
            'study_date'         => '20261116',
        ]);

        $found = $model->findByStudyUid($uid);
        $this->assertNotNull($found);
        $this->assertSame('Juan Perez', $found['patient_name']);
        $this->assertSame(5, (int) $found['owner_user_id']);
    }

    public function testPatientAccessLinkLifecycleActiveExpiredAndRevoked(): void
    {
        $model = model(PatientAccessLinkModel::class);
        $uid   = '1.2.826.0.1.3680043.10.2';

        $activeId = $model->insert([
            'study_instance_uid'      => $uid,
            'created_by_user_id'      => 1,
            'token'                   => 'tok_active_123',
            'patient_id_hash'         => password_hash('12345678A', PASSWORD_DEFAULT),
            'patient_birth_date_hash' => password_hash('1990-05-15', PASSWORD_DEFAULT),
            'expires_at'              => date('Y-m-d H:i:s', strtotime('+30 days')),
        ], true);

        $this->assertNotNull($model->findActiveByToken('tok_active_123'));
        $this->assertTrue($model->isStillActive((int) $activeId));

        $expiredId = $model->insert([
            'study_instance_uid'      => $uid,
            'created_by_user_id'      => 1,
            'token'                   => 'tok_expired_123',
            'patient_id_hash'         => password_hash('12345678A', PASSWORD_DEFAULT),
            'patient_birth_date_hash' => password_hash('1990-05-15', PASSWORD_DEFAULT),
            'expires_at'              => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ], true);

        $this->assertNull($model->findActiveByToken('tok_expired_123'));
        $this->assertFalse($model->isStillActive((int) $expiredId));

        $model->update((int) $activeId, ['revoked_at' => date('Y-m-d H:i:s')]);
        $this->assertNull($model->findActiveByToken('tok_active_123'));
        $this->assertFalse($model->isStillActive((int) $activeId));
    }

    public function testMedicalReportAndAuditLog(): void
    {
        $reportModel = model(MedicalReportModel::class);
        $uid         = '1.2.826.0.1.3680043.10.3';

        $reportModel->insert([
            'study_instance_uid' => $uid,
            'doctor_user_id'     => 2,
            'status'             => 'reported',
            'findings'           => 'Sin hallazgos agudos.',
            'impression'         => 'Estudio normal.',
            'reported_at'        => date('Y-m-d H:i:s'),
        ]);

        $report = $reportModel->findByStudyUid($uid);
        $this->assertNotNull($report);
        $this->assertSame('reported', $report['status']);

        AuditLogModel::record('report.published', $uid, null, 'Publicado en test');
        $log = model(AuditLogModel::class)->where('study_instance_uid', $uid)->first();
        $this->assertNotNull($log);
        $this->assertSame('report.published', $log['action']);
    }

    public function testBatchMapAndPatientLinkCounts(): void
    {
        $uid1 = '1.2.826.0.1.3680043.20.1';
        $uid2 = '1.2.826.0.1.3680043.20.2';

        $studyModel       = model(StudyModel::class);
        $reportModel      = model(MedicalReportModel::class);
        $patientLinkModel = model(PatientAccessLinkModel::class);

        $studyModel->insert(['orthanc_id' => 'o1', 'study_instance_uid' => $uid1, 'patient_name' => 'Uno']);
        $studyModel->insert(['orthanc_id' => 'o2', 'study_instance_uid' => $uid2, 'patient_name' => 'Dos']);

        $reportModel->insert([
            'study_instance_uid' => $uid1,
            'doctor_user_id'     => 2,
            'status'             => 'reported',
            'findings'           => 'Normal',
            'impression'         => 'Normal',
        ]);

        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        $idHash  = password_hash('12345678A', PASSWORD_DEFAULT);
        $dobHash = password_hash('1990-05-15', PASSWORD_DEFAULT);

        $patientLinkModel->insert(['study_instance_uid' => $uid1, 'created_by_user_id' => 1, 'token' => 't1', 'patient_id_hash' => $idHash, 'patient_birth_date_hash' => $dobHash, 'expires_at' => $expires]);
        $patientLinkModel->insert(['study_instance_uid' => $uid1, 'created_by_user_id' => 1, 'token' => 't2', 'patient_id_hash' => $idHash, 'patient_birth_date_hash' => $dobHash, 'expires_at' => $expires]);
        $patientLinkModel->insert([
            'study_instance_uid'      => $uid2,
            'created_by_user_id'      => 1,
            'token'                   => 't3',
            'patient_id_hash'         => $idHash,
            'patient_birth_date_hash' => $dobHash,
            'expires_at'              => $expires,
            'revoked_at'              => date('Y-m-d H:i:s'),
        ]);

        $studiesMap = $studyModel->mapByStudyUids([$uid1, $uid2]);
        $this->assertCount(2, $studiesMap);
        $this->assertSame('Uno', $studiesMap[$uid1]['patient_name']);

        $reportsMap = $reportModel->mapByStudyUids([$uid1, $uid2]);
        $this->assertArrayHasKey($uid1, $reportsMap);
        $this->assertArrayNotHasKey($uid2, $reportsMap);

        $linkCounts = $patientLinkModel->countActiveByStudyUids([$uid1, $uid2]);
        $this->assertSame(2, $linkCounts[$uid1] ?? 0);
        $this->assertSame(0, $linkCounts[$uid2] ?? 0);
        $this->assertSame([], $studyModel->mapByStudyUids([]));
    }
}
