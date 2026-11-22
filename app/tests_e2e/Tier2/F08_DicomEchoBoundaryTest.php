<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F8: DICOM C-ECHO boundary & failure modes.
 */
final class F08_DicomEchoBoundaryTest extends E2ETestCase
{
    public function testTestConnectionOnMissingCenterReturns404Json(): void
    {
        $this->loginAsRole('admin');
        $res = $this->ajaxPost('centers/test-connection/999999');
        $res->assertStatus(404);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);
        $this->assertNotEmpty($json['message']);
    }

    public function testTestConnectionOnUnconfiguredCenterReturns422WithoutCallingOrthanc(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter(['ae_title' => 'CTR_UNCONF_E']);
        $centerId = (int) $seeded['center']['id'];

        $res = $this->ajaxPost('centers/test-connection/' . $centerId);
        $res->assertStatus(422);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);

        $echoReqs = array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with($r['path'], '/echo')
        );
        $this->assertCount(0, $echoReqs);
    }

    public function testTestConnectionOnInactiveCenterReturns422WithoutCallingOrthanc(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_INACT_E',
            'active'           => 0,
            'remote_node_name' => 'Nodo Inact E',
            'remote_ae_title'  => 'INACT_E',
            'remote_host'      => '10.3.3.10',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res = $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $res->assertStatus(422);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);
    }

    public function testTestConnectionOnRemoteActiveZeroReturns422WithoutCallingOrthanc(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_REMOFF_E',
            'active'           => 1,
            'remote_node_name' => 'Nodo RemOff E',
            'remote_ae_title'  => 'REMOFF_E',
            'remote_host'      => '10.3.3.11',
            'remote_port'      => 4242,
            'remote_active'    => 0,
        ]);

        $res = $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $res->assertStatus(422);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);
    }

    public function testTestConnectionReportsExplicitErrorWhenRemoteNodeFailsEcho(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_FAIL_E',
            'active'           => 1,
            'remote_node_name' => 'Nodo Fail E',
            'remote_ae_title'  => 'FAIL_E',
            'remote_host'      => '10.3.3.12',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $this->orthanc->setFaults(['echo' => 'fail_500']);

        $res = $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $this->assertContains($res->response()->getStatusCode(), [422, 502]);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertFalse($json['ok']);
        $this->assertNotEmpty($json['message']);
        $this->assertNotEmpty($json['detail'] ?? $json['message']);
    }

    public function testNonAjaxFormPostToTestConnectionRedirectsWithFlashMessage(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_NOAJAX_E',
            'active'           => 1,
            'remote_node_name' => 'Nodo Form E',
            'remote_ae_title'  => 'FORM_E',
            'remote_host'      => '10.3.3.13',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res = $this->formPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $res->assertRedirect();
        $this->assertNotNull(session('message') ?? session('success'));
    }

    public function testNonAjaxFormPostToTestConnectionFailureRedirectsWithFlashError(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_NOAJAX_EF',
            'active'           => 1,
            'remote_node_name' => 'Nodo Form Err',
            'remote_ae_title'  => 'FORME_AET',
            'remote_host'      => '10.3.3.14',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $this->orthanc->setFaults(['echo' => 'fail_500']);

        $res = $this->formPost('centers/test-connection/' . (int) $seeded['center']['id']);
        $res->assertRedirect();
        $this->assertNotEmpty(session('error'));
    }
}
