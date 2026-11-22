<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Models\StudyAttachmentModel;
use App\Models\StudyModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class CaseTriageAndAttachmentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testStudyPriorityAndAssignedDoctor(): void
    {
        $model = model(StudyModel::class);
        $uid   = '1.2.826.0.1.3680043.77.1';

        $model->insert([
            'orthanc_id'         => 'orthanc-77',
            'study_instance_uid' => $uid,
            'owner_user_id'      => 10,
            'assigned_doctor_id' => 3,
            'priority'           => 'urgent',
            'patient_name'       => 'Maria Lopez',
            'clinical_notes'     => 'Dolor lumbar irradiado desde hace 3 semanas.',
        ]);

        $study = $model->findByStudyUid($uid);
        $this->assertNotNull($study);
        $this->assertSame('urgent', $study['priority']);
        $this->assertSame(3, (int) $study['assigned_doctor_id']);
        $this->assertSame('Dolor lumbar irradiado desde hace 3 semanas.', $study['clinical_notes']);
    }

    public function testStudyAttachmentsAreScopedByStudyUid(): void
    {
        $attModel = model(StudyAttachmentModel::class);
        $uid1     = '1.2.826.0.1.3680043.77.2';
        $uid2     = '1.2.826.0.1.3680043.77.3';

        $attModel->insert([
            'study_instance_uid'  => $uid1,
            'uploaded_by_user_id' => 1,
            'original_name'       => 'analitica.pdf',
            'stored_name'         => 'rand1.pdf',
            'mime_type'           => 'application/pdf',
            'file_size'           => 10240,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $attModel->insert([
            'study_instance_uid'  => $uid2,
            'uploaded_by_user_id' => 1,
            'original_name'       => 'otro.pdf',
            'stored_name'         => 'rand2.pdf',
            'mime_type'           => 'application/pdf',
            'file_size'           => 5120,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);

        $list1 = $attModel->forStudy($uid1);
        $this->assertCount(1, $list1);
        $this->assertSame('analitica.pdf', $list1[0]['original_name']);
    }
}
