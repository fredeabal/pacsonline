<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\AuthGroups;

final class AuthGroupsTest extends CIUnitTestCase
{
    public function testRolesAreStrictlyDefined(): void
    {
        $config = new AuthGroups();

        $this->assertSame('paciente', $config->defaultGroup);
        $this->assertArrayHasKey('admin', $config->groups);
        $this->assertArrayHasKey('medico', $config->groups);
        $this->assertArrayHasKey('paciente', $config->groups);
        $this->assertCount(3, $config->groups);
    }

    public function testMedicoOnlyReviewsAndNeverUploadsOrManagesUsers(): void
    {
        $config = new AuthGroups();
        $matrix = $config->matrix['medico'];

        $this->assertContains('reports.review', $matrix);
        $this->assertContains('studies.view_all', $matrix);
        $this->assertNotContains('studies.upload', $matrix);
        $this->assertNotContains('users.manage', $matrix);
        $this->assertNotContains('settings.manage', $matrix);
    }

    public function testPacienteOnlyViewsOwnAndShares(): void
    {
        $config = new AuthGroups();
        $matrix = $config->matrix['paciente'];

        $this->assertContains('studies.view', $matrix);
        $this->assertContains('studies.share', $matrix);
        $this->assertContains('reports.view_own', $matrix);
        $this->assertNotContains('studies.view_all', $matrix);
        $this->assertNotContains('studies.upload', $matrix);
        $this->assertNotContains('reports.review', $matrix);
    }
}
