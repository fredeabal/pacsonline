<?php

declare(strict_types=1);

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use ZipArchive;

/**
 * Cliente HTTP para comunicarse con el servidor PACS Orthanc.
 */
class OrthancClient
{
    private string $baseUrl;
    private CURLRequest $http;
    private ?string $username = null;
    private ?string $password = null;

    public function __construct(?CURLRequest $client = null, ?string $baseUrl = null)
    {
        $resolvedUrl   = $baseUrl !== null && trim($baseUrl) !== '' ? trim($baseUrl) : $this->readEnv('ORTHANC_URL');
        $this->baseUrl = rtrim($resolvedUrl !== '' ? $resolvedUrl : 'http://orthanc:8042', '/');

        $user = $this->readEnv('ORTHANC_USER');
        $pass = $this->readEnv('ORTHANC_PASSWORD');
        if ($user !== '' && $pass !== '') {
            $this->username = $user;
            $this->password = $pass;
        }

        $this->http = $client ?? Services::curlrequest($this->defaultClientOptions(), null, null, false);
    }

    public function isAlive(): bool
    {
        try {
            $res = $this->http->request('GET', $this->baseUrl . '/system', $this->requestOptions());
            return $res->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Lista todos los estudios almacenados en Orthanc con sus etiquetas principales.
     *
     * @return list<array<string, mixed>>
     */
    public function listStudies(): array
    {
        try {
            $res = $this->http->request(
                'GET',
                $this->baseUrl . '/studies?expand&requestedTags=ModalitiesInStudy',
                $this->requestOptions()
            );
            if ($res->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Devuelve las estadísticas globales indexadas en Orthanc (/statistics).
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        try {
            $res = $this->http->request('GET', $this->baseUrl . '/statistics', $this->requestOptions());
            if ($res->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Devuelve el total de estudios indexados en Orthanc (/statistics).
     */
    public function countStudies(): int
    {
        $stats = $this->getStatistics();
        return (int) ($stats['CountStudies'] ?? 0);
    }

    /**
     * Lista estudios paginados desde Orthanc usando /tools/find (ordenados por StudyDate DESC por defecto).
     *
     * @param array<string, string> $query
     *
     * @return list<array<string, mixed>>
     */
    public function findStudiesPage(int $since = 0, int $limit = 25, array $query = [], bool $orderByDate = true): array
    {
        try {
            $payload = [
                'Level'         => 'Study',
                'Expand'        => true,
                'Since'         => max(0, $since),
                'Limit'         => max(1, $limit),
                'Query'         => $query === [] ? new \stdClass() : $query,
                'CaseSensitive' => false,
            ];

            if ($orderByDate) {
                $payload['OrderBy'] = [
                    [
                        'Type'      => 'DicomTag',
                        'Key'       => 'StudyDate',
                        'Direction' => 'DESC',
                    ],
                ];
            }

            // Evita calcular ModalitiesInStudy para todo el índice en listados sin filtro de modalidad
            if (isset($query['ModalitiesInStudy'])) {
                $payload['RequestedTags'] = ['ModalitiesInStudy'];
            }

            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/tools/find',
                $this->requestOptions(['json' => $payload])
            );

            if ($res->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Busca estudios paginados en Orthanc combinando múltiples etiquetas DICOM (OR lógico)
     * cuando hay texto de búsqueda, manteniendo orden por StudyDate DESC y sin duplicados.
     *
     * @param array<string, string> $baseQuery
     *
     * @return list<array<string, mixed>>
     */
    public function searchStudiesPage(int $since, int $limit, string $search, array $baseQuery = []): array
    {
        $search = trim($search);
        if ($search === '') {
            return $this->findStudiesPage($since, $limit, $baseQuery);
        }

        $tagKeys = preg_match('/\d/', $search) === 1
            ? ['PatientID', 'AccessionNumber']
            : ['PatientName', 'AccessionNumber'];

        $fetchLimit = max(1, $since + $limit);
        $byId       = [];

        foreach ($tagKeys as $tagKey) {
            $query          = $baseQuery;
            $query[$tagKey] = '*' . $search . '*';
            // Sin OrderBy en Orthanc para evitar full-scan con wildcards; ordenamos en PHP.
            foreach ($this->findStudiesPage(0, $fetchLimit, $query, false) as $study) {
                $id = (string) ($study['ID'] ?? '');
                if ($id !== '' && ! isset($byId[$id])) {
                    $byId[$id] = $study;
                }
            }
        }

        $results = array_values($byId);
        usort($results, static function (array $a, array $b): int {
            $dateA = (string) ($a['MainDicomTags']['StudyDate'] ?? '');
            $dateB = (string) ($b['MainDicomTags']['StudyDate'] ?? '');
            return strcmp($dateB, $dateA);
        });

        return array_slice($results, max(0, $since), max(1, $limit));
    }

    /**
     * Obtiene el detalle de un paciente desde Orthanc (/patients/{id}).
     */
    public function getPatient(string $orthancPatientId): ?array
    {
        return $this->getJson('/patients/' . rawurlencode($orthancPatientId));
    }

    /**
     * Busca un paciente en Orthanc por su etiqueta DICOM PatientID exacta (/tools/find).
     */
    public function findPatientByPatientId(string $patientId): ?array
    {
        $clean = trim($patientId);
        if ($clean === '') {
            return null;
        }

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/tools/find',
                $this->requestOptions([
                    'json' => [
                        'Level'         => 'Patient',
                        'Expand'        => true,
                        'Query'         => ['PatientID' => $clean],
                        'CaseSensitive' => true,
                        'Limit'         => 2,
                    ],
                ])
            );
            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $res->getBody(), true);
            if (! is_array($data)) {
                return null;
            }

            foreach ($data as $patient) {
                if (is_array($patient) && trim((string) ($patient['MainDicomTags']['PatientID'] ?? '')) === $clean) {
                    return $patient;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Busca otros estudios del mismo paciente en Orthanc por PatientID exacto (/tools/find),
     * excluyendo opcionalmente el estudio actual y ordenando del más reciente al más antiguo.
     *
     * @return list<array<string, mixed>>
     */
    public function findStudiesByPatientId(string $patientId, string $excludeStudyUid = '', int $limit = 20): array
    {
        $clean = trim($patientId);
        if ($clean === '' || in_array(strtoupper($clean), ['-', '0', 'UNKNOWN', 'ANONYMOUS', 'ANON', 'N/A', 'NA', 'NONE'], true)) {
            return [];
        }

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/tools/find',
                $this->requestOptions([
                    'json' => [
                        'Level'         => 'Study',
                        'Expand'        => true,
                        'Query'         => ['PatientID' => $clean],
                        'CaseSensitive' => false,
                        'RequestedTags' => ['ModalitiesInStudy'],
                        'Limit'         => max(1, $limit + 1),
                        'OrderBy'       => [
                            [
                                'Type'      => 'DicomTag',
                                'Key'       => 'StudyDate',
                                'Direction' => 'DESC',
                            ],
                        ],
                    ],
                ])
            );

            if ($res->getStatusCode() !== 200) {
                return [];
            }

            $data = json_decode((string) $res->getBody(), true);
            if (! is_array($data)) {
                return [];
            }

            $results = [];
            foreach ($data as $study) {
                if (! is_array($study)) {
                    continue;
                }

                $foundPatientId = trim((string) ($study['PatientMainDicomTags']['PatientID'] ?? ''));
                if ($foundPatientId === '' || strcasecmp($foundPatientId, $clean) !== 0) {
                    continue;
                }

                $uid = trim((string) ($study['MainDicomTags']['StudyInstanceUID'] ?? ''));
                if ($uid === '' || ($excludeStudyUid !== '' && $uid === $excludeStudyUid)) {
                    continue;
                }

                $results[] = $study;
            }

            usort($results, static function (array $a, array $b): int {
                $dateA = (string) ($a['MainDicomTags']['StudyDate'] ?? '');
                $dateB = (string) ($b['MainDicomTags']['StudyDate'] ?? '');
                $cmp   = strcmp($dateB, $dateA);
                if ($cmp !== 0) {
                    return $cmp;
                }

                $timeA = (string) ($a['MainDicomTags']['StudyTime'] ?? '');
                $timeB = (string) ($b['MainDicomTags']['StudyTime'] ?? '');

                return strcmp($timeB, $timeA);
            });

            return array_slice($results, 0, max(1, $limit));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Completa RequestedTags.ModalitiesInStudy para un lote pequeño de estudios (página actual)
     * usando caché (24h) y peticiones paralelas curl_multi solo para los no cacheados.
     * Evita pedir ModalitiesInStudy en /tools/find sobre todo el índice.
     *
     * @param list<array<string, mixed>> $studies
     *
     * @return list<array<string, mixed>>
     */
    public function enrichStudiesModalities(array $studies): array
    {
        if ($studies === []) {
            return [];
        }

        $cache   = Services::cache();
        $missing = [];

        foreach ($studies as $idx => $study) {
            $existing = $study['RequestedTags']['ModalitiesInStudy'] ?? '';
            if (is_string($existing) && trim($existing) !== '') {
                continue;
            }

            $orthancId = trim((string) ($study['ID'] ?? ''));
            if ($orthancId === '') {
                continue;
            }

            $cacheKey = 'orthanc_mod_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $orthancId);
            $cached   = $cache->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $studies[$idx]['RequestedTags']['ModalitiesInStudy'] = $cached;
                continue;
            }

            $missing[$idx] = [
                'id'        => $orthancId,
                'cache_key' => $cacheKey,
            ];
        }

        if ($missing === []) {
            return $studies;
        }

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($missing as $idx => $info) {
            $url = $this->baseUrl . '/studies/' . rawurlencode($info['id']) . '?requestedTags=ModalitiesInStudy';
            $ch  = curl_init($url);
            if ($ch === false) {
                continue;
            }

            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 5,
            ];
            if ($this->username !== null && $this->password !== null) {
                $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                $opts[CURLOPT_USERPWD]  = $this->username . ':' . $this->password;
            }

            curl_setopt_array($ch, $opts);
            curl_multi_add_handle($mh, $ch);
            $handles[$idx] = $ch;
        }

        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);
            if ($active && $status === CURLM_OK) {
                curl_multi_select($mh, 0.1);
            }
        } while ($active && $status === CURLM_OK);

        foreach ($handles as $idx => $ch) {
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $body     = (string) curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            if ($httpCode !== 200 || $body === '') {
                continue;
            }

            $decoded = json_decode($body, true);
            $mod     = is_array($decoded) ? ($decoded['RequestedTags']['ModalitiesInStudy'] ?? null) : null;
            if (is_array($mod)) {
                $mod = implode('\\', array_filter(array_map('strval', $mod)));
            }
            if (is_string($mod) && trim($mod) !== '') {
                $cleanMod = trim($mod);
                $studies[$idx]['RequestedTags']['ModalitiesInStudy'] = $cleanMod;
                $resolved = extract_study_modality($studies[$idx]);
                if (! in_array($resolved, ['SR', 'DOC', 'PR', 'KO', 'AU', 'DICOM'], true)) {
                    $cache->save($missing[$idx]['cache_key'], $cleanMod, 86400);
                }
            }
        }

        curl_multi_close($mh);

        return $studies;
    }

    public function getStudy(string $orthancStudyId): ?array
    {
        try {
            $res = $this->http->request(
                'GET',
                $this->baseUrl . '/studies/' . urlencode($orthancStudyId) . '?requestedTags=ModalitiesInStudy',
                $this->requestOptions()
            );
            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Busca un estudio en Orthanc directamente por StudyInstanceUID (/tools/find).
     */
    public function findStudyByUid(string $studyInstanceUid): ?array
    {
        if ($studyInstanceUid === '') {
            return null;
        }

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/tools/find',
                $this->requestOptions([
                    'json' => [
                        'Level'         => 'Study',
                        'Expand'        => true,
                        'Query'         => ['StudyInstanceUID' => $studyInstanceUid],
                        'RequestedTags' => ['ModalitiesInStudy'],
                    ],
                ])
            );

            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $res->getBody(), true);
            if (! is_array($data) || $data === []) {
                return null;
            }

            return is_array($data[0]) ? $data[0] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Obtiene la metadata de una serie en Orthanc (GET /series/{id}).
     */
    public function getSeries(string $orthancSeriesId): ?array
    {
        if ($orthancSeriesId === '') {
            return null;
        }

        return $this->getJson('/series/' . urlencode($orthancSeriesId));
    }

    /**
     * Obtiene la metadata de una instancia en Orthanc (GET /instances/{id}).
     */
    public function getInstance(string $orthancInstanceId): ?array
    {
        if ($orthancInstanceId === '') {
            return null;
        }

        return $this->getJson('/instances/' . urlencode($orthancInstanceId));
    }

    /**
     * Obtiene los tags simplificados de una instancia en Orthanc.
     */
    public function getInstanceSimplifiedTags(string $orthancInstanceId): ?array
    {
        if ($orthancInstanceId === '') {
            return null;
        }

        return $this->getJson('/instances/' . urlencode($orthancInstanceId) . '/simplified-tags');
    }

    /**
     * Descarga el PDF encapsulado de una instancia DICOM (GET /instances/{id}/pdf).
     */
    public function getInstancePdf(string $orthancInstanceId): ?string
    {
        if ($orthancInstanceId === '') {
            return null;
        }

        try {
            $res = $this->http->request(
                'GET',
                $this->baseUrl . '/instances/' . urlencode($orthancInstanceId) . '/pdf',
                $this->requestOptions(['timeout' => 30])
            );
            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $body = (string) $res->getBody();
            return str_starts_with($body, '%PDF') ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Devuelve la lista de series de un estudio en Orthanc.
     *
     * @return list<array<string, mixed>>
     */
    public function getStudySeries(array $orthancStudyInfo): array
    {
        $orthancStudyId = (string) ($orthancStudyInfo['ID'] ?? '');
        $seriesList     = $orthancStudyId !== ''
            ? $this->getJson('/studies/' . urlencode($orthancStudyId) . '/series')
            : null;

        if (! is_array($seriesList)) {
            $seriesIds = $orthancStudyInfo['Series'] ?? [];
            if (! is_array($seriesIds) || $seriesIds === []) {
                return [];
            }

            $seriesList = [];
            foreach ($seriesIds as $seriesId) {
                if (is_string($seriesId) && $seriesId !== '') {
                    $series = $this->getSeries($seriesId);
                    if ($series !== null) {
                        $seriesList[] = $series;
                    }
                }
            }
        }

        return array_values(array_filter($seriesList, 'is_array'));
    }

    /**
     * Cuenta las series de imagen visualizables de un estudio (excluye SR, DOC, PR, KO, Raw Data, etc.).
     */
    public function countImageSeries(array $orthancStudyInfo, ?array $seriesList = null): int
    {
        $seriesList         ??= $this->getStudySeries($orthancStudyInfo);
        $nonImageModalities   = ['SR', 'PR', 'KO', 'DOC', 'SEG', 'RTSTRUCT', 'RTPLAN', 'RTDOSE', 'REG', 'FID', 'AU', 'ECG'];
        $count                = 0;

        foreach ($seriesList as $series) {
            $tags     = $series['MainDicomTags'] ?? [];
            $modality = strtoupper(trim((string) ($tags['Modality'] ?? '')));
            if (in_array($modality, $nonImageModalities, true)) {
                continue;
            }

            $instances = $series['Instances'] ?? [];
            if (! is_array($instances) || $instances === []) {
                continue;
            }

            // Si no tiene descripción de serie, verificamos que la primera instancia tenga dimensiones de imagen (Rows > 0),
            // descartando Raw Data Storage (ej. ExamCard de Philips).
            if (trim((string) ($tags['SeriesDescription'] ?? '')) === '') {
                $firstTags = $this->getInstanceSimplifiedTags((string) $instances[0]);
                if ($firstTags === null || (int) ($firstTags['Rows'] ?? 0) <= 0) {
                    continue;
                }
            }

            $count++;
        }

        return $count;
    }

    /**
     * Lista los documentos PDF encapsulados (Modality=DOC / Encapsulated PDF) de un estudio.
     *
     * @return list<array{instance_id: string, title: string, date: string, size: int|null}>
     */
    public function findStudyDicomDocuments(array $orthancStudyInfo, ?array $seriesList = null): array
    {
        $seriesList ??= $this->getStudySeries($orthancStudyInfo);

        $documents = [];
        foreach ($seriesList as $series) {
            if (! is_array($series)) {
                continue;
            }

            $modality = strtoupper(trim((string) ($series['MainDicomTags']['Modality'] ?? '')));
            if ($modality !== 'DOC') {
                continue;
            }

            $seriesDesc = trim((string) ($series['MainDicomTags']['SeriesDescription'] ?? ''));
            $seriesDate = trim((string) ($series['MainDicomTags']['SeriesDate'] ?? ''));

            foreach (($series['Instances'] ?? []) as $instanceId) {
                if (! is_string($instanceId) || $instanceId === '') {
                    continue;
                }

                $tags = $this->getInstanceSimplifiedTags($instanceId);
                if ($tags === null) {
                    continue;
                }

                $sopClass = trim((string) ($tags['SOPClassUID'] ?? ''));
                $mime     = strtolower(trim((string) ($tags['MIMETypeOfEncapsulatedDocument'] ?? '')));
                if ($sopClass !== '1.2.840.10008.5.1.4.1.1.104.1' && $mime !== 'application/pdf') {
                    continue;
                }

                $docTitle = trim((string) ($tags['DocumentTitle'] ?? ''));
                $title    = $docTitle !== '' ? $docTitle : ($seriesDesc !== '' ? $seriesDesc : 'Informe_DICOM');
                if (! str_ends_with(strtolower($title), '.pdf')) {
                    $title .= '.pdf';
                }

                $date = trim((string) ($tags['ContentDate'] ?? ''));
                if ($date === '') {
                    $date = $seriesDate !== '' ? $seriesDate : trim((string) ($orthancStudyInfo['MainDicomTags']['StudyDate'] ?? ''));
                }

                $instMeta = $this->getInstance($instanceId);
                $fileSize = isset($instMeta['FileSize']) ? (int) $instMeta['FileSize'] : null;

                $documents[] = [
                    'instance_id' => $instanceId,
                    'title'       => $title,
                    'date'        => $date,
                    'size'        => $fileSize,
                ];
            }
        }

        return $documents;
    }

    private function getJson(string $path): ?array
    {
        try {
            $res = $this->http->request(
                'GET',
                $this->baseUrl . $path,
                $this->requestOptions()
            );
            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $data = json_decode((string) $res->getBody(), true);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Modifica etiquetas DICOM de un estudio completo en Orthanc (POST /studies/{id}/modify).
     *
     * @param array<string, string> $replaceTags Mapa de etiquetas DICOM a reemplazar (ej. PatientName, PatientID).
     *
     * @return array{ok: bool, status: int, id: ?string, data: ?array<string, mixed>, error: ?string}
     */
    public function modifyStudy(string $orthancStudyId, array $replaceTags): array
    {
        return $this->modifyResource('/studies/' . rawurlencode(trim($orthancStudyId)) . '/modify', trim($orthancStudyId), $replaceTags);
    }

    /**
     * Modifica etiquetas DICOM de un paciente completo en Orthanc (POST /patients/{id}/modify).
     *
     * @param array<string, string> $replaceTags Mapa de etiquetas DICOM a reemplazar (ej. PatientName, PatientID).
     *
     * @return array{ok: bool, status: int, id: ?string, data: ?array<string, mixed>, error: ?string}
     */
    public function modifyPatient(string $orthancPatientId, array $replaceTags): array
    {
        return $this->modifyResource('/patients/' . rawurlencode(trim($orthancPatientId)) . '/modify', trim($orthancPatientId), $replaceTags);
    }

    /**
     * Consulta el estado de un trabajo en Orthanc (GET /jobs/{id}).
     *
     * @return array<string, mixed>|null
     */
    public function getJob(string $jobId): ?array
    {
        $cleanId = trim($jobId);
        if ($cleanId === '') {
            return null;
        }

        try {
            $res = $this->http->request('GET', $this->baseUrl . '/jobs/' . rawurlencode($cleanId), $this->requestOptions([
                'timeout' => 10,
            ]));

            if ($res->getStatusCode() !== 200) {
                return null;
            }

            $decoded = json_decode((string) $res->getBody(), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cancela un trabajo en ejecución en Orthanc (POST /jobs/{id}/cancel).
     */
    public function cancelJob(string $jobId): bool
    {
        $cleanId = trim($jobId);
        if ($cleanId === '') {
            return false;
        }

        try {
            $res = $this->http->request('POST', $this->baseUrl . '/jobs/' . rawurlencode($cleanId) . '/cancel', $this->requestOptions([
                'timeout' => 10,
            ]));

            return $res->getStatusCode() >= 200 && $res->getStatusCode() < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ejecuta /modify sobre un recurso de Orthanc manteniendo StudyInstanceUID y SeriesInstanceUID.
     * Se usa Synchronous=false para que Orthanc procese las instancias mediante un trabajo en segundo plano,
     * evitando caídas por timeout HTTP en estudios con cientos o miles de imágenes.
     *
     * @param array<string, string> $replaceTags
     *
     * @return array{ok: bool, status: int, id: ?string, data: ?array<string, mixed>, error: ?string, async?: bool}
     */
    private function modifyResource(string $path, string $resourceId, array $replaceTags): array
    {
        if ($resourceId === '' || $replaceTags === []) {
            return [
                'ok'     => false,
                'status' => 0,
                'id'     => null,
                'data'   => null,
                'error'  => 'ID de recurso o etiquetas DICOM vacías.',
            ];
        }

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . $path,
                $this->requestOptions([
                    'json' => [
                        'Replace'     => $replaceTags,
                        'Keep'        => ['StudyInstanceUID', 'SeriesInstanceUID'],
                        'KeepSource'  => false,
                        'Force'       => true,
                        'Synchronous' => false,
                    ],
                    'timeout' => 30,
                ])
            );

            $status  = $res->getStatusCode();
            $rawBody = (string) $res->getBody();
            $decoded = json_decode($rawBody, true);
            $json    = is_array($decoded) ? $decoded : null;

            if ($status < 200 || $status >= 300 || $json === null) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'id'     => null,
                    'data'   => $json,
                    'error'  => $this->extractOrthancError($rawBody, $status),
                ];
            }

            $jobId = (string) ($json['ID'] ?? '');
            if ($jobId === '') {
                $newId = isset($json['ID']) && is_string($json['ID']) && $json['ID'] !== ''
                    ? $json['ID']
                    : $resourceId;

                return [
                    'ok'     => true,
                    'status' => $status,
                    'id'     => $newId,
                    'data'   => $json,
                    'error'  => null,
                ];
            }

            // Esperar hasta 8 segundos para responder de inmediato si el estudio tiene pocas imágenes
            $maxWaitSec = 8;
            $start = microtime(true);
            while ((microtime(true) - $start) < $maxWaitSec) {
                usleep(400000); // 400ms
                $job = $this->getJob($jobId);
                if ($job === null) {
                    continue;
                }

                $state = (string) ($job['State'] ?? '');
                if ($state === 'Success') {
                    $content = is_array($job['Content'] ?? null) ? $job['Content'] : [];
                    $newId   = (string) ($content['ID'] ?? $resourceId);

                    return [
                        'ok'     => true,
                        'status' => 200,
                        'id'     => $newId !== '' ? $newId : $resourceId,
                        'data'   => $content,
                        'error'  => null,
                    ];
                }

                if ($state === 'Failure') {
                    $err = (string) ($job['ErrorDescription'] ?? 'Error desconocido');

                    return [
                        'ok'     => false,
                        'status' => 500,
                        'id'     => null,
                        'data'   => $job,
                        'error'  => 'Error en PACS: ' . $err,
                    ];
                }
            }

            // En estudios con muchas imágenes (> 500), el trabajo sigue corriendo en segundo plano sin fallar
            return [
                'ok'     => true,
                'status' => 202,
                'id'     => $resourceId,
                'data'   => $json,
                'error'  => null,
                'async'  => true,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Orthanc modifyResource failed: ' . $e->getMessage());

            return [
                'ok'     => false,
                'status' => 0,
                'id'     => null,
                'data'   => null,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Elimina un estudio completo de Orthanc (DELETE /studies/{id}).
     */
    public function deleteStudy(string $orthancStudyId): bool
    {
        try {
            $res    = $this->http->request(
                'DELETE',
                $this->baseUrl . '/studies/' . urlencode($orthancStudyId),
                $this->requestOptions()
            );
            $status = $res->getStatusCode();
            return in_array($status, [200, 204, 404], true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Descarga el archivo ZIP completo de un estudio desde Orthanc (/studies/{id}/archive).
     */
    public function getStudyArchive(string $orthancStudyId): ?string
    {
        try {
            $res = $this->http->request(
                'GET',
                $this->baseUrl . '/studies/' . urlencode($orthancStudyId) . '/archive',
                $this->requestOptions(['timeout' => 120])
            );
            if ($res->getStatusCode() !== 200) {
                return null;
            }

            return (string) $res->getBody();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Transmite el archivo ZIP de un estudio por bloques sin cargarlo completo en memoria.
     *
     * @param callable(): void      $onReady Se invoca cuando Orthanc responde HTTP 200 antes del primer bloque.
     * @param callable(string): int $onChunk Recibe cada bloque binario y devuelve los bytes procesados.
     */
    public function streamStudyArchive(string $orthancStudyId, callable $onReady, callable $onChunk): bool
    {
        $url = $this->baseUrl . '/studies/' . urlencode($orthancStudyId) . '/archive';
        $ch  = curl_init($url);
        if ($ch === false) {
            return false;
        }

        $statusCode  = 0;
        $readyCalled = false;

        $curlOpts = [
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$statusCode, &$readyCalled, $onReady): int {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $m)) {
                    $statusCode = (int) $m[1];
                } elseif (trim($headerLine) === '' && $statusCode === 200 && ! $readyCalled) {
                    $readyCalled = true;
                    $onReady();
                }

                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$statusCode, &$readyCalled, $onReady, $onChunk): int {
                if ($statusCode !== 200) {
                    return strlen($chunk);
                }

                if (! $readyCalled) {
                    $readyCalled = true;
                    $onReady();
                }

                $onChunk($chunk);
                return strlen($chunk);
            },
        ];

        if ($this->username !== null && $this->password !== null) {
            $curlOpts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $curlOpts[CURLOPT_USERPWD]  = $this->username . ':' . $this->password;
        }

        curl_setopt_array($ch, $curlOpts);

        $ok = curl_exec($ch);
        curl_close($ch);

        return $ok !== false && $statusCode === 200;
    }

    /**
     * Envía un archivo DICOM binario a Orthanc (/instances).
     *
     * @return array{ok: bool, status: int, data?: array<string, mixed>, error?: string}
     */
    public function uploadDicom(string $binaryPayload): array
    {
        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/instances',
                $this->requestOptions([
                    'headers' => ['Content-Type' => 'application/dicom'],
                    'body'    => $binaryPayload,
                    'timeout' => 60,
                ])
            );

            $status = $res->getStatusCode();
            $rawBody = (string) $res->getBody();
            $body   = json_decode($rawBody, true);

            if ($status >= 200 && $status < 300) {
                return ['ok' => true, 'status' => $status, 'data' => is_array($body) ? $body : []];
            }

            return ['ok' => false, 'status' => $status, 'error' => $rawBody];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extrae un archivo .zip y sube cada archivo DICOM válido a Orthanc.
     *
     * @return array{uploaded: int, failed: int, errors: list<string>, study_ids: list<string>}
     */
    public function uploadZip(string $zipPath): array
    {
        $zip      = new ZipArchive();
        $uploaded = 0;
        $failed   = 0;
        $errors   = [];
        $studyIds = [];

        if ($zip->open($zipPath) !== true) {
            return ['uploaded' => 0, 'failed' => 1, 'errors' => ['No se pudo abrir el archivo ZIP.'], 'study_ids' => []];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || str_ends_with($name, '/') || str_contains($name, '__MACOSX')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || $content === '') {
                continue;
            }

            $result = $this->uploadDicom($content);
            if ($result['ok']) {
                $uploaded++;
                if (! empty($result['data']['ParentStudy'])) {
                    $studyIds[(string) $result['data']['ParentStudy']] = true;
                }
            } else {
                $failed++;
                $errors[] = basename($name) . ': HTTP ' . $result['status'];
            }
        }

        $zip->close();

        return [
            'uploaded'  => $uploaded,
            'failed'    => $failed,
            'errors'    => array_slice($errors, 0, 5),
            'study_ids' => array_keys($studyIds),
        ];
    }

    /**
     * Lista las modalidades DICOM configuradas en Orthanc (GET /modalities).
     *
     * @return array{ok: bool, status: int, data: array<int|string, mixed>, error: ?string}
     */
    public function listModalities(bool $expand = false): array
    {
        $path = $expand ? '/modalities?expand' : '/modalities';

        try {
            $res     = $this->http->request('GET', $this->baseUrl . $path, $this->requestOptions());
            $status  = $res->getStatusCode();
            $rawBody = (string) $res->getBody();

            if ($status < 200 || $status >= 300) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'data'   => [],
                    'error'  => $this->extractOrthancError($rawBody, $status),
                ];
            }

            $decoded = json_decode($rawBody, true);
            if (! is_array($decoded)) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'data'   => [],
                    'error'  => 'Respuesta JSON inválida de Orthanc.',
                ];
            }

            return [
                'ok'     => true,
                'status' => $status,
                'data'   => $decoded,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => [],
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtiene la configuración de una modalidad DICOM en Orthanc (GET /modalities/{id}).
     *
     * @return array{ok: bool, status: int, data: ?array<string, mixed>, error: ?string}
     */
    public function getModality(string $modalityId): array
    {
        if (trim($modalityId) === '') {
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => null,
                'error'  => 'ID de modalidad vacío.',
            ];
        }

        try {
            $res     = $this->http->request(
                'GET',
                $this->baseUrl . '/modalities/' . rawurlencode($modalityId),
                $this->requestOptions()
            );
            $status  = $res->getStatusCode();
            $rawBody = (string) $res->getBody();

            if ($status < 200 || $status >= 300) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'data'   => null,
                    'error'  => $this->extractOrthancError($rawBody, $status),
                ];
            }

            $decoded = json_decode($rawBody, true);
            if (! is_array($decoded)) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'data'   => null,
                    'error'  => 'Respuesta JSON inválida de Orthanc.',
                ];
            }

            return [
                'ok'     => true,
                'status' => $status,
                'data'   => $decoded,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => null,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Crea o actualiza una modalidad DICOM en Orthanc (PUT /modalities/{id}).
     *
     * @param array<string, mixed> $config
     *
     * @return array{ok: bool, status: int, data: ?array<string, mixed>, error: ?string}
     */
    public function putModality(string $modalityId, array $config): array
    {
        if (trim($modalityId) === '') {
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => null,
                'error'  => 'ID de modalidad vacío.',
            ];
        }

        $payload = array_merge([
            'Manufacturer' => 'Generic',
            'AllowEcho'    => true,
            'AllowStore'   => true,
        ], $config);

        if (isset($payload['AET'])) {
            $payload['AET'] = (string) $payload['AET'];
        }
        if (isset($payload['Host'])) {
            $payload['Host'] = (string) $payload['Host'];
        }
        if (array_key_exists('Port', $payload)) {
            $payload['Port'] = (int) $payload['Port'];
        }
        $payload['Manufacturer'] = (string) $payload['Manufacturer'];
        $payload['AllowEcho']    = (bool) $payload['AllowEcho'];
        $payload['AllowStore']   = (bool) $payload['AllowStore'];

        try {
            $res     = $this->http->request(
                'PUT',
                $this->baseUrl . '/modalities/' . rawurlencode($modalityId),
                $this->requestOptions(['json' => $payload])
            );
            $status  = $res->getStatusCode();
            $rawBody = (string) $res->getBody();

            if ($status < 200 || $status >= 300) {
                return [
                    'ok'     => false,
                    'status' => $status,
                    'data'   => null,
                    'error'  => $this->extractOrthancError($rawBody, $status),
                ];
            }

            $trimmed = trim($rawBody);
            if ($trimmed === '') {
                return [
                    'ok'     => true,
                    'status' => $status,
                    'data'   => null,
                    'error'  => null,
                ];
            }

            $decoded = json_decode($trimmed, true);

            return [
                'ok'     => true,
                'status' => $status,
                'data'   => is_array($decoded) ? $decoded : null,
                'error'  => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'status' => 0,
                'data'   => null,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Elimina una modalidad DICOM de Orthanc de forma idempotente (DELETE /modalities/{id}).
     *
     * @return array{ok: bool, status: int, error: ?string}
     */
    public function deleteModality(string $modalityId): array
    {
        if (trim($modalityId) === '') {
            return [
                'ok'     => false,
                'status' => 0,
                'error'  => 'ID de modalidad vacío.',
            ];
        }

        try {
            $res    = $this->http->request(
                'DELETE',
                $this->baseUrl . '/modalities/' . rawurlencode($modalityId),
                $this->requestOptions()
            );
            $status = $res->getStatusCode();

            if (in_array($status, [200, 204, 404], true)) {
                return [
                    'ok'     => true,
                    'status' => $status,
                    'error'  => null,
                ];
            }

            return [
                'ok'     => false,
                'status' => $status,
                'error'  => $this->extractOrthancError((string) $res->getBody(), $status),
            ];
        } catch (\Throwable $e) {
            return [
                'ok'     => false,
                'status' => 0,
                'error'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Ejecuta C-ECHO DICOM hacia una modalidad registrada en Orthanc (POST /modalities/{id}/echo).
     *
     * @return array{ok: bool, status: int, latency_ms: int, error: ?string}
     */
    public function echoModality(string $modalityId, int $timeout = 10): array
    {
        if (trim($modalityId) === '') {
            return [
                'ok'         => false,
                'status'     => 0,
                'latency_ms' => 0,
                'error'      => 'ID de modalidad vacío.',
            ];
        }

        $timeout   = max(1, $timeout);
        $startedAt = hrtime(true);

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/modalities/' . rawurlencode($modalityId) . '/echo',
                $this->requestOptions([
                    'json'    => ['Timeout' => $timeout],
                    'timeout' => max(15, $timeout + 5),
                ])
            );

            $latencyMs = (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000));
            $status    = $res->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return [
                    'ok'         => true,
                    'status'     => $status,
                    'latency_ms' => $latencyMs,
                    'error'      => null,
                ];
            }

            return [
                'ok'         => false,
                'status'     => $status,
                'latency_ms' => $latencyMs,
                'error'      => $this->extractOrthancError((string) $res->getBody(), $status),
            ];
        } catch (\Throwable $e) {
            $latencyMs = (int) max(0, round((hrtime(true) - $startedAt) / 1_000_000));

            return [
                'ok'         => false,
                'status'     => 0,
                'latency_ms' => $latencyMs,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Envía recursos DICOM (estudios/series/instancias) vía C-STORE a una modalidad (POST /modalities/{id}/store).
     *
     * @param list<string> $resourceIds
     *
     * @return array{ok: bool, status: int, data: ?array<string, mixed>, instances_count: int, failed_count: int, error: ?string}
     */
    public function storeToModality(string $modalityId, array $resourceIds, bool $synchronous = true): array
    {
        if (trim($modalityId) === '') {
            return [
                'ok'              => false,
                'status'          => 0,
                'data'            => null,
                'instances_count' => 0,
                'failed_count'    => 0,
                'error'           => 'ID de modalidad vacío.',
            ];
        }

        $resources = array_values(array_filter(
            array_map(static fn ($id): string => trim((string) $id), $resourceIds),
            static fn (string $id): bool => $id !== ''
        ));

        if ($resources === []) {
            return [
                'ok'              => false,
                'status'          => 0,
                'data'            => null,
                'instances_count' => 0,
                'failed_count'    => 0,
                'error'           => 'No se especificaron recursos DICOM para enviar.',
            ];
        }

        try {
            $res = $this->http->request(
                'POST',
                $this->baseUrl . '/modalities/' . rawurlencode($modalityId) . '/store',
                $this->requestOptions([
                    'json' => [
                        'Resources'   => $resources,
                        'Synchronous' => $synchronous,
                    ],
                    'timeout' => 290,
                ])
            );

            $status         = $res->getStatusCode();
            $rawBody        = (string) $res->getBody();
            $decoded        = json_decode($rawBody, true);
            $json           = is_array($decoded) ? $decoded : null;
            $instancesCount = $json !== null ? (int) ($json['InstancesCount'] ?? 0) : 0;
            $failedCount    = $json !== null ? (int) ($json['FailedInstancesCount'] ?? 0) : 0;

            if ($status < 200 || $status >= 300) {
                return [
                    'ok'              => false,
                    'status'          => $status,
                    'data'            => $json,
                    'instances_count' => $instancesCount,
                    'failed_count'    => $failedCount,
                    'error'           => $this->extractOrthancError($rawBody, $status),
                ];
            }

            if ($json === null) {
                return [
                    'ok'              => false,
                    'status'          => $status,
                    'data'            => null,
                    'instances_count' => 0,
                    'failed_count'    => 0,
                    'error'           => 'Respuesta JSON inválida de Orthanc.',
                ];
            }

            if ($failedCount > 0) {
                $detail = $this->extractOrthancMessage($json);
                $error  = $detail !== ''
                    ? $detail
                    : "Falló el envío de {$failedCount} instancia(s) DICOM hacia el nodo remoto.";

                return [
                    'ok'              => false,
                    'status'          => $status,
                    'data'            => $json,
                    'instances_count' => $instancesCount,
                    'failed_count'    => $failedCount,
                    'error'           => $error,
                ];
            }

            if ($instancesCount === 0) {
                $detail = $this->extractOrthancMessage($json);
                $error  = $detail !== ''
                    ? $detail
                    : 'Orthanc no reportó instancias DICOM enviadas (InstancesCount = 0).';

                return [
                    'ok'              => false,
                    'status'          => $status,
                    'data'            => $json,
                    'instances_count' => 0,
                    'failed_count'    => $failedCount,
                    'error'           => $error,
                ];
            }

            return [
                'ok'              => true,
                'status'          => $status,
                'data'            => $json,
                'instances_count' => $instancesCount,
                'failed_count'    => $failedCount,
                'error'           => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'              => false,
                'status'          => 0,
                'data'            => null,
                'instances_count' => 0,
                'failed_count'    => 0,
                'error'           => $e->getMessage(),
            ];
        }
    }

    private function readEnv(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (! is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultClientOptions(): array
    {
        $options = [
            'timeout'     => 45,
            'http_errors' => false,
        ];

        if ($this->username !== null && $this->password !== null) {
            $options['auth'] = [$this->username, $this->password, 'basic'];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function requestOptions(array $extra = []): array
    {
        $options = array_merge([
            'timeout'     => 45,
            'http_errors' => false,
        ], $extra);

        if ($this->username !== null && $this->password !== null) {
            $options['auth'] = [$this->username, $this->password, 'basic'];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractOrthancMessage(array $decoded): string
    {
        $message = isset($decoded['Message']) && is_string($decoded['Message']) ? trim($decoded['Message']) : '';
        $details = isset($decoded['Details']) && is_string($decoded['Details']) ? trim($decoded['Details']) : '';

        if ($message === '' && isset($decoded['OrthancError']) && is_string($decoded['OrthancError'])) {
            $message = trim($decoded['OrthancError']);
        }
        if ($message === '' && isset($decoded['error']) && is_string($decoded['error'])) {
            $message = trim($decoded['error']);
        }

        $parts = [];
        if ($message !== '') {
            $parts[] = $message;
        }
        if ($details !== '' && $details !== $message) {
            $parts[] = $details;
        }

        return implode(' — ', $parts);
    }

    private function extractOrthancError(string $rawBody, int $status): string
    {
        $trimmed = trim($rawBody);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $extracted = $this->extractOrthancMessage($decoded);
                if ($extracted !== '') {
                    return $extracted;
                }
            }

            return $trimmed;
        }

        return "Error HTTP {$status} desde Orthanc.";
    }
}
