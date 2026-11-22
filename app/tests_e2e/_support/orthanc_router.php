<?php

declare(strict_types=1);

/**
 * Stateful local Orthanc HTTP simulator router for PHP's built-in server.
 *
 * State is persisted in ORTHANC_FAKE_STATE_FILE with flock() protection so
 * modalities, studies, faults, and request logs survive restarts.
 */

$stateFile = getenv('ORTHANC_FAKE_STATE_FILE');
if (! is_string($stateFile) || $stateFile === '') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ORTHANC_FAKE_STATE_FILE not configured']);
    return true;
}

$method     = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsed     = parse_url($requestUri);
$path       = $parsed['path'] ?? '/';
$queryStr   = $parsed['query'] ?? '';
$rawBody    = file_get_contents('php://input');
if (! is_string($rawBody)) {
    $rawBody = '';
}

$headers = [];
foreach ($_SERVER as $key => $val) {
    if (str_starts_with($key, 'HTTP_')) {
        $name           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
        $headers[$name] = (string) $val;
    } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
        $name           = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
        $headers[$name] = (string) $val;
    }
}

$sendJson = static function (int $status, mixed $payload, array $extraHeaders = []): bool {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    foreach ($extraHeaders as $hName => $hVal) {
        header($hName . ': ' . $hVal);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
};

$loadState = static function ($fp): array {
    rewind($fp);
    $raw = stream_get_contents($fp);
    if (! is_string($raw) || trim($raw) === '') {
        return [
            'modalities' => [],
            'studies'    => [],
            'instances'  => [],
            'faults'     => [],
            'requests'   => [],
        ];
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        return [
            'modalities' => [],
            'studies'    => [],
            'instances'  => [],
            'faults'     => [],
            'requests'   => [],
        ];
    }

    return [
        'modalities' => is_array($decoded['modalities'] ?? null) ? $decoded['modalities'] : [],
        'studies'    => is_array($decoded['studies'] ?? null) ? $decoded['studies'] : [],
        'instances'  => is_array($decoded['instances'] ?? null) ? $decoded['instances'] : [],
        'faults'     => is_array($decoded['faults'] ?? null) ? $decoded['faults'] : [],
        'requests'   => is_array($decoded['requests'] ?? null) ? $decoded['requests'] : [],
    ];
};

$saveState = static function ($fp, array $state): void {
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
};

$parseDicomTags = static function (string $binary): array {
    $tags = [];
    if (strlen($binary) < 132 || substr($binary, 128, 4) !== 'DICM') {
        return $tags;
    }

    $pos = 132;
    $len = strlen($binary);
    $wanted = [
        '0008,0018' => 'SOPInstanceUID',
        '0008,0020' => 'StudyDate',
        '0008,0060' => 'Modality',
        '0008,1030' => 'StudyDescription',
        '0010,0010' => 'PatientName',
        '0010,0020' => 'PatientID',
        '0020,000D' => 'StudyInstanceUID',
        '0020,000E' => 'SeriesInstanceUID',
    ];
    $longVRs = ['OB', 'OD', 'OF', 'OL', 'OW', 'SQ', 'UC', 'UN', 'UR', 'UT'];

    while ($pos + 8 <= $len) {
        $group   = unpack('v', substr($binary, $pos, 2))[1];
        $element = unpack('v', substr($binary, $pos + 2, 2))[1];
        $key     = sprintf('%04X,%04X', $group, $element);
        $vr      = substr($binary, $pos + 4, 2);

        if (ctype_alpha($vr[0] ?? '') && ctype_alpha($vr[1] ?? '')) {
            if (in_array($vr, $longVRs, true)) {
                if ($pos + 12 > $len) {
                    break;
                }
                $valLen = unpack('V', substr($binary, $pos + 8, 4))[1];
                $pos += 12;
            } else {
                $valLen = unpack('v', substr($binary, $pos + 6, 2))[1];
                $pos += 8;
            }
        } else {
            // Implicit VR fallback
            $valLen = unpack('V', substr($binary, $pos + 4, 4))[1];
            $pos += 8;
        }

        if ($valLen === 0xFFFFFFFF || $valLen < 0 || $pos + $valLen > $len) {
            break;
        }

        $value = substr($binary, $pos, $valLen);
        $pos += $valLen;

        if (isset($wanted[$key])) {
            $cleaned = trim(rtrim($value, "\0 "));
            if ($cleaned !== '') {
                $tags[$wanted[$key]] = $cleaned;
            }
        }
    }

    return $tags;
};

$normalizeStudy = static function (array $input): array {
    $studyUid = (string) ($input['MainDicomTags']['StudyInstanceUID'] ?? $input['StudyInstanceUID'] ?? ('1.2.826.0.1.3680043.9.' . substr(md5(json_encode($input)), 0, 12)));
    $studyId  = (string) ($input['ID'] ?? substr(sha1($studyUid), 0, 16));
    $modality = (string) ($input['RequestedTags']['ModalitiesInStudy'] ?? $input['Modality'] ?? 'CT');
    $seriesId = (string) ($input['Series'][0] ?? substr(sha1($studyUid . ':series'), 0, 16));

    return [
        'ID'                   => $studyId,
        'Type'                 => 'Study',
        'IsStable'             => true,
        'MainDicomTags'        => [
            'StudyInstanceUID' => $studyUid,
            'StudyDate'        => (string) ($input['MainDicomTags']['StudyDate'] ?? $input['StudyDate'] ?? date('Ymd')),
            'StudyDescription' => (string) ($input['MainDicomTags']['StudyDescription'] ?? $input['StudyDescription'] ?? 'Estudio DICOM'),
        ],
        'PatientMainDicomTags' => [
            'PatientID'   => (string) ($input['PatientMainDicomTags']['PatientID'] ?? $input['PatientID'] ?? 'PAT-' . substr($studyId, 0, 6)),
            'PatientName' => (string) ($input['PatientMainDicomTags']['PatientName'] ?? $input['PatientName'] ?? 'Paciente Demo'),
        ],
        'RequestedTags'        => [
            'ModalitiesInStudy' => $modality,
        ],
        'Series'               => is_array($input['Series'] ?? null) ? $input['Series'] : [$seriesId],
    ];
};

$wildcardMatch = static function (string $pattern, string $value, bool $caseSensitive): bool {
    if ($pattern === '' || $pattern === '*') {
        return true;
    }
    $quoted = preg_quote($pattern, '/');
    $regex  = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], $quoted) . '$/' . ($caseSensitive ? '' : 'i');
    return preg_match($regex, $value) === 1;
};

