<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\AuditLogModel;
use App\Models\StudyShareModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SharePinThrottleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    public function testVerifyPinBlocksAfterFiveAttempts(): void
    {
        $token = 'tok_throttle_' . bin2hex(random_bytes(4));
        $uid   = '1.2.826.0.1.3680043.88.1';

        model(StudyShareModel::class)->insert([
            'study_instance_uid' => $uid,
            'created_by_user_id' => 1,
            'token'              => $token,
            'pin_hash'           => password_hash('1234', PASSWORD_DEFAULT),
            'expires_at'         => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $res = $this->post("share/{$token}/verify", [
                'pin'        => '0000',
                csrf_token() => csrf_hash(),
            ]);
            $res->assertRedirect();
            $this->assertSame('PIN incorrecto.', session('error'));
        }

        $blocked = $this->post("share/{$token}/verify", [
            'pin'        => '1234',
            csrf_token() => csrf_hash(),
        ]);
        $blocked->assertRedirect();
        $this->assertStringContainsString('Demasiados intentos fallidos', (string) session('error'));

        $throttledLog = model(AuditLogModel::class)
            ->where('action', 'share.pin_throttled')
            ->where('study_instance_uid', $uid)
            ->first();
        $this->assertNotNull($throttledLog);
    }
}
