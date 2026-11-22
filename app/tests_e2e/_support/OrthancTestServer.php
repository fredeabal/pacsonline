<?php

declare(strict_types=1);

namespace Tests\E2E\Support;

use RuntimeException;
use WeakReference;

/**
 * Ephemeral stateful Orthanc HTTP simulator lifecycle manager.
 *
 * Uses `PHP_BINARY -S 127.0.0.1:<port>` backed by an flock()-protected JSON
 * state file so restart persistence, fault injection, Basic Auth, and request
 * inspection work over real loopback TCP/HTTP.
 *
 * If E2E_ORTHANC_URL is set (and $forceLocal is false), delegates to the live
 * external Orthanc instance without spawning a local process.
 */
final class OrthancTestServer
{
    private ?string $externalUrl = null;
    private string $stateFile;
    private bool $ownsStateFile;
    private ?string $docRoot   = null;
    private ?string $stderrLog = null;
    private ?int $port         = null;
    /** @var resource|null */
    private $process = null;
    private bool $shutdownHookRegistered = false;

    public function __construct(?string $stateFile = null, bool $forceLocal = false)
    {
        $envUrl = getenv('E2E_ORTHANC_URL');
        if (! $forceLocal && is_string($envUrl) && trim($envUrl) !== '') {
            $this->externalUrl   = rtrim(trim($envUrl), '/');
            $this->stateFile     = '';
            $this->ownsStateFile = false;
            return;
        }

        if ($stateFile !== null && $stateFile !== '') {
            $this->stateFile     = $stateFile;
            $this->ownsStateFile = false;
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'orthanc_sim_state_');
            if ($tmp === false) {
                throw new RuntimeException('Unable to create temp state file for OrthancTestServer.');
            }
            $this->stateFile     = $tmp;
            $this->ownsStateFile = true;
        }
    }

    public function __destruct()
    {
        $this->stop();
        if ($this->ownsStateFile && $this->stateFile !== '' && is_file($this->stateFile)) {
            @unlink($this->stateFile);
        }
    }

    public function isExternal(): bool
    {
        return $this->externalUrl !== null;
    }

    public function start(): void
    {
        if ($this->externalUrl !== null) {
            return;
        }
        if ($this->isRunning()) {
            return;
        }

        $this->port = $this->allocateEphemeralPort();
        $this->docRoot = sys_get_temp_dir() . '/orthanc_sim_root_' . bin2hex(random_bytes(6));
        if (! is_dir($this->docRoot) && ! mkdir($this->docRoot, 0775, true) && ! is_dir($this->docRoot)) {
            throw new RuntimeException('Unable to create docRoot for OrthancTestServer.');
        }

        $stderr = tempnam(sys_get_temp_dir(), 'orthanc_sim_err_');
        if ($stderr === false) {
            throw new RuntimeException('Unable to create stderr log file for OrthancTestServer.');
        }
        $this->stderrLog = $stderr;

        if (! is_file($this->stateFile) || filesize($this->stateFile) === 0) {
            file_put_contents($this->stateFile, json_encode([
                'modalities' => [],
                'studies'    => [],
                'instances'  => [],
                'faults'     => [],
                'requests'   => [],
            ]));
        }

        $routerScript = __DIR__ . '/orthanc_router.php';
        $cmd          = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $this->port,
            '-t',
            $this->docRoot,
            $routerScript,
        ];

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', $this->stderrLog, 'a'],
        ];

        $env                            = getenv();
        $env['ORTHANC_FAKE_STATE_FILE'] = $this->stateFile;

        $proc = proc_open($cmd, $descriptors, $pipes, $this->docRoot, $env);
        if (! is_resource($proc)) {
            throw new RuntimeException('Failed to spawn php -S Orthanc simulator.');
        }
        $this->process = $proc;

        if (! $this->shutdownHookRegistered) {
            $this->shutdownHookRegistered = true;
            $weakSelf                     = WeakReference::create($this);
            register_shutdown_function(static function () use ($weakSelf): void {
                $self = $weakSelf->get();
                if ($self !== null) {
                    $self->stop();
                }
            });
        }

        $this->waitUntilReady();
    }

    public function stop(): void
    {
        if ($this->externalUrl !== null) {
            return;
        }

        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process);
                $deadline = microtime(true) + 2.0;
                while (microtime(true) < $deadline) {
                    $st = proc_get_status($this->process);
                    if (! $st['running']) {
                        break;
                    }
                    usleep(20_000);
                }
                $st = proc_get_status($this->process);
                if ($st['running']) {
                    proc_terminate($this->process, 9);
                }
            }
            @proc_close($this->process);
        }
        $this->process = null;
        $this->port    = null;

        if ($this->docRoot !== null && is_dir($this->docRoot)) {
            @rmdir($this->docRoot);
        }
        $this->docRoot = null;

        if ($this->stderrLog !== null && is_file($this->stderrLog)) {
            @unlink($this->stderrLog);
        }
        $this->stderrLog = null;
    }

    public function restart(): void
    {
        if ($this->externalUrl !== null) {
            return;
        }

        $this->stop();
        $this->start();
    }

    public function isRunning(): bool
    {
        if ($this->externalUrl !== null) {
            $res = $this->httpRequest('GET', '/system');
            return in_array($res['status'], [200, 401], true);
        }

        if (! is_resource($this->process)) {
            return false;
        }

        $status = proc_get_status($this->process);
        return (bool) ($status['running'] ?? false);
    }

    public function getUrl(): string
    {
        if ($this->externalUrl !== null) {
            return $this->externalUrl;
        }
        if ($this->port === null) {
            throw new RuntimeException('OrthancTestServer is not running.');
        }
        return 'http://127.0.0.1:' . $this->port;
    }

    public function getStateFile(): string
    {
        return $this->stateFile;
    }

    public function reset(): void
    {
        if ($this->externalUrl !== null) {
            return;
        }
        $res = $this->httpRequest('POST', '/_sim/reset', '{}', ['Content-Type: application/json']);
        if ($res['status'] !== 200) {
            throw new RuntimeException('Failed to reset Orthanc simulator: HTTP ' . $res['status']);
        }
    }

    /**
     * @param array<string, mixed> $faults
     */
    public function setFaults(array $faults): void
    {
        if ($this->externalUrl !== null) {
            return;
        }
        $payload = $faults === [] ? '{}' : json_encode($faults);
        $res     = $this->httpRequest('POST', '/_sim/faults', (string) $payload, ['Content-Type: application/json']);
        if ($res['status'] !== 200) {
            throw new RuntimeException('Failed to set simulator faults: HTTP ' . $res['status']);
        }
    }

    public function clearFaults(): void
    {
        $this->setFaults([]);
    }

    public function setAuth(?string $user, ?string $password): void
    {
        $state  = $this->getState();
        $faults = is_array($state['faults'] ?? null) ? $state['faults'] : [];
        if ($user === null || $user === '') {
            unset($faults['auth']);
        } else {
            $faults['auth'] = ['user' => $user, 'password' => (string) $password];
        }
        $this->setFaults($faults);
    }

    /**
     * @param array<string, mixed> $study
     *
     * @return array<string, mixed>
     */
    public function seedStudy(array $study): array
    {
        if ($this->externalUrl !== null) {
            return $study;
        }
        $res = $this->httpRequest('POST', '/_sim/seed', (string) json_encode($study), ['Content-Type: application/json']);
        if ($res['status'] !== 200) {
            throw new RuntimeException('Failed to seed study in simulator: HTTP ' . $res['status']);
        }
        $decoded = json_decode($res['body'], true);
        return is_array($decoded['seeded'][0] ?? null) ? $decoded['seeded'][0] : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        if ($this->externalUrl !== null) {
            return [];
        }
        $res     = $this->httpRequest('GET', '/_sim/state');
        $decoded = json_decode($res['body'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getModalities(): array
    {
        $state = $this->getState();
        return is_array($state['modalities'] ?? null) ? $state['modalities'] : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStudies(): array
    {
        $state = $this->getState();
        return is_array($state['studies'] ?? null) ? $state['studies'] : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRequests(): array
    {
        $state = $this->getState();
        return is_array($state['requests'] ?? null) ? $state['requests'] : [];
    }

    /**
     * @param list<string> $headers
     *
     * @return array{status: int, body: string}
     */
    public function httpRequest(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $url = $this->getUrl() . $path;
        $ch  = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => ''];
        }

        $opts = [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if ($headers !== []) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $opts);
        $respBody = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body'   => is_string($respBody) ? $respBody : '',
        ];
    }

    private function allocateEphemeralPort(): int
    {
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            throw new RuntimeException("Unable to allocate ephemeral TCP port: {$errstr} ({$errno})");
        }
        $name = stream_socket_get_name($sock, false);
        fclose($sock);

        if (! is_string($name) || ! str_contains($name, ':')) {
            throw new RuntimeException('Unable to parse allocated socket name.');
        }

        return (int) substr(strrchr($name, ':'), 1);
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            if (! $this->isRunning()) {
                $err = $this->stderrLog !== null && is_file($this->stderrLog)
                    ? trim((string) file_get_contents($this->stderrLog))
                    : '';
                throw new RuntimeException('Orthanc simulator exited prematurely. ' . $err);
            }

            $res = $this->httpRequest('GET', '/system');
            if (in_array($res['status'], [200, 401, 503], true)) {
                return;
            }
            usleep(15_000);
        }

        throw new RuntimeException('Timed out waiting for OrthancTestServer readiness on ' . $this->getUrl());
    }
}
