<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class SpanishLocalizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected function tearDown(): void
    {
        if (auth()->loggedIn()) {
            auth()->logout();
        }
        parent::tearDown();
    }

    public function testLoginViewUsesConciseSpanishSweetAlertTitles(): void
    {
        $res = $this->get('login');
        $res->assertStatus(200);
        $body = (string) $res->response()->getBody();
        $this->assertStringContainsString("title: 'Datos inválidos'", $body);
        $this->assertStringNotContainsString('Error de Validación', $body);
    }

    public function testLoginWithMissingPasswordReturnsSpanishValidationError(): void
    {
        $this->get('login');
        $res = $this->post('login', [
            'email'      => 'medico@clinica.com',
            'password'   => '',
            csrf_token() => csrf_hash(),
        ]);

        $res->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertSame('El campo Contraseña es obligatorio.', $errors['password']);
        $this->assertStringNotContainsString('The Password field is required.', $errors['password']);
    }

    public function testLoginWithMissingEmailReturnsSpanishValidationError(): void
    {
        $this->get('login');
        $res = $this->post('login', [
            'email'      => '',
            'password'   => 'Secret123!',
            csrf_token() => csrf_hash(),
        ]);

        $res->assertRedirect();
        $errors = session('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('El campo Correo electrónico es obligatorio.', $errors['email']);
    }

    public function testLoginWithInvalidCredentialsReturnsConciseSpanishError(): void
    {
        $users = auth()->getProvider();
        $user  = new User([
            'username' => 'login_test_user',
            'email'    => 'login.test@pacs.test',
            'password' => 'CorrectPass123!',
        ]);
        $users->save($user);
        $saved = $users->findById($users->getInsertID());
        $saved?->activate();

        $resWrongPass = $this->post('login', [
            'email'      => 'login.test@pacs.test',
            'password'   => 'WrongPassword!',
            csrf_token() => csrf_hash(),
        ]);

        $resWrongPass->assertRedirect();
        $this->assertSame('Contraseña incorrecta.', session('error'));

        $resUnknown = $this->post('login', [
            'email'      => 'nobody@pacs.test',
            'password'   => 'WrongPassword!',
            csrf_token() => csrf_hash(),
        ]);

        $resUnknown->assertRedirect();
        $this->assertSame('Credenciales incorrectas.', session('error'));
    }

    public function testMagicLinkWithUnknownEmailReturnsConciseSpanishWithoutUntranslatedMarker(): void
    {
        $res = $this->post('login/magic-link', [
            'email'      => 'unknown@pacs.test',
            csrf_token() => csrf_hash(),
        ]);

        $res->assertRedirect();
        $error = (string) session('error');
        $this->assertStringContainsString('No se encontró una cuenta con el correo "unknown@pacs.test".', $error);
        $this->assertStringNotContainsString('(To be translated)', $error);
    }

    public function testLanguageOverridesContainNoUntranslatedStrings(): void
    {
        $authKeys = [
            'invalidEmail',
            'magicLinkDisabled',
            'invalidJWT',
            'expiredJWT',
            'beforeValidJWT',
            'token',
            'badAttempt',
            'invalidPassword',
            'forgotPassword',
            'email',
            'password',
        ];

        foreach ($authKeys as $key) {
            $val = lang('Auth.' . $key, ['test@pacs.test'], 'es');
            $this->assertStringNotContainsString('(To be translated)', $val, "Key Auth.{$key} contains untranslated text");
        }

        $this->assertSame('Acción no permitida o sesión expirada.', lang('Security.disallowedAction', [], 'es'));
        $this->assertSame('404 - Página no encontrada', lang('Errors.pageNotFound', [], 'es'));
    }

    public function testControllersReturnSpanishValidationLabelsWithoutRawFieldKeys(): void
    {
        $users = auth()->getProvider();
        $admin = new User([
            'username' => 'admin_i18n',
            'email'    => 'admin.i18n@pacs.test',
            'password' => 'AdminPass123!',
        ]);
        $users->save($admin);
        $savedAdmin = $users->findById($users->getInsertID());
        $this->assertNotNull($savedAdmin);
        $savedAdmin->activate();
        $savedAdmin->addGroup('admin');
        $savedAdmin->addGroup('medico');

        auth()->login($savedAdmin);

        // 1. CenterController::store
        $centerRes = $this->post('centers', [
            'name'       => '',
            'ae_title'   => '',
            csrf_token() => csrf_hash(),
        ]);
        $centerRes->assertRedirect();
        $centerErrors = session('errors');
        $this->assertIsArray($centerErrors);
        $this->assertSame('El campo Nombre es obligatorio.', $centerErrors['name']);
        $this->assertSame('El campo AE Title es obligatorio.', $centerErrors['ae_title']);
        $this->assertStringNotContainsString('ae_title', $centerErrors['ae_title']);

        // 2. UserController::store
        $userRes = $this->post('users', [
            'username'   => '',
            'email'      => '',
            'password'   => '',
            'role'       => '',
            csrf_token() => csrf_hash(),
        ]);
        $userRes->assertRedirect();
        $userErrors = session('errors');
        $this->assertIsArray($userErrors);
        $this->assertSame('El campo Usuario es obligatorio.', $userErrors['username']);
        $this->assertSame('El campo Correo electrónico es obligatorio.', $userErrors['email']);
        $this->assertSame('El campo Contraseña es obligatorio.', $userErrors['password']);
        $this->assertSame('El campo Rol es obligatorio.', $userErrors['role']);

        // 3. ReportController::save
        $reportRes = $this->post('reports/save/1.2.826.0.1.3680043.99.1', [
            'findings'    => '',
            'impression'  => '',
            'action_type' => '',
            csrf_token()  => csrf_hash(),
        ]);
        $reportRes->assertRedirect();
        $reportErrors = session('errors');
        $this->assertIsArray($reportErrors);
        $this->assertSame('El campo Hallazgos es obligatorio.', $reportErrors['findings']);
        $this->assertSame('El campo Conclusión es obligatorio.', $reportErrors['impression']);
        $this->assertSame('El campo Acción es obligatorio.', $reportErrors['action_type']);
        $this->assertStringNotContainsString('action_type', $reportErrors['action_type']);

        // 4. ProfileController::updatePassword
        $passRes = $this->post('profile/password', [
            'current_password' => '',
            'new_password'     => '',
            'confirm_password' => '',
            csrf_token()       => csrf_hash(),
        ]);
        $passRes->assertRedirect();
        $passErrors = session('errors');
        $this->assertIsArray($passErrors);
        $this->assertSame('El campo Contraseña actual es obligatorio.', $passErrors['current_password']);
        $this->assertSame('El campo Nueva contraseña es obligatorio.', $passErrors['new_password']);
        $this->assertSame('El campo Confirmar contraseña es obligatorio.', $passErrors['confirm_password']);
        $this->assertStringNotContainsString('current_password', $passErrors['current_password']);
    }
}
