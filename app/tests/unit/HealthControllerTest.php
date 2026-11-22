<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class HealthControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthEndpointReturnsJsonStatus(): void
    {
        $result = $this->get('health');
        $json   = json_decode($result->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('orthanc', $json);
        $this->assertContains($result->response()->getStatusCode(), [200, 503]);
    }
}
