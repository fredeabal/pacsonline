<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F4: Configuration & Echo permission boundary (admin allowed).
 */
final class F04_CenterPermissionsHappyTest extends E2ETestCase
{
    public function testAdminCanViewCentersIndexAndCreateForm(): void
    {
        $this->loginAsRole('admin');

        $this->getPage('centers')->assertStatus(200);
        $this->getPage('centers/create')->assertStatus(200);
    }

    public function testAdminCanCreateCenterViaHttpForm(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Admin Store',
            'ae_title'         => 'ADM_STORE',
            'remote_node_name' => 'Nodo Admin',
            'remote_ae_title'  => 'ADM_REMOTE',
            'remote_host'      => '10.1.1.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $this->assertNotNull(model(MedicalCenterModel::class)->where('ae_title', 'ADM_STORE')->first());
    }

    public function testAdminCanEditAndUpdateCenterViaHttpForm(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter(['name' => 'Antes Edit', 'ae_title' => 'CTR_ANTES']);
        $centerId = (int) $seeded['center']['id'];

        $this->getPage('centers/edit/' . $centerId)->assertStatus(200);

        $res = $this->formPost('centers/update/' . $centerId, [
            'name'             => 'Despues Edit',
            'ae_title'         => 'CTR_DESPUES',
            'active'           => '1',
            'remote_node_name' => 'Nodo Actualizado',
            'remote_ae_title'  => 'NEW_REMOTE',
            'remote_host'      => '10.2.2.2',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ]);
        $res->assertRedirectTo('/centers');

        $updated = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame('Despues Edit', $updated['name']);
        $this->assertSame('CTR_DESPUES', $updated['ae_title']);
        $this->assertSame('NEW_REMOTE', $updated['remote_ae_title']);
    }

    public function testAdminCanRotateCenterTokenViaHttpForm(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter(['ae_title' => 'CTR_ROT']);
        $centerId = (int) $seeded['center']['id'];
        $oldHash  = $seeded['center']['api_token_hash'];

        $res = $this->formPost('centers/rotate-token/' . $centerId);
        $res->assertRedirectTo('/centers');

        $updated = model(MedicalCenterModel::class)->find($centerId);
        $this->assertNotSame($oldHash, $updated['api_token_hash']);
    }

    public function testAdminCanExecuteTestConnectionEndpoint(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_ECHO_PERM',
            'active'           => 1,
            'remote_node_name' => 'Nodo Perm',
            'remote_ae_title'  => 'PERM_ECHO',
            'remote_host'      => '10.3.3.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $res = $this->ajaxPost('centers/test-connection/' . $centerId);
        $res->assertStatus(200);
        $json = json_decode((string) $res->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertTrue($json['ok']);
    }
}
