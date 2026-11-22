<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (! function_exists('format_date')) {
    /**
     * Formatea cualquier fecha (DICOM YYYYMMDD, ISO YYYY-MM-DD, SQL datetime o timestamp) a dd/mm/aaaa.
     */
    function format_date(?string $date, string $fallback = '-'): string
    {
        $trimmed = trim((string) $date);
        if ($trimmed === '' || $trimmed === '-') {
            return $fallback;
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $trimmed, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        if (preg_match('/^(\d{4})[-.\/](\d{2})[-.\/](\d{2})(?:[T\s].*)?$/', $trimmed, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }

        if (preg_match('/^(\d{2})[-.\/](\d{2})[-.\/](\d{4})(?:\s+.*)?$/', $trimmed, $m)) {
            return "{$m[1]}/{$m[2]}/{$m[3]}";
        }

        $ts = strtotime($trimmed);

        return $ts !== false ? date('d/m/Y', $ts) : $trimmed;
    }
}

if (! function_exists('format_datetime')) {
    /**
     * Formatea una fecha con hora a dd/mm/aaaa HH:mm (o dd/mm/aaaa si no incluye hora).
     */
    function format_datetime(?string $datetime, string $fallback = '-'): string
    {
        $trimmed = trim((string) $datetime);
        if ($trimmed === '' || $trimmed === '-') {
            return $fallback;
        }

        if (preg_match('/^(\d{4})[-.\/](\d{2})[-.\/](\d{2})[T\s](\d{2}:\d{2})(?::\d{2})?/', $trimmed, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]} {$m[4]}";
        }

        if (preg_match('/^(\d{2})[-.\/](\d{2})[-.\/](\d{4})\s+(\d{2}:\d{2})(?::\d{2})?/', $trimmed, $m)) {
            return "{$m[1]}/{$m[2]}/{$m[3]} {$m[4]}";
        }

        $ts = strtotime($trimmed);
        if ($ts !== false && preg_match('/\d{2}:\d{2}/', $trimmed)) {
            return date('d/m/Y H:i', $ts);
        }

        return format_date($trimmed, $fallback);
    }
}

if (! function_exists('extract_study_modality')) {
    /**
     * Obtiene la modalidad DICOM principal de un estudio (CT, MR, CR, DX, US, etc.).
     *
     * @param array<string, mixed> $study
     */
    function extract_study_modality(array $study): string
    {
        $raw = $study['RequestedTags']['ModalitiesInStudy']
            ?? $study['MainDicomTags']['ModalitiesInStudy']
            ?? $study['MainDicomTags']['Modality']
            ?? $study['modality']
            ?? '';

        if (is_array($raw)) {
            $raw = implode('\\', array_filter(array_map('strval', $raw)));
        }

        $raw = strtoupper(trim((string) $raw));
        if ($raw !== '') {
            $parts     = array_values(array_filter(array_map('trim', explode('\\', $raw))));
            $nonImages = ['SR', 'PR', 'KO', 'DOC', 'AU'];
            foreach ($parts as $part) {
                if (! in_array($part, $nonImages, true)) {
                    return $part;
                }
            }
            if ($parts !== []) {
                return $parts[0];
            }
        }

        $desc = strtoupper(trim((string) ($study['MainDicomTags']['StudyDescription'] ?? $study['study_description'] ?? '')));
        if ($desc !== '') {
            if (preg_match('/\b(TAC|CT|TOMOGRAF)/u', $desc)) {
                return 'CT';
            }
            if (preg_match('/\b(RM|MR|RESONANCIA)/u', $desc)) {
                return 'MR';
            }
            if (preg_match('/\b(RX|CR|DX|RADIOGRAF)/u', $desc)) {
                return 'RX';
            }
            if (preg_match('/\b(ECO|US|ULTRASON)/u', $desc)) {
                return 'US';
            }
            if (preg_match('/\b(MAMO|MG)\b/u', $desc)) {
                return 'MG';
            }
            if (preg_match('/\b(PET|PT)\b/u', $desc)) {
                return 'PT';
            }
        }

        return 'DICOM';
    }
}

if (! function_exists('extract_patient_age')) {
    /**
     * Calcula la edad del paciente desde PatientAge o PatientBirthDate respecto a StudyDate.
     *
     * @param array<string, mixed> $info
     */
    function extract_patient_age(array $info, ?string $studyDate = null): string
    {
        $rawAge = trim((string) (
            $info['MainDicomTags']['PatientAge']
            ?? $info['PatientMainDicomTags']['PatientAge']
            ?? ''
        ));

        if ($rawAge !== '' && preg_match('/^0*(\d+)([YMWD])$/i', $rawAge, $m)) {
            $num  = (int) $m[1];
            $unit = strtoupper($m[2]);

            return match ($unit) {
                'Y' => $num === 1 ? '1 año' : "{$num} años",
                'M' => $num === 1 ? '1 mes' : "{$num} meses",
                'W' => $num === 1 ? '1 semana' : "{$num} semanas",
                'D' => $num === 1 ? '1 día' : "{$num} días",
            };
        }

        $birthRaw = preg_replace('/\D/', '', (string) ($info['PatientMainDicomTags']['PatientBirthDate'] ?? ''));
        if (strlen($birthRaw) !== 8) {
            return '-';
        }

        $birth = \DateTimeImmutable::createFromFormat('!Ymd', $birthRaw);
        if ($birth === false) {
            return '-';
        }

        $refRaw = preg_replace('/\D/', '', (string) $studyDate);
        $ref    = strlen($refRaw) === 8
            ? (\DateTimeImmutable::createFromFormat('!Ymd', $refRaw) ?: new \DateTimeImmutable('today'))
            : new \DateTimeImmutable('today');

        if ($birth > $ref) {
            return '-';
        }

        $diff = $birth->diff($ref);
        if ($diff->y > 0) {
            return $diff->y === 1 ? '1 año' : "{$diff->y} años";
        }
        if ($diff->m > 0) {
            return $diff->m === 1 ? '1 mes' : "{$diff->m} meses";
        }

        return $diff->d === 1 ? '1 día' : "{$diff->d} días";
    }
}

if (! function_exists('format_study_description')) {
    /**
     * Formatea la descripción del estudio en mayúsculas uniformes, o devuelve un respaldo si está vacía.
     */
    function format_study_description(?string $description, string $fallback = 'Sin descripción'): string
    {
        $clean = trim((string) $description);
        if ($clean === '') {
            return $fallback;
        }

        return mb_strtoupper($clean, 'UTF-8');
    }
}

if (! function_exists('ohif_url')) {
    /**
     * Construye la URL de apertura de OHIF Viewer forzando el idioma español y vista en cuadrícula adaptativa.
     */
    function ohif_url(string $studyUid): string
    {
        return '/viewer/viewer?StudyInstanceUIDs=' . urlencode($studyUid) . '&hangingProtocolId=' . urlencode('@ohif/mnGrid') . '&lng=es';
    }
}

if (! function_exists('normalize_date_filter')) {
    /**
     * Valida y normaliza una fecha (YYYY-MM-DD o DD/MM/YYYY) al formato YYYY-MM-DD.
     */
    function normalize_date_filter(?string $value): string
    {
        $clean = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean) === 1) {
            return $clean;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $clean, $m) === 1) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return '';
    }
}

