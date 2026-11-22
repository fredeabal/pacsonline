<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F2: Centers Create/Edit/Index UI fields & CSS layout rules.
 */
final class F02_CentersUiFieldsAndLayoutTest extends E2ETestCase
{
    public function testCentersCreateViewRendersRemoteDicomNodeInputs(): void
    {
        $this->loginAsRole('admin');
        $res = $this->getPage('centers/create');
        $res->assertStatus(200);

        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('name="remote_node_name"', $body);
        $this->assertStringContainsString('name="remote_ae_title"', $body);
        $this->assertStringContainsString('name="remote_host"', $body);
        $this->assertStringContainsString('name="remote_port"', $body);
        $this->assertStringContainsString('name="remote_active"', $body);
    }

    public function testCentersEditViewRendersRemoteNodeFieldsAndEchoTrigger(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'name'             => 'Centro UI Edit',
            'ae_title'         => 'CTR_UI_EDIT',
            'remote_node_name' => 'Nodo Edit',
            'remote_ae_title'  => 'AET_EDIT',
            'remote_host'      => '10.20.30.40',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $res = $this->getPage('centers/edit/' . $centerId);
        $res->assertStatus(200);

        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('name="remote_node_name"', $body);
        $this->assertStringContainsString('name="remote_ae_title"', $body);
        $this->assertStringContainsString('name="remote_host"', $body);
        $this->assertStringContainsString('name="remote_port"', $body);
        $this->assertStringContainsString('name="remote_active"', $body);
        $this->assertStringContainsString('Nodo Edit', $body);
        $this->assertStringContainsString('AET_EDIT', $body);
        $this->assertStringContainsString('10.20.30.40', $body);
        $this->assertStringContainsString('4242', $body);
        $this->assertStringContainsString('centers/test-connection/' . $centerId, $body);
    }

    public function testCentersIndexDisplaysRemoteNodeInfoAndTestConnectionAction(): void
    {
        $this->loginAsRole('admin');
        $this->seedMedicalCenter([
            'name'                => 'Centro Index UI',
            'ae_title'            => 'CTR_INDEX',
            'remote_node_name'    => 'Nodo Index',
            'remote_ae_title'     => 'AET_INDEX',
            'remote_host'         => '172.16.0.10',
            'remote_port'         => 11112,
            'remote_active'       => 1,
            'orthanc_sync_status' => 'synced',
        ]);

        $res = $this->getPage('centers');
        $res->assertStatus(200);

        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString('Centro Index UI', $body);
        $this->assertStringContainsString('AET_INDEX', $body);
        $this->assertStringContainsString('172.16.0.10', $body);
        $this->assertStringContainsString('centers/test-connection/', $body);
        $this->assertStringContainsString('id="centersTable"', $body);
        $this->assertStringContainsString('id="centersCards"', $body);
    }

    public function testCentersIndexMaintainsDesktopTableAndMobileCardsParity(): void
    {
        $this->loginAsRole('admin');
        $this->seedMedicalCenter([
            'name'             => 'Centro Dual View',
            'ae_title'         => 'CTR_DUAL',
            'remote_node_name' => 'Nodo Dual',
            'remote_ae_title'  => 'AET_DUAL',
            'remote_host'      => '10.0.0.5',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);

        $res  = $this->getPage('centers');
        $body = (string) $res->response()->getBody();

        $this->assertStringContainsString('id="centersTable"', $body);
        $this->assertStringContainsString('id="centersCards"', $body);
        $this->assertGreaterThanOrEqual(2, substr_count($body, 'Centro Dual View'));
    }

    public function testCustomCssEnforcesUniformTableRowHeightAndBadgeMinWidth(): void
    {
        $cssPath = FCPATH . 'assets/css/custom.css';
        $this->assertFileExists($cssPath);
        $css = (string) file_get_contents($cssPath);

        $this->assertMatchesRegularExpression('/\.table\s+tbody\s+tr\s*\{[^}]*height:\s*52px/s', $css);
        $this->assertMatchesRegularExpression('/\.table\s+\.badge\s*\{[^}]*min-width:\s*84px/s', $css);
        $this->assertMatchesRegularExpression('/\.table\s+\.badge\s*\{[^}]*text-align:\s*center/s', $css);
    }

    public function testCentersCreatePreservesGatewayTokenGenerationWorkflow(): void
    {
        $this->loginAsRole('admin');
        $res = $this->formPost('centers', [
            'name'     => 'Centro Solo Gateway',
            'ae_title' => 'CTR_GW_ONLY',
        ]);

        $res->assertRedirectTo('/centers');
        $token = session('generated_token');
        $this->assertIsString($token);
        $this->assertStringStartsWith('pacs_', $token);

        $indexRes = $this->getPage('centers');
        $indexRes->assertStatus(200);
        $this->assertStringContainsString('id="generatedTokenInput"', (string) $indexRes->response()->getBody());
    }
}
