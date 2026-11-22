<?php

declare(strict_types=1);

namespace Tests\E2E\Support;

use App\Models\AuditLogModel;
use App\Models\MedicalCenterModel;
use App\Models\StudyModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Opaque-box base test case for E2E Tiers 1-4.
 *
 * Strictly forbids mocking OrthancClient, controllers, or models.
 * Connects the real CI4 HTTP stack (Routes, Filters, Shield, CSRF, SQLite :memory:)
 * to the stateful OrthancTestServer over loopback HTTP.
 */
abstract class E2ETestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected static ?OrthancTestServer $sharedOrthanc = null;
    protected OrthancTestServer $orthanc;

    private int $userSeq = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$sharedOrthanc = new OrthancTestServer();
        static::$sharedOrthanc->start();
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$sharedOrthanc !== null) {
            static::$sharedOrthanc->stop();
            static::$sharedOrthanc = null;
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (static::$sharedOrthanc === null) {
            static::$sharedOrthanc = new OrthancTestServer();
        }
        static::$sharedOrthanc->start();
        static::$sharedOrthanc->reset();
        $this->orthanc = static::$sharedOrthanc;

        $this->setOrthancEnv($this->orthanc->getUrl(), null, null);
        $this->resetAppSingletons();
        $this->resetFeatureRequestState();
    }

    protected function tearDown(): void
    {
        if (auth()->loggedIn()) {
            auth()->logout();
        }
        $this->resetAppSingletons();
        $this->clearOrthancEnv();
        parent::tearDown();
    }

    protected function resetAppSingletons(): void
    {
        Services::resetSingle('orthanc');
        Services::resetSingle('auth');
        Services::resetSingle('security');
    }

    protected function setOrthancEnv(string $url, ?string $user = null, ?string $password = null): void
    {
        putenv("ORTHANC_URL={$url}");
        $_ENV['ORTHANC_URL']    = $url;
        $_SERVER['ORTHANC_URL'] = $url;

        if ($user !== null && $user !== '') {
            putenv("ORTHANC_USER={$user}");
            $_ENV['ORTHANC_USER']    = $user;
            $_SERVER['ORTHANC_USER'] = $user;
        } else {
            putenv('ORTHANC_USER');
            unset($_ENV['ORTHANC_USER'], $_SERVER['ORTHANC_USER']);
        }

        if ($password !== null && $password !== '') {
            putenv("ORTHANC_PASSWORD={$password}");
            $_ENV['ORTHANC_PASSWORD']    = $password;
            $_SERVER['ORTHANC_PASSWORD'] = $password;
        } else {
            putenv('ORTHANC_PASSWORD');
            unset($_ENV['ORTHANC_PASSWORD'], $_SERVER['ORTHANC_PASSWORD']);
        }

        Services::resetSingle('orthanc');
    }

    protected function clearOrthancEnv(): void
    {
        putenv('ORTHANC_URL');
        putenv('ORTHANC_USER');
        putenv('ORTHANC_PASSWORD');
        unset(
            $_ENV['ORTHANC_URL'],
            $_SERVER['ORTHANC_URL'],
            $_ENV['ORTHANC_USER'],
            $_SERVER['ORTHANC_USER'],
            $_ENV['ORTHANC_PASSWORD'],
            $_SERVER['ORTHANC_PASSWORD']
        );
    }

    protected function resetFeatureRequestState(): void
    {
        $this->withHeaders([])
            ->withBodyFormat('')
            ->withBody('');
    }

    /**
     * Performs a clean GET request without header/body leakage.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    protected function getPage(string $path, array $params = [], array $headers = []): TestResponse
    {
        $this->resetFeatureRequestState();
        if ($headers !== []) {
            $this->withHeaders($headers);
        }
        $this->withSession($_SESSION ?? []);

        $response = $this->get($path, $params === [] ? null : $params);
        $this->withSession($_SESSION ?? []);

        return $response;
    }

    /**
     * Performs a clean form POST with automatic CSRF token unless disabled.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $headers
     */
    protected function formPost(string $path, array $params = [], bool $withCsrf = true, array $headers = []): TestResponse
    {
        $this->resetFeatureRequestState();
        if ($withCsrf && ! array_key_exists(csrf_token(), $params)) {
            $params[csrf_token()] = csrf_hash();
        }
        if ($headers !== []) {
            $this->withHeaders($headers);
        }
        $this->withSession($_SESSION ?? []);

        $response = $this->post($path, $params);
        $this->withSession($_SESSION ?? []);

        return $response;
    }

    /**
     * Performs an AJAX POST (X-Requested-With: XMLHttpRequest) with optional CSRF header/field.
     *
     * @param array<string, mixed> $params
     * @param array<string, string> $extraHeaders
     */
    protected function ajaxPost(string $path, array $params = [], bool $withCsrf = true, array $extraHeaders = []): TestResponse
    {
        $this->resetFeatureRequestState();
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        if ($withCsrf) {
            $token                     = csrf_hash();
            $headers['X-CSRF-TOKEN']   = $token;
            if (! array_key_exists(csrf_token(), $params)) {
                $params[csrf_token()] = $token;
            }
        }
        foreach ($extraHeaders as $k => $v) {
            $headers[$k] = $v;
        }

        $this->withHeaders($headers);
        $this->withSession($_SESSION ?? []);

        $response = $this->post($path, $params);
        $this->withSession($_SESSION ?? []);

        return $response;
    }

    /**
     * Creates an activated Shield user in the given group(s).
     *
     * @param list<string>|string $groups
     */
    protected function createUser(string|array $groups = 'admin', ?string $email = null, string $password = 'SecretPass123!'): User
    {
        $this->userSeq++;
        $suffix = $this->userSeq . '_' . bin2hex(random_bytes(3));
        $email  = $email ?? "e2e_user_{$suffix}@pacs.test";

        $users = auth()->getProvider();
        $user  = new User([
            'username'  => 'e2e_' . $suffix,
            'email'     => $email,
            'password'  => $password,
            'full_name' => 'E2E User ' . $suffix,
        ]);
        $users->save($user);

        /** @var User $saved */
        $saved = $users->findById($users->getInsertID());
        $saved->activate();
        foreach ((array) $groups as $group) {
            $saved->addGroup($group);
        }

        return $saved;
    }

    /**
     * Logs in a newly created user with the given role via Shield's auth()->login().
     */
    protected function loginAsRole(string $role): User
    {
        if (auth()->loggedIn()) {
            auth()->logout();
        }
        Services::resetSingle('auth');
        $user = $this->createUser($role);
        auth()->login($user);
        $this->withSession($_SESSION ?? []);

        return $user;
    }

    /**
     * Logs in via real HTTP POST /login.
     */
    protected function loginViaHttp(string $email, string $password): TestResponse
    {
        if (auth()->loggedIn()) {
            auth()->logout();
        }
        Services::resetSingle('auth');
        $this->getPage('login');
        return $this->formPost('login', [
            'email'    => $email,
            'password' => $password,
        ]);
    }

    /**
     * Builds a minimal valid DICOM Part 10 binary payload with Explicit VR Little Endian tags.
     */
    protected function buildMinimalDicomBytes(
        string $studyUid,
        string $patientName = 'Paciente^Prueba',
        string $patientId = 'PAC-E2E-001',
        string $studyDate = '20261119',
        string $modality = 'CT',
        string $studyDescription = 'TC Torax E2E'
    ): string {
        $encodeTag = static function (int $group, int $element, string $vr, string $value): string {
            if (strlen($value) % 2 !== 0) {
                $value .= ($vr === 'UI') ? "\0" : ' ';
            }
            return pack('vv', $group, $element) . $vr . pack('v', strlen($value)) . $value;
        };

        $sopUid    = $studyUid . '.1.1.' . mt_rand(100, 999);
        $seriesUid = $studyUid . '.1';

        $preamble = str_repeat("\0", 128) . 'DICM';
        $dataset  = ''
            . $encodeTag(0x0008, 0x0018, 'UI', $sopUid)
            . $encodeTag(0x0008, 0x0020, 'DA', $studyDate)
            . $encodeTag(0x0008, 0x0060, 'CS', $modality)
            . $encodeTag(0x0008, 0x1030, 'LO', $studyDescription)
            . $encodeTag(0x0010, 0x0010, 'PN', $patientName)
            . $encodeTag(0x0010, 0x0020, 'LO', $patientId)
            . $encodeTag(0x0020, 0x000D, 'UI', $studyUid)
            . $encodeTag(0x0020, 0x000E, 'UI', $seriesUid);

        return $preamble . $dataset;
    }

    /**
     * Ingests a synthetic DICOM payload via POST /api/ingest/dicom with a center Bearer token.
     */
    protected function ingestDicomViaApi(
        string $bearerToken,
        string $studyUid,
        string $patientName = 'Paciente^Ingesta',
        string $patientId = 'PAC-ING-1',
        string $studyDate = '20261119',
        string $modality = 'CT',
        string $studyDescription = 'TC Ingestada'
    ): TestResponse {
        $binary = $this->buildMinimalDicomBytes($studyUid, $patientName, $patientId, $studyDate, $modality, $studyDescription);
        $this->resetFeatureRequestState();

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $bearerToken,
            'Content-Type'  => 'application/dicom',
        ])->withBody($binary)->post('api/ingest/dicom');
    }

    /**
     * Creates a MedicalCenter row directly via model for fixture setup (non-HTTP setup).
     *
     * @param array<string, mixed> $overrides
     *
     * @return array{center: array<string, mixed>, plain_token: string}
     */
    protected function seedMedicalCenter(array $overrides = []): array
    {
        $plainToken = 'pacs_' . bin2hex(random_bytes(24));
        $suffix     = strtoupper(bin2hex(random_bytes(3)));
        $data       = array_merge([
            'name'           => 'Centro Fixture ' . $suffix,
            'ae_title'       => 'CTR_' . $suffix,
            'api_token_hash' => hash('sha256', $plainToken),
            'active'         => 1,
        ], $overrides);

        $model = model(MedicalCenterModel::class);
        $id    = $model->insert($data, true);
        $row   = $model->find((int) $id);

        return [
            'center'      => is_array($row) ? $row : [],
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Seeds a study in both the Orthanc simulator and SQLite `studies` table.
     *
     * @return array<string, mixed>
     */
    protected function seedStudyInOrthancAndDb(
        ?string $studyUid = null,
        string $patientName = 'Juan Perez',
        string $patientId = 'PAC-100',
        string $studyDate = '20261119',
        string $modality = 'CT',
        string $studyDescription = 'TC Torax'
    ): array {
        $studyUid ??= '1.2.826.0.1.3680043.10.' . mt_rand(100000, 999999);
        $orthancStudy = $this->orthanc->seedStudy([
            'StudyInstanceUID' => $studyUid,
            'PatientName'      => $patientName,
            'PatientID'        => $patientId,
            'StudyDate'        => $studyDate,
            'Modality'         => $modality,
            'StudyDescription' => $studyDescription,
        ]);

        $studyModel = model(StudyModel::class);
        $studyModel->insert([
            'orthanc_id'         => $orthancStudy['ID'],
            'study_instance_uid' => $studyUid,
            'patient_name'       => $patientName,
            'study_description'  => $studyDescription,
            'study_date'         => $studyDate,
        ]);

        return $orthancStudy;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function getAuditLogsByAction(string $action): array
    {
        return model(AuditLogModel::class)
            ->where('action', $action)
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