if (! function_exists('resolve_date_filter')) {
    /**
     * Resuelve el preset de fecha (all, today, week, month, year, custom) a un rango YYYY-MM-DD.
     *
     * @return array{preset: string, dateFrom: string, dateTo: string}
     */
    function resolve_date_filter(?string $preset, ?string $rawFrom, ?string $rawTo): array
    {
        $cleanPreset = strtolower(trim((string) $preset));
        $from        = normalize_date_filter($rawFrom);
        $to          = normalize_date_filter($rawTo);

        if ($cleanPreset === '' && ($from !== '' || $to !== '')) {
            $cleanPreset = 'custom';
        }

        $today = date('Y-m-d');

        return match ($cleanPreset) {
            'today' => ['preset' => 'today', 'dateFrom' => $today, 'dateTo' => $today],
            'week'  => ['preset' => 'week', 'dateFrom' => date('Y-m-d', strtotime('monday this week')), 'dateTo' => $today],
            'month' => ['preset' => 'month', 'dateFrom' => date('Y-m-01'), 'dateTo' => $today],
            'year'  => ['preset' => 'year', 'dateFrom' => date('Y-01-01'), 'dateTo' => $today],
            'custom' => ['preset' => 'custom', 'dateFrom' => $from, 'dateTo' => $to],
            default => ['preset' => 'all', 'dateFrom' => '', 'dateTo' => ''],
        };
    }
}

if (! function_exists('build_dicom_date_range')) {
    /**
     * Construye un rango StudyDate válido para Orthanc /tools/find (YYYYMMDD-YYYYMMDD).
     */
    function build_dicom_date_range(string $dateFrom, string $dateTo): ?string
    {
        $from = $dateFrom !== '' ? str_replace('-', '', $dateFrom) : '';
        $to   = $dateTo !== '' ? str_replace('-', '', $dateTo) : '';

        if ($from === '' && $to === '') {
            return null;
        }

        if ($from !== '' && $to !== '') {
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return $from . '-' . $to;
        }

        return $from !== '' ? ($from . '-') : ('-' . $to);
    }
}

if (! function_exists('study_matches_date_range')) {
    /**
     * Comprueba si la fecha StudyDate de un estudio está dentro del rango YYYY-MM-DD.
     *
     * @param array<string, mixed> $study
     */
    function study_matches_date_range(array $study, string $dateFrom, string $dateTo): bool
    {
        if ($dateFrom === '' && $dateTo === '') {
            return true;
        }

        $rawDate = trim((string) ($study['MainDicomTags']['StudyDate'] ?? ''));
        if (preg_match('/^\d{8}$/', $rawDate) !== 1) {
            return false;
        }

        $from = $dateFrom !== '' ? str_replace('-', '', $dateFrom) : '';
        $to   = $dateTo !== '' ? str_replace('-', '', $dateTo) : '';

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        if ($from !== '' && $rawDate < $from) {
            return false;
        }

        if ($to !== '' && $rawDate > $to) {
            return false;
        }

        return true;
    }
}
