<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F2: Centers UI edge cases, XSS escaping, and missing records.
 */
final class F02_CentersUiEdgeCasesTest extends E2ETestCase
{
    public function testCentersIndexEmptyStateRendersCleanlyInSpanish(): void
    {
        $this->loginAsRole('admin');
        $res = $this->getPage('centers');
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('No hay centros médicos registrados todavía.', $body);
    }

    public function testCentersViewsEscapeXssInCenterAndRemoteNodeNames(): void
    {
        $this->loginAsRole('admin');
        $xssName = '<script>alert("xss-center")</script>';
        $xssNode = '<img src=x onerror=alert(1)>';

        $seeded = $this->seedMedicalCenter([
            'name'             => $xssName,
            'ae_title'         => 'CTR_XSS',
            'remote_node_name' => $xssNode,
            'remote_ae_title'  => 'XSS_AET',
            'remote_host'      => '10.1.1.5',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $indexBody = (string) $this->getPage('centers')->response()->getBody();
        $this->assertStringNotContainsString($xssName, $indexBody);
        $this->assertStringNotContainsString($xssNode, $indexBody);
        $this->assertStringContainsString('&lt;script&gt;', $indexBody);

        $editBody = (string) $this->getPage('centers/edit/' . (int) $seeded['center']['id'])->response()->getBody();
        $this->assertStringNotContainsString($xssName, $editBody);
        $this->assertStringNotContainsString($xssNode, $editBody);
    }

    public function testEditNonExistentCenterRedirectsWithSpanishError(): void
    {
        $this->loginAsRole('admin');
        $res = $this->getPage('centers/edit/999999');
        $res->assertRedirectTo('/centers');
        $this->assertSame('Centro no encontrado.', session('error'));
    }

    public function testUpdateNonExistentCenterRedirectsWithSpanishError(): void
    {
        $this->loginAsRole('admin');
        $res = $this->formPost('centers/update/999999', [
            'name'     => 'No Existe',
            'ae_title' => 'NO_EXIST',
        ]);
        $res->assertRedirectTo('/centers');
        $this->assertSame('Centro no encontrado.', session('error'));
    }

    public function testCentersIndexShowsSyncErrorDetailsWhenCenterSyncFailed(): void
    {
        $this->loginAsRole('admin');
        $this->seedMedicalCenter([
            'name'                => 'Centro Error Sync',
            'ae_title'            => 'CTR_SYNC_ERR',
            'active'              => 1,
            'remote_node_name'    => 'Nodo Err',
            'remote_ae_title'     => 'ERR_AET',
            'remote_host'         => '10.1.1.99',
            'remote_port'         => 4242,
            'remote_active'       => 1,
            'orthanc_sync_status' => 'error',
            'orthanc_sync_error'  => 'Orthanc HTTP 503',
        ]);

        $res = $this->getPage('centers');
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('Centro Error Sync', $body);
        $this->assertStringContainsString('ERR_AET', $body);
        $this->assertStringContainsString('Orthanc HTTP 503', $body);
    }
}
