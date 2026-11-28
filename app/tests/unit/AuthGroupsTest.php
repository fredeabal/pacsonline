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

        $this->assertSame('observador', $config->defaultGroup);
        $this->assertArrayHasKey('admin', $config->groups);
        $this->assertArrayHasKey('medico', $config->groups);
        $this->assertArrayHasKey('usuario', $config->groups);
        $this->assertArrayHasKey('observador', $config->groups);
        $this->assertCount(4, $config->groups);
    }

    public function testMedicoOnlyReviewsAndNeverUploadsOrManagesUsers(): void
    {
        $config = new AuthGroups();
        $matrix = $config->matrix['medico'];

        $this->assertContains('reports.review', $matrix);
        $this->assertContains('reports.sign', $matrix);
        $this->assertContains('studies.view_all', $matrix);
        $this->assertNotContains('studies.upload', $matrix);
        $this->assertNotContains('users.manage', $matrix);
        $this->assertNotContains('settings.manage', $matrix);
        $this->assertNotContains('reports.sign', $config->matrix['admin']);
        $this->assertNotContains('reports.sign', $config->matrix['usuario']);
        $this->assertNotContains('reports.sign', $config->matrix['observador']);
    }

    public function testObservadorOnlyViewsAndNeverEditsOrUploads(): void
    {
        $config = new AuthGroups();
        $matrix = $config->matrix['observador'];

        $this->assertContains('studies.view', $matrix);
        $this->assertContains('reports.view_own', $matrix);
        $this->assertNotContains('studies.upload', $matrix);
        $this->assertNotContains('studies.delete', $matrix);
        $this->assertNotContains('reports.review', $matrix);
        $this->assertNotContains('users.manage', $matrix);
    }
}
