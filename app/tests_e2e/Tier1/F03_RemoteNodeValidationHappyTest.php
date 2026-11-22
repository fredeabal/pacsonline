<?php

declare(strict_types=1);

namespace Tests\E2E\Tier1;

use App\Models\MedicalCenterModel;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 1 — Feature F3: Remote DICOM node input validation (happy paths & normalization).
 */
final class F03_RemoteNodeValidationHappyTest extends E2ETestCase
{
    public function testCreateCenterWithValidIpv4RemoteNodeSucceeds(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica IPv4',
            'ae_title'         => 'CLIN_IPV4',
            'remote_node_name' => 'PACS IPv4',
            'remote_ae_title'  => 'REMOTE_IPV4',
            'remote_host'      => '192.168.1.100',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_IPV4')->first();
        $this->assertIsArray($center);
        $this->assertSame('192.168.1.100', $center['remote_host']);
        $this->assertSame(4242, (int) $center['remote_port']);
    }

    public function testCreateCenterWithValidFqdnHostAndHighPortSucceeds(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica FQDN',
            'ae_title'         => 'CLIN_FQDN',
            'remote_node_name' => 'PACS FQDN',
            'remote_ae_title'  => 'ORTHANC_HQ',
            'remote_host'      => 'pacs-remoto.clinica.example.com',
            'remote_port'      => '11112',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_FQDN')->first();
        $this->assertIsArray($center);
        $this->assertSame('pacs-remoto.clinica.example.com', $center['remote_host']);
        $this->assertSame(11112, (int) $center['remote_port']);
    }

    public function testCreateCenterWithValidIpv6HostSucceeds(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica IPv6',
            'ae_title'         => 'CLIN_IPV6',
            'remote_node_name' => 'PACS IPv6',
            'remote_ae_title'  => 'NODE_IPV6',
            'remote_host'      => '2001:db8::10',
            'remote_port'      => '104',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_IPV6')->first();
        $this->assertIsArray($center);
        $this->assertSame('2001:db8::10', $center['remote_host']);
    }

    public function testRemoteAeTitleAndHostAreTrimmedAndNormalized(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Normalize',
            'ae_title'         => 'clin_norm',
            'remote_node_name' => '  Nodo Normalizado  ',
            'remote_ae_title'  => '  pacs_sub_01  ',
            'remote_host'      => '  10.0.0.15  ',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_NORM')->first();
        $this->assertIsArray($center);
        $this->assertSame('Nodo Normalizado', $center['remote_node_name']);
        $this->assertSame('PACS_SUB_01', $center['remote_ae_title']);
        $this->assertSame('10.0.0.15', $center['remote_host']);
    }

    public function testCreateCenterWithoutRemoteFieldsIsStillAllowed(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Sin Nodo',
            'ae_title'         => 'CLIN_NONODE',
            'remote_node_name' => '',
            'remote_ae_title'  => '',
            'remote_host'      => '',
            'remote_port'      => '',
        ]);

        $res->assertRedirectTo('/centers');
        $center = model(MedicalCenterModel::class)->where('ae_title', 'CLIN_NONODE')->first();
        $this->assertIsArray($center);
        $this->assertSame(0, (int) $center['remote_active']);
    }
}
