<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F6: Persistent Orthanc modality storage across restarts & docker-compose config.
 */
final class F06_OrthancPersistenceAndConfigTest extends E2ETestCase
{
    public function testDockerComposeEnablesOrthancDatabaseModalityPersistence(): void
    {
        $composePath = dirname(ROOTPATH) . '/docker-compose.yml';
        $this->assertFileExists($composePath);
        $yaml = (string) file_get_contents($composePath);

        $this->assertMatchesRegularExpression('/ORTHANC__DICOM_MODALITIES_IN_DATABASE:\s*["\']?true["\']?/i', $yaml);
    }

    public function testSyncedModalitySurvivesOrthancRestart(): void
    {
        $this->loginAsRole('admin');

        $this->formPost('centers', [
            'name'             => 'Clinica Persiste Orthanc',
            'ae_title'         => 'CLIN_ORTH_P',
            'remote_node_name' => 'Nodo Persistente',
            'remote_ae_title'  => 'PERSIST_AET',
            'remote_host'      => '10.50.60.70',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center     = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_ORTH_P')->first();
        $modalityId = 'center_' . (int) $center['id'];

        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $get = Services::orthanc()->getModality($modalityId);
        $this->assertTrue($get['ok']);
        $this->assertSame('PERSIST_AET', $get['data']['AET']);
        $this->assertSame('10.50.60.70', $get['data']['Host']);
    }

    public function testEchoStillWorksAfterOrthancRestart(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica Echo Restart',
            'ae_title'         => 'CLIN_ECHO_R',
            'remote_node_name' => 'Nodo Echo R',
            'remote_ae_title'  => 'ECHO_R_AET',
            'remote_host'      => '10.50.60.71',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_ECHO_R')->first();

        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $echoRes = $this->ajaxPost('centers/test-connection/' . (int) $center['id']);
        $echoRes->assertStatus(200);
        $json = json_decode((string) $echoRes->response()->getBody(), true);
        $this->assertTrue($json['ok']);
    }

    public function testStudySendStillWorksAfterOrthancRestart(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.10.6001');

        $this->formPost('centers', [
            'name'             => 'Clinica Store Restart',
            'ae_title'         => 'CLIN_STR_R',
            'remote_node_name' => 'Nodo Store R',
            'remote_ae_title'  => 'STORE_R_AET',
            'remote_host'      => '10.50.60.72',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_STR_R')->first();

        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $sendRes = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $center['id'],
        ]);
        $sendRes->assertStatus(200);
        $json = json_decode((string) $sendRes->response()->getBody(), true);
        $this->assertTrue($json['ok']);
    }

    public function testModalityDeletionPersistsAcrossOrthancRestart(): void
    {
        $this->loginAsRole('admin');
        $this->formPost('centers', [
            'name'             => 'Clinica Del Restart',
            'ae_title'         => 'CLIN_DEL_R',
            'remote_node_name' => 'Nodo Del R',
            'remote_ae_title'  => 'DEL_R_AET',
            'remote_host'      => '10.50.60.73',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ])->assertRedirectTo('/centers');

        $center   = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_DEL_R')->first();
        $centerId = (int) $center['id'];

        $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Clinica Del Restart',
            'ae_title'         => 'CLIN_DEL_R',
            'active'           => '1',
            'remote_node_name' => 'Nodo Del R',
            'remote_ae_title'  => 'DEL_R_AET',
            'remote_host'      => '10.50.60.73',
            'remote_port'      => '4242',
            // remote_active omitted -> deactivated
        ])->assertRedirectTo('/centers');

        $this->orthanc->restart();
        $this->setOrthancEnv($this->orthanc->getUrl());

        $this->assertArrayNotHasKey('center_' . $centerId, $this->orthanc->getModalities());
    }
}