$dateMatch = static function (string $filter, string $studyDate): bool {
    $filter = trim($filter);
    if ($filter === '') {
        return true;
    }
    if (str_contains($filter, '-')) {
        [$from, $to] = explode('-', $filter, 2);
        if ($from !== '' && $studyDate < $from) {
            return false;
        }
        if ($to !== '' && $studyDate > $to) {
            return false;
        }
        return true;
    }
    return $studyDate === $filter;
};

$fp = fopen($stateFile, 'c+');
if ($fp === false) {
    return $sendJson(500, ['error' => 'Cannot open state file']);
}

if (! flock($fp, LOCK_EX)) {
    fclose($fp);
    return $sendJson(500, ['error' => 'Cannot lock state file']);
}

try {
    $state = $loadState($fp);

    // -----------------------------------------------------------------
    // Simulator control endpoints (/_sim/*) - bypass Basic Auth & faults
    // -----------------------------------------------------------------
    if (str_starts_with($path, '/_sim/')) {
        if ($path === '/_sim/state' && $method === 'GET') {
            $out               = $state;
            $out['modalities'] = $state['modalities'] === [] ? new \stdClass() : $state['modalities'];
            $out['studies']    = $state['studies'] === [] ? new \stdClass() : $state['studies'];
            $out['faults']     = $state['faults'] === [] ? new \stdClass() : $state['faults'];
            return $sendJson(200, $out);
        }

        if ($path === '/_sim/reset' && $method === 'POST') {
            $decoded    = json_decode($rawBody, true);
            $keepFaults = is_array($decoded) && ! empty($decoded['keep_faults']);
            $state      = [
                'modalities' => [],
                'studies'    => [],
                'instances'  => [],
                'faults'     => $keepFaults ? $state['faults'] : [],
                'requests'   => [],
            ];
            $saveState($fp, $state);
            return $sendJson(200, ['ok' => true]);
        }

        if ($path === '/_sim/faults' && $method === 'POST') {
            $decoded = json_decode($rawBody, true);
            if (! is_array($decoded)) {
                return $sendJson(400, ['error' => 'Invalid JSON']);
            }
            $state['faults'] = $decoded;
            $saveState($fp, $state);
            return $sendJson(200, ['ok' => true, 'faults' => $decoded === [] ? new \stdClass() : $decoded]);
        }

        if ($path === '/_sim/seed' && $method === 'POST') {
            $decoded = json_decode($rawBody, true);
            if (! is_array($decoded)) {
                return $sendJson(400, ['error' => 'Invalid JSON']);
            }
            $items  = isset($decoded['studies']) && is_array($decoded['studies']) ? $decoded['studies'] : [$decoded];
            $seeded = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $norm = $normalizeStudy($item);
                $state['studies'][$norm['ID']] = $norm;
                $seeded[] = $norm;
            }
            $saveState($fp, $state);
            return $sendJson(200, ['ok' => true, 'seeded' => $seeded]);
        }

        return $sendJson(404, ['error' => 'Unknown _sim endpoint']);
    }

    // Record non-control request
    $state['requests'][] = [
        'method'    => $method,
        'path'      => $path,
        'query'     => $queryStr,
        'headers'   => $headers,
        'body'      => strlen($rawBody) > 4096 ? substr($rawBody, 0, 4096) : $rawBody,
        'timestamp' => microtime(true),
    ];
    if (count($state['requests']) > 500) {
        $state['requests'] = array_slice($state['requests'], -500);
    }
    $saveState($fp, $state);

    // -----------------------------------------------------------------
    // Optional Basic Auth enforcement
    // -----------------------------------------------------------------
    $authCfg = $state['faults']['auth'] ?? null;
    if (is_array($authCfg) && ! empty($authCfg['user'])) {
        $expectedUser = (string) $authCfg['user'];
        $expectedPass = (string) ($authCfg['password'] ?? '');
        $authHeader   = $headers['Authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $authed       = false;

        if (str_starts_with($authHeader, 'Basic ')) {
            $decodedCreds = base64_decode(substr($authHeader, 6), true);
            if (is_string($decodedCreds) && str_contains($decodedCreds, ':')) {
                [$u, $p] = explode(':', $decodedCreds, 2);
                $authed  = ($u === $expectedUser && $p === $expectedPass);
            }
        } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
            $authed = ($_SERVER['PHP_AUTH_USER'] === $expectedUser && ($_SERVER['PHP_AUTH_PW'] ?? '') === $expectedPass);
        }

        if (! $authed) {
            return $sendJson(401, ['HttpError' => 'Unauthorized', 'HttpStatus' => 401], [
                'WWW-Authenticate' => 'Basic realm="Orthanc"',
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Orthanc System & Statistics
    // -----------------------------------------------------------------
    if ($path === '/system' && $method === 'GET') {
        $sysFault = $state['faults']['system'] ?? null;
        if ($sysFault !== null && $sysFault !== false && $sysFault !== 'ok') {
            $code = is_int($sysFault) ? $sysFault : (int) ($sysFault['status'] ?? 503);
            return $sendJson($code, ['HttpError' => 'Service Unavailable', 'HttpStatus' => $code]);
        }

        return $sendJson(200, [
            'ApiVersion' => 20,
            'DicomAet'   => 'PACSONLINE',
            'DicomPort'  => 4242,
            'Name'       => 'PACS_ONLINE',
            'Version'    => '1.12.4',
        ]);
    }

    if ($path === '/statistics' && $method === 'GET') {
        $studyCount    = count($state['studies']);
        $seriesCount   = 0;
        foreach ($state['studies'] as $st) {
            $seriesCount += count($st['Series'] ?? []);
        }
        $instanceCount = max($studyCount, count($state['instances']));

        return $sendJson(200, [
            'CountPatients'  => $studyCount,
            'CountStudies'   => $studyCount,
            'CountSeries'    => $seriesCount,
            'CountInstances' => $instanceCount,
            'TotalDiskSize'  => (string) ($instanceCount * 1024),
        ]);
    }

    // -----------------------------------------------------------------
    // Studies & Instances
    // -----------------------------------------------------------------
    if ($path === '/studies' && $method === 'GET') {
        if (isset($_GET['expand'])) {
            return $sendJson(200, array_values($state['studies']));
        }
        return $sendJson(200, array_keys($state['studies']));
    }

    if (preg_match('#^/studies/([^/]+)/archive$#', $path, $m) && $method === 'GET') {
        $studyId = urldecode($m[1]);
        if (! isset($state['studies'][$studyId])) {
            return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'orthanc_zip_');
        if ($tmpZip === false) {
            return $sendJson(500, ['error' => 'Cannot create temp archive']);
        }
        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tmpZip);
            return $sendJson(500, ['error' => 'Cannot open zip']);
        }
        $zip->addFromString('study_' . $studyId . '/manifest.json', json_encode($state['studies'][$studyId]));
        $zip->close();
        $bytes = (string) file_get_contents($tmpZip);
        @unlink($tmpZip);

        http_response_code(200);
        header('Content-Type: application/zip');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        return true;
    }

    if (preg_match('#^/studies/([^/]+)$#', $path, $m)) {
        $studyId = urldecode($m[1]);
        if ($method === 'GET') {
            if (! isset($state['studies'][$studyId])) {
                return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
            }
            return $sendJson(200, $state['studies'][$studyId]);
        }
        if ($method === 'DELETE') {
            if (! isset($state['studies'][$studyId])) {
                return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
            }
            unset($state['studies'][$studyId]);
            foreach ($state['instances'] as $instId => $inst) {
                if (($inst['ParentStudy'] ?? '') === $studyId) {
                    unset($state['instances'][$instId]);
                }
            }
            $saveState($fp, $state);
            return $sendJson(200, new \stdClass());
        }
    }

    if ($path === '/tools/find' && $method === 'POST') {
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400]);
        }

        $query         = is_array($payload['Query'] ?? null) ? $payload['Query'] : [];
        $expand        = ! empty($payload['Expand']);
        $caseSensitive = (bool) ($payload['CaseSensitive'] ?? true);
        $since         = max(0, (int) ($payload['Since'] ?? 0));
        $limit         = (int) ($payload['Limit'] ?? 0);

        $matches = [];
        foreach ($state['studies'] as $study) {
            $ok = true;
            foreach ($query as $tag => $val) {
                $val = (string) $val;
                if ($tag === 'StudyInstanceUID') {
                    if (! $wildcardMatch($val, (string) ($study['MainDicomTags']['StudyInstanceUID'] ?? ''), true)) {
                        $ok = false;
                        break;
                    }
                } elseif ($tag === 'PatientName') {
                    if (! $wildcardMatch($val, (string) ($study['PatientMainDicomTags']['PatientName'] ?? ''), $caseSensitive)) {
                        $ok = false;
                        break;
                    }
                } elseif ($tag === 'PatientID') {
                    if (! $wildcardMatch($val, (string) ($study['PatientMainDicomTags']['PatientID'] ?? ''), $caseSensitive)) {
                        $ok = false;
                        break;
                    }
                } elseif ($tag === 'StudyDescription') {
                    if (! $wildcardMatch($val, (string) ($study['MainDicomTags']['StudyDescription'] ?? ''), $caseSensitive)) {
                        $ok = false;
                        break;
                    }
                } elseif ($tag === 'StudyDate') {
                    if (! $dateMatch($val, (string) ($study['MainDicomTags']['StudyDate'] ?? ''))) {
                        $ok = false;
                        break;
                    }
                } elseif ($tag === 'ModalitiesInStudy') {
                    if (! $wildcardMatch($val, (string) ($study['RequestedTags']['ModalitiesInStudy'] ?? ''), $caseSensitive)) {
                        $ok = false;
                        break;
                    }
                }
            }
            if ($ok) {
                $matches[] = $study;
            }
        }

        // OrderBy StudyDate ASC/DESC
        $direction = 'DESC';
        if (! empty($payload['OrderBy']) && is_array($payload['OrderBy'])) {
            $firstOrder = $payload['OrderBy'][0] ?? [];
            $direction  = strtoupper((string) ($firstOrder['Direction'] ?? 'DESC'));
        }
        usort($matches, static function (array $a, array $b) use ($direction): int {
            $da  = (string) ($a['MainDicomTags']['StudyDate'] ?? '');
            $db  = (string) ($b['MainDicomTags']['StudyDate'] ?? '');
            $cmp = strcmp($da, $db);
            return $direction === 'ASC' ? $cmp : -$cmp;
        });

        if ($since > 0) {
            $matches = array_slice($matches, $since);
        }
        if ($limit > 0) {
            $matches = array_slice($matches, 0, $limit);
        }

        if ($expand) {
            return $sendJson(200, array_values($matches));
        }
        return $sendJson(200, array_map(static fn (array $s): string => (string) $s['ID'], $matches));
    }

    if ($path === '/instances' && $method === 'POST') {
        $instFault = $state['faults']['instances'] ?? null;
        if ($instFault !== null && $instFault !== false && $instFault !== 'ok') {
            $code = is_int($instFault) ? $instFault : (int) ($instFault['status'] ?? 400);
            return $sendJson($code, ['HttpError' => 'Instance Rejected', 'HttpStatus' => $code]);
        }

        $binary = $rawBody;
        if ($binary === '' && isset($_FILES['file']['tmp_name']) && is_file($_FILES['file']['tmp_name'])) {
            $binary = (string) file_get_contents($_FILES['file']['tmp_name']);
        }

        if ($binary === '' || str_starts_with($binary, 'INVALID_DICOM')) {
            return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400, 'Message' => 'Bad file format']);
        }

        $parsedTags = $parseDicomTags($binary);
        $hash       = sha1($binary);
        $studyUid   = $parsedTags['StudyInstanceUID'] ?? ('1.2.826.0.1.3680043.10.' . hexdec(substr($hash, 0, 8)));
        $studyId    = substr(sha1($studyUid), 0, 16);
        $seriesUid  = $parsedTags['SeriesInstanceUID'] ?? ($studyUid . '.1');
        $seriesId   = substr(sha1($seriesUid), 0, 16);
        $patientId  = substr(sha1($parsedTags['PatientID'] ?? $studyId), 0, 16);
        $instanceId = substr(sha1(($parsedTags['SOPInstanceUID'] ?? $hash)), 0, 16);

        if (! isset($state['studies'][$studyId])) {
            $state['studies'][$studyId] = $normalizeStudy([
                'ID'               => $studyId,
                'StudyInstanceUID' => $studyUid,
                'StudyDate'        => $parsedTags['StudyDate'] ?? date('Ymd'),
                'StudyDescription' => $parsedTags['StudyDescription'] ?? 'Estudio Ingestado',
                'PatientID'        => $parsedTags['PatientID'] ?? ('PAC-' . strtoupper(substr($studyId, 0, 5))),
                'PatientName'      => $parsedTags['PatientName'] ?? 'Paciente Ingestado',
                'Modality'         => $parsedTags['Modality'] ?? 'CT',
                'Series'           => [$seriesId],
            ]);
        }

        $state['instances'][$instanceId] = [
            'ID'            => $instanceId,
            'ParentStudy'   => $studyId,
            'ParentSeries'  => $seriesId,
            'ParentPatient' => $patientId,
            'Size'          => strlen($binary),
        ];
        $saveState($fp, $state);

        return $sendJson(200, [
            'ID'            => $instanceId,
            'ParentPatient' => $patientId,
            'ParentSeries'  => $seriesId,
            'ParentStudy'   => $studyId,
            'Path'          => '/instances/' . $instanceId,
            'Status'        => 'Success',
        ]);
    }

    // -----------------------------------------------------------------
    // Modalities CRUD, C-ECHO & C-STORE
    // -----------------------------------------------------------------
    if ($path === '/modalities' && $method === 'GET') {
        if (isset($_GET['expand'])) {
            return $sendJson(200, $state['modalities'] === [] ? new \stdClass() : $state['modalities']);
        }
        return $sendJson(200, array_keys($state['modalities']));
    }

    if (preg_match('#^/modalities/([^/]+)/echo$#', $path, $m) && $method === 'POST') {
        $modalityId = urldecode($m[1]);

        // Strict Orthanc >= 1.7.0 rule: POST /modalities/{id}/echo requires a non-empty JSON object
        $trimmed = trim($rawBody);
        if ($trimmed === '' || ! str_starts_with($trimmed, '{')) {
            return $sendJson(400, [
                'HttpError'    => 'Bad Request',
                'HttpStatus'   => 400,
                'Message'      => 'Expected a JSON object body for /echo (e.g. {} or {"Timeout":10})',
                'OrthancError' => 'Bad JSON request',
            ]);
        }
        $echoBody = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($echoBody)) {
            return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400, 'Message' => 'Invalid JSON']);
        }

        if (! isset($state['modalities'][$modalityId])) {
            return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
        }

        $echoFault = $state['faults']['per_modality'][$modalityId]['echo'] ?? ($state['faults']['echo'] ?? null);
        if (is_array($echoFault) && ! empty($echoFault['delay_ms'])) {
            usleep((int) $echoFault['delay_ms'] * 1000);
        }
        if ($echoFault === 'fail_500' || (is_array($echoFault) && ($echoFault['mode'] ?? '') === 'fail_500')) {
            return $sendJson(500, [
                'HttpError'    => 'Internal Server Error',
                'HttpStatus'   => 500,
                'Message'      => 'Error in the network protocol',
                'OrthancError' => 'Error in the network protocol',
            ]);
        }
        if ($echoFault === 'fail_404' || (is_array($echoFault) && ($echoFault['mode'] ?? '') === 'fail_404')) {
            return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
        }
        if ($echoFault === 'timeout' || (is_array($echoFault) && ($echoFault['mode'] ?? '') === 'timeout')) {
            usleep(1_500_000);
            return $sendJson(504, [
                'HttpError'    => 'Gateway Timeout',
                'HttpStatus'   => 504,
                'Message'      => 'DICOM Association timed out',
                'OrthancError' => 'Timeout',
            ]);
        }

        return $sendJson(200, new \stdClass());
    }

    if (preg_match('#^/modalities/([^/]+)/store$#', $path, $m) && $method === 'POST') {
        $modalityId = urldecode($m[1]);
        $decoded    = json_decode($rawBody, true);
        if (! is_array($decoded) || empty($decoded['Resources']) || ! is_array($decoded['Resources'])) {
            return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400, 'Message' => 'Resources array required']);
        }

        if (! isset($state['modalities'][$modalityId])) {
            return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404, 'Message' => 'Unknown modality']);
        }

        $resources = array_values(array_map('strval', $decoded['Resources']));
        foreach ($resources as $resId) {
            if (! isset($state['studies'][$resId])) {
                return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404, 'Message' => 'Unknown resource ' . $resId]);
            }
        }

        $remoteAet  = (string) ($state['modalities'][$modalityId]['AET'] ?? $modalityId);
        $storeFault = $state['faults']['per_modality'][$modalityId]['store'] ?? ($state['faults']['store'] ?? null);
        $mode       = is_array($storeFault) ? ($storeFault['mode'] ?? '') : (string) $storeFault;

        if ($mode === 'fail_500') {
            return $sendJson(500, [
                'HttpError'    => 'Internal Server Error',
                'HttpStatus'   => 500,
                'Message'      => 'Error in the network protocol',
                'OrthancError' => 'Error in the network protocol',
            ]);
        }

        if ($mode === 'partial_fail') {
            return $sendJson(200, [
                'Description'          => 'REST API',
                'FailedInstancesCount' => 1,
                'InstancesCount'       => 2,
                'LocalAet'             => 'PACSONLINE',
                'ParentResources'      => $resources,
                'RemoteAet'            => $remoteAet,
            ]);
        }

        if ($mode === 'zero_instances') {
            return $sendJson(200, [
                'Description'          => 'REST API',
                'FailedInstancesCount' => 0,
                'InstancesCount'       => 0,
                'LocalAet'             => 'PACSONLINE',
                'ParentResources'      => $resources,
                'RemoteAet'            => $remoteAet,
            ]);
        }

        $instCount = 0;
        foreach ($state['instances'] as $inst) {
            if (in_array((string) ($inst['ParentStudy'] ?? ''), $resources, true)) {
                $instCount++;
            }
        }
        $instCount = max(count($resources), $instCount);

        return $sendJson(200, [
            'Description'          => 'REST API',
            'FailedInstancesCount' => 0,
            'InstancesCount'       => $instCount,
            'LocalAet'             => 'PACSONLINE',
            'ParentResources'      => $resources,
            'RemoteAet'            => $remoteAet,
        ]);
    }

    if (preg_match('#^/modalities/([^/]+)$#', $path, $m)) {
        $modalityId = urldecode($m[1]);

        if ($method === 'GET') {
            if (! isset($state['modalities'][$modalityId])) {
                return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
            }
            return $sendJson(200, $state['modalities'][$modalityId]);
        }

        if ($method === 'PUT') {
            $putFault = $state['faults']['modality_put'] ?? null;
            if ($putFault !== null && $putFault !== false && $putFault !== 'ok') {
                $code = is_int($putFault) ? $putFault : (int) ($putFault['status'] ?? 503);
                return $sendJson($code, ['HttpError' => 'Orthanc Modality Put Error', 'HttpStatus' => $code]);
            }

            $decoded = json_decode($rawBody, true);
            if (! is_array($decoded) || empty($decoded['AET']) || empty($decoded['Host']) || ! isset($decoded['Port'])) {
                return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400, 'Message' => 'AET, Host, Port required']);
            }
            $port = (int) $decoded['Port'];
            if ($port < 1 || $port > 65535) {
                return $sendJson(400, ['HttpError' => 'Bad Request', 'HttpStatus' => 400, 'Message' => 'Invalid TCP Port']);
            }

            $state['modalities'][$modalityId] = [
                'AET'          => (string) $decoded['AET'],
                'Host'         => (string) $decoded['Host'],
                'Port'         => $port,
                'Manufacturer' => (string) ($decoded['Manufacturer'] ?? 'Generic'),
                'AllowEcho'    => (bool) ($decoded['AllowEcho'] ?? true),
                'AllowStore'   => (bool) ($decoded['AllowStore'] ?? true),
            ];
            $saveState($fp, $state);
            return $sendJson(200, new \stdClass());
        }

        if ($method === 'DELETE') {
            $delFault = $state['faults']['modality_delete'] ?? null;
            if ($delFault !== null && $delFault !== false && $delFault !== 'ok') {
                $code = is_int($delFault) ? $delFault : (int) ($delFault['status'] ?? 503);
                return $sendJson($code, ['HttpError' => 'Orthanc Modality Delete Error', 'HttpStatus' => $code]);
            }

            if (! isset($state['modalities'][$modalityId])) {
                return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404]);
            }
            unset($state['modalities'][$modalityId]);
            $saveState($fp, $state);
            return $sendJson(200, new \stdClass());
        }
    }

    return $sendJson(404, ['HttpError' => 'Not Found', 'HttpStatus' => 404, 'Path' => $path]);
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
