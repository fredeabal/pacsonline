<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use CodeIgniter\Security\Exceptions\SecurityException;
use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F9: Study C-STORE sending boundaries, RBAC & Orthanc failure modes.
 */
final class F09_StudySendBoundaryTest extends E2ETestCase
{
    public function testMedicoIsDeniedSendStudyEndpoint(): void
    {
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.901');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_MED_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo Med S',
            'remote_ae_title'  => 'MED_S_AET',
            'remote_host'      => '10.4.4.1',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $this->loginAsRole('medico');

        $ajaxRes = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $seeded['center']['id'],
        ]);
        $ajaxRes->assertStatus(403);

        $formRes = $this->formPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $seeded['center']['id'],
        ]);
        $formRes->assertRedirect();
        $this->assertSame('Sin permiso.', session('error'));
    }

    public function testPacienteIsDeniedSendStudyEndpoint(): void
    {
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.902');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_PAC_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo Pac S',
            'remote_ae_title'  => 'PAC_S_AET',
            'remote_host'      => '10.4.4.2',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $this->loginAsRole('paciente');

        $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $seeded['center']['id'],
        ])->assertStatus(403);
    }

    public function testGuestIsRedirectedToLoginOnSendStudy(): void
    {
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.903');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_GST_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo Guest S',
            'remote_ae_title'  => 'GSTS_AET',
            'remote_host'      => '10.4.4.9',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $uid      = $study['MainDicomTags']['StudyInstanceUID'];
        $centerId = (int) $seeded['center']['id'];

        $this->formPost('studies/send/' . $uid, [
            'center_id' => $centerId,
        ])->assertRedirectTo('/login');

        $ajaxRes = $this->ajaxPost('studies/send/' . $uid, [
            'center_id' => $centerId,
        ]);
        if ($ajaxRes->isRedirect()) {
            $ajaxRes->assertRedirectTo('/login');
        } else {
            $this->assertContains($ajaxRes->response()->getStatusCode(), [401, 403]);
        }

        $storeReqs = array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with((string) ($r['path'] ?? ''), '/store')
        );
        $this->assertCount(0, $storeReqs);
    }

    public function testAjaxSendStudyWithoutCsrfReturns403(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.904');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_CSRF_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo CSRF S',
            'remote_ae_title'  => 'CSRF_S_AET',
            'remote_host'      => '10.4.4.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        Services::resetSingle('security');

        try {
            $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
                'center_id' => (int) $seeded['center']['id'],
            ], false);
            $this->fail('Expected SecurityException (403) when CSRF token is missing on AJAX studies/send.');
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $storeReqs = array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with((string) ($r['path'] ?? ''), '/store')
        );
        $this->assertCount(0, $storeReqs);
    }

    public function testSendNonExistentStudyReturns404(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_404_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo 404 S',
            'remote_ae_title'  => 'S404_AET',
            'remote_host'      => '10.4.4.4',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res = $this->ajaxPost('studies/send/1.2.826.0.1.3680043.999999', [
            'center_id' => (int) $seeded['center']['id'],
        ]);
        $res->assertStatus(404);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);
    }

    public function testSendToMissingOrInactiveCenterReturns422(): void
    {
        $this->loginAsRole('admin');
        $study = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.905');

        $missingCenterRes = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => 999999,
        ]);
        $missingCenterRes->assertStatus(422);

        $inactive = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_INACT_S',
            'active'           => 1,
            'remote_node_name' => 'Nodo Off',
            'remote_ae_title'  => 'OFF_S_AET',
            'remote_host'      => '10.4.4.5',
            'remote_port'      => 4242,
            'remote_active'    => 0,
        ]);
        $inactiveRes = $this->ajaxPost('studies/send/' . $study['MainDicomTags']['StudyInstanceUID'], [
            'center_id' => (int) $inactive['center']['id'],
        ]);
        $inactiveRes->assertStatus(422);
    }

    public function testSendStudyReportsExplicitErrorsOnPartialZeroAnd500StoreFailures(): void
    {
        $this->loginAsRole('admin');
        $study  = $this->seedStudyInOrthancAndDb('1.2.826.0.1.3680043.20.906');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_SFAIL',
            'active'           => 1,
            'remote_node_name' => 'Nodo Store Fail',
            'remote_ae_title'  => 'SFAIL_AET',
            'remote_host'      => '10.4.4.6',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $uid      = $study['MainDicomTags']['StudyInstanceUID'];
        $centerId = (int) $seeded['center']['id'];

        foreach (['partial_fail', 'zero_instances', 'fail_500'] as $faultMode) {
            $this->orthanc->setFaults(['store' => $faultMode]);
            $res = $this->ajaxPost('studies/send/' . $uid, ['center_id' => $centerId]);
            $this->assertGreaterThanOrEqual(400, $res->response()->getStatusCode(), "Expected error status for {$faultMode}");
            $json = json_decode((string) $res->response()->getBody(), true);
            $this->assertIsArray($json);
            $this->assertFalse($json['ok'], "Expected ok=false for {$faultMode}");
            $this->assertNotEmpty($json['message']);
        }
    }
}
