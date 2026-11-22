<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Libraries\NotificationService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

final class NotificationServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testReturnsFalseWhenSmtpDisabled(): void
    {
        service('settings')->set('Smtp.enabled', false);

        $service = new NotificationService();
        $result  = $service->sendReportPublishedEmail('1.2.3.4', ['owner_user_id' => 1], ['reported_at' => '2026-11-17']);

        $this->assertFalse($result);
    }

    public function testReturnsFalseWhenStudyHasNoOwner(): void
    {
        service('settings')->set('Smtp.enabled', true);

        $service = new NotificationService();
        $result  = $service->sendReportPublishedEmail('1.2.3.4', ['owner_user_id' => null], ['reported_at' => '2026-11-17']);

        $this->assertFalse($result);
    }

    public function testWelcomeEmailReturnsFalseWhenSmtpDisabled(): void
    {
        service('settings')->set('Smtp.enabled', false);

        $patient = new User([
            'username' => 'pac_welcome',
            'email'    => 'pac_welcome@example.com',
        ]);

        $service = new NotificationService();
        $this->assertFalse($service->sendPatientWelcomeEmail($patient, 'TempPass123'));
        $this->assertFalse($service->sendStudyAssignedEmail('1.2.3.4', ['priority' => 'urgent'], 999));
    }
}
