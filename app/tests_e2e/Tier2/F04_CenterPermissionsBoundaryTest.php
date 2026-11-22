<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\MedicalCenterModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use Config\Services;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F4: Center & C-ECHO permission and CSRF boundaries.
 */
final class F04_CenterPermissionsBoundaryTest extends E2ETestCase
{
    public function testMedicoIsDeniedAllCenterRoutesAndTestConnection(): void
    {
        $seeded   = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_MED_DENY',
            'remote_node_name' => 'Nodo Deny',
            'remote_ae_title'  => 'DENY_AET',
            'remote_host'      => '10.1.1.1',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->loginAsRole('medico');

        $this->getPage('centers')->assertRedirectTo('/');
        $this->assertSame('Sin permiso.', session('error'));

        $this->getPage('centers/create')->assertRedirectTo('/');
        $this->getPage('centers/edit/' . $centerId)->assertRedirectTo('/');

        $this->formPost('centers', ['name' => 'Hack', 'ae_title' => 'HACK1'])->assertRedirectTo('/');
        $this->assertNull(model(MedicalCenterModel::class)->where('ae_title', 'HACK1')->first());

        $this->formPost('centers/update/' . $centerId, ['name' => 'Hack2', 'ae_title' => 'CTR_MED_DENY'])->assertRedirectTo('/');
        $this->formPost('centers/rotate-token/' . $centerId)->assertRedirectTo('/');

        $ajaxEcho = $this->ajaxPost('centers/test-connection/' . $centerId);
        $ajaxEcho->assertStatus(403);
    }

    public function testPacienteIsDeniedAllCenterRoutesAndTestConnection(): void
    {
        $seeded   = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_PAC_DENY',
            'remote_node_name' => 'Nodo Pac Deny',
            'remote_ae_title'  => 'PDENY_AET',
            'remote_host'      => '10.1.1.2',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->loginAsRole('paciente');

        $this->getPage('centers')->assertRedirectTo('/');
        $this->assertSame('Sin permiso.', session('error'));

        $this->formPost('centers', ['name' => 'Hack Pac', 'ae_title' => 'HACKPAC'])->assertRedirectTo('/');
        $this->assertNull(model(MedicalCenterModel::class)->where('ae_title', 'HACKPAC')->first());

        $this->ajaxPost('centers/test-connection/' . $centerId)->assertStatus(403);
    }

    public function testUnauthenticatedGuestIsRedirectedToLoginForCentersAndEcho(): void
    {
        $seeded   = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_GUEST',
            'active'           => 1,
            'remote_node_name' => 'Nodo Guest',
            'remote_ae_title'  => 'GUEST_AET',
            'remote_host'      => '10.1.1.4',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        $centerId = (int) $seeded['center']['id'];

        $this->getPage('centers')->assertRedirectTo('/login');
        $this->getPage('centers/create')->assertRedirectTo('/login');
        $this->formPost('centers', ['name' => 'Guest', 'ae_title' => 'GUEST1'])->assertRedirectTo('/login');
        $this->formPost('centers/test-connection/' . $centerId)->assertRedirectTo('/login');

        $ajaxRes = $this->ajaxPost('centers/test-connection/' . $centerId);
        if ($ajaxRes->isRedirect()) {
            $ajaxRes->assertRedirectTo('/login');
        } else {
            $this->assertContains($ajaxRes->response()->getStatusCode(), [401, 403]);
        }

        $echoReqs = array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with((string) ($r['path'] ?? ''), '/echo')
        );
        $this->assertCount(0, $echoReqs);
    }

    public function testMissingCsrfOnCenterCreateIsRejected(): void
    {
        $this->loginAsRole('admin');
        Services::resetSingle('security');

        $res = $this->formPost('centers', [
            'name'     => 'Sin CSRF',
            'ae_title' => 'NO_CSRF',
        ], false);

        $res->assertRedirect();
        $this->assertNull(model(MedicalCenterModel::class)->where('ae_title', 'NO_CSRF')->first());
    }

    public function testInvalidCsrfOnCenterUpdateIsRejected(): void
    {
        $this->loginAsRole('admin');
        $seeded   = $this->seedMedicalCenter(['name' => 'Before CSRF', 'ae_title' => 'CTR_CSRF_U']);
        $centerId = (int) $seeded['center']['id'];

        $res = $this->formPost('centers/update/' . $centerId, [
            'name'         => 'Mutated Without CSRF',
            'ae_title'     => 'CTR_CSRF_U',
            csrf_token()   => 'invalid_csrf_token_value',
        ], false);

        $res->assertRedirect();
        $row = model(MedicalCenterModel::class)->find($centerId);
        $this->assertSame('Before CSRF', $row['name']);
    }

    public function testAjaxTestConnectionWithoutCsrfReturns403(): void
    {
        $this->loginAsRole('admin');
        $seeded = $this->seedMedicalCenter([
            'ae_title'         => 'CTR_CSRF_E',
            'remote_node_name' => 'Nodo CSRF',
            'remote_ae_title'  => 'CSRF_ECHO',
            'remote_host'      => '10.1.1.3',
            'remote_port'      => 4242,
            'remote_active'    => 1,
        ]);
        Services::resetSingle('security');

        try {
            $this->ajaxPost('centers/test-connection/' . (int) $seeded['center']['id'], [], false);
            $this->fail('Expected SecurityException (403) when CSRF token is missing on AJAX test-connection.');
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        $echoReqs = array_filter(
            $this->orthanc->getRequests(),
            static fn (array $r): bool => str_ends_with((string) ($r['path'] ?? ''), '/echo')
        );
        $this->assertCount(0, $echoReqs);
    }
}
