<?php

declare(strict_types=1);

namespace Tests\E2E\Tier2;

use App\Models\MedicalCenterModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\E2E\Support\E2ETestCase;

/**
 * Tier 2 — Feature F3: Remote DICOM node validation boundary & invalid inputs.
 */
final class F03_RemoteNodeValidationBoundaryTest extends E2ETestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function validAeTitlesProvider(): array
    {
        return [
            'single_char_min_length' => ['A'],
            'max_16_chars'           => ['ABCDEFGHIJKLMNOP'],
            'digits_and_punctuation' => ['PACS-01_LAB.A'],
        ];
    }

    #[DataProvider('validAeTitlesProvider')]
    public function testValidDicomAeTitleBoundariesAreAccepted(string $aeTitle): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica AET ' . $aeTitle,
            'ae_title'         => 'CTR_' . substr(md5($aeTitle), 0, 6),
            'remote_node_name' => 'Nodo Valido',
            'remote_ae_title'  => $aeTitle,
            'remote_host'      => '10.10.10.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirectTo('/centers');
        $this->assertNull(session('errors'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidAeTitlesProvider(): array
    {
        return [
            'too_long_17_chars'   => ['ABCDEFGHIJKLMNOPQ'],
            'contains_backslash'  => ['AET\\BAD'],
            'contains_space'      => ['AET BAD'],
            'contains_control_lf' => ["AET\nBAD"],
            'contains_non_ascii'  => ['AET_ÑANDU'],
        ];
    }

    #[DataProvider('invalidAeTitlesProvider')]
    public function testInvalidDicomAeTitlesAreRejectedWithSpanishLabel(string $badAeTitle): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Bad AET',
            'ae_title'         => 'CTR_BADAET',
            'remote_node_name' => 'Nodo Bad',
            'remote_ae_title'  => $badAeTitle,
            'remote_host'      => '10.10.10.1',
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('remote_ae_title', $errors);
        $this->assertStringNotContainsString('remote_ae_title', $errors['remote_ae_title']);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidHostsProvider(): array
    {
        return [
            'http_scheme'      => ['http://10.0.0.1'],
            'https_scheme'     => ['https://pacs.example.com'],
            'host_with_port'   => ['10.0.0.1:4242'],
            'host_with_spaces' => ['10.0.0.1 bad'],
            'host_with_slash'  => ['pacs.example.com/orthanc'],
        ];
    }

    #[DataProvider('invalidHostsProvider')]
    public function testInvalidRemoteHostsAreRejectedWithSpanishLabel(string $badHost): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Bad Host',
            'ae_title'         => 'CTR_BADHOST',
            'remote_node_name' => 'Nodo Host',
            'remote_ae_title'  => 'VALID_AET',
            'remote_host'      => $badHost,
            'remote_port'      => '4242',
            'remote_active'    => '1',
        ]);

        $res->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('remote_host', $errors);
        $this->assertStringNotContainsString('remote_host', $errors['remote_host']);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function tcpPortsProvider(): array
    {
        return [
            'port_1_min_valid'     => ['1', true],
            'port_65535_max_valid' => ['65535', true],
            'port_0_invalid'       => ['0', false],
            'port_65536_invalid'   => ['65536', false],
            'port_negative'        => ['-1', false],
            'port_non_numeric'     => ['abc', false],
        ];
    }

    #[DataProvider('tcpPortsProvider')]
    public function testTcpPortBoundariesAreEnforced(string $port, bool $shouldPass): void
    {
        $this->loginAsRole('admin');

        $ae  = 'CTR_P_' . substr(md5($port), 0, 6);
        $res = $this->formPost('centers', [
            'name'             => 'Clinica Port ' . $port,
            'ae_title'         => $ae,
            'remote_node_name' => 'Nodo Port',
            'remote_ae_title'  => 'PORT_AET',
            'remote_host'      => '10.10.10.2',
            'remote_port'      => $port,
            'remote_active'    => '1',
        ]);

        if ($shouldPass) {
            $res->assertRedirectTo('/centers');
            $this->assertNull(session('errors'));
            $row = model(MedicalCenterModel::class)->where('ae_title', $ae)->first();
            $this->assertSame((int) $port, (int) $row['remote_port']);
        } else {
            $res->assertRedirect();
            $errors = session('errors');
            $this->assertIsArray($errors);
            $this->assertArrayHasKey('remote_port', $errors);
            $this->assertStringNotContainsString('remote_port', $errors['remote_port']);
        }
    }

    public function testPartialRemoteConfigurationRequiresAllFourFieldsWithSpanishLabels(): void
    {
        $this->loginAsRole('admin');

        $res = $this->formPost('centers', [
            'name'             => 'Clinica Parcial',
            'ae_title'         => 'CTR_PARCIAL',
            'remote_node_name' => '',
            'remote_ae_title'  => '',
            'remote_host'      => '10.20.30.40',
            'remote_port'      => '',
            'remote_active'    => '1',
        ]);

        $res->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('remote_node_name', $errors);
        $this->assertArrayHasKey('remote_ae_title', $errors);
        $this->assertArrayHasKey('remote_port', $errors);

        foreach ($errors as $rawKey => $msg) {
            $this->assertStringNotContainsString($rawKey, $msg);
            $this->assertStringNotContainsString('(To be translated)', $msg);
        }
    }
}
