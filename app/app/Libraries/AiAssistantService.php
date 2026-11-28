<?php

declare(strict_types=1);

namespace App\Libraries;

/////////////////////////////////////////////////////////////////////////////////
// Cliente agnóstico de IA para asistencia en informes (Cloud API u Ollama).  //
/////////////////////////////////////////////////////////////////////////////////
class AiAssistantService
{
    private const MAX_INPUT_CHARS = 15000;

    private const CLOUD_PRESETS = [
        'gemini' => [
            'label' => 'Google Gemini (OpenAI compat.)',
            'url'   => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'model' => 'gemini-3.8-flash',
        ],
        'openai' => [
            'label' => 'OpenAI',
            'url'   => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
        ],
        'groq' => [
            'label' => 'Groq',
            'url'   => 'https://api.groq.com/openai/v1',
            'model' => 'llama-3.3-70b-versatile',
        ],
        'custom' => [
            'label' => 'URL personalizada (compatible OpenAI)',
            'url'   => '',
            'model' => '',
        ],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cachedConfig = null;

    /**
     * Devuelve los presets soportados para el modo Cloud.
     *
     * @return array<string, array{label:string, url:string, model:string}>
     */
    public static function cloudPresets(): array
    {
        return self::CLOUD_PRESETS;
    }

    /**
     * Lee la configuración actual desde Settings (cacheada por instancia).
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }

        $settings = service('settings');
        $provider = (string) ($settings->get('Ai.provider') ?? 'cloud');
        if (! in_array($provider, ['cloud', 'ollama'], true)) {
            $provider = 'cloud';
        }

        $cloudPreset = (string) ($settings->get('Ai.cloudPreset') ?? 'gemini');
        if (! array_key_exists($cloudPreset, self::CLOUD_PRESETS)) {
            $cloudPreset = 'gemini';
        }

        $cloudUrl   = trim((string) ($settings->get('Ai.cloudUrl') ?? self::CLOUD_PRESETS[$cloudPreset]['url']));
        $cloudModel = trim((string) ($settings->get('Ai.cloudModel') ?? self::CLOUD_PRESETS[$cloudPreset]['model']));
        $cloudKey   = trim((string) ($settings->get('Ai.cloudApiKey') ?? ''));

        $ollamaUrl   = trim((string) ($settings->get('Ai.ollamaUrl') ?? 'http://127.0.0.1:11434'));
        $ollamaModel = trim((string) ($settings->get('Ai.ollamaModel') ?? 'medgemma:4b'));

        $this->cachedConfig = [
            'enabled'     => (bool) $settings->get('Ai.enabled'),
            'provider'    => $provider,
            'cloudPreset' => $cloudPreset,
            'cloudUrl'    => $cloudUrl,
            'cloudModel'  => $cloudModel,
            'hasApiKey'   => $cloudKey !== '',
            'cloudApiKey' => $cloudKey,
            'ollamaUrl'   => $ollamaUrl,
            'ollamaModel' => $ollamaModel,
        ];

        return $this->cachedConfig;
    }

    /**
     * Indica si el asistente está habilitado y mínimamente configurado.
     */
    public function isAvailable(): bool
    {
        $cfg = $this->getConfig();
        if (! $cfg['enabled']) {
            return false;
        }

        if ($cfg['provider'] === 'ollama') {
            return $cfg['ollamaUrl'] !== '' && $cfg['ollamaModel'] !== '';
        }

        return $cfg['cloudUrl'] !== '' && $cfg['cloudModel'] !== '' && $cfg['cloudApiKey'] !== '';
    }

    /**
     * Ejecuta una prueba de conexión contra el proveedor configurado.
     *
     * @return array{ok:bool, message:string}
     */
    public function testConnection(): array
    {
        $cfg = $this->getConfig();

        if ($cfg['provider'] === 'ollama') {
            if ($cfg['ollamaUrl'] === '' || $cfg['ollamaModel'] === '') {
                return ['ok' => false, 'message' => 'Falta la URL o el modelo de Ollama.'];
            }
        } elseif ($cfg['cloudUrl'] === '' || $cfg['cloudModel'] === '' || $cfg['cloudApiKey'] === '') {
            return ['ok' => false, 'message' => 'Falta la URL, el modelo o la API Key del proveedor Cloud.'];
        }

        $res = $this->chat(
            'Responde únicamente con la palabra OK.',
            'Prueba de conexión.',
            0.0,
            512
        );

        if (! $res['ok']) {
            return ['ok' => false, 'message' => $res['error'] ?? 'Error desconocido.'];
        }

        $target = $cfg['provider'] === 'ollama'
            ? 'Ollama (' . $cfg['ollamaModel'] . ')'
            : 'Cloud (' . $cfg['cloudModel'] . ')';

        return ['ok' => true, 'message' => 'Conexión correcta con ' . $target . '.'];
    }

    /**
     * Consulta los modelos instalados en un servidor Ollama (/api/tags).
     *
     * @return array{ok:bool, models:list<string>, error?:string}
     */
    public function listOllamaModels(string $ollamaUrl): array
    {
        $base = rtrim(trim($ollamaUrl), '/');
        if (! $this->isValidHttpUrl($base)) {
            return ['ok' => false, 'models' => [], 'error' => 'URL de Ollama no válida.'];
        }

        $res = $this->httpRequest('GET', $base . '/api/tags', [], null, 4, 6);
        if (! $res['ok']) {
            return ['ok' => false, 'models' => [], 'error' => $res['error']];
        }

        $models = [];
        foreach (($res['data']['models'] ?? []) as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '') {
                $models[] = $name;
            }
        }

        return ['ok' => true, 'models' => $models];
    }

    /**
     * Consulta los modelos disponibles en una API Cloud (Gemini nativo o /models OpenAI).
     *
     * @return array{ok:bool, models:list<string>, error?:string}
     */
    public function listCloudModels(string $cloudUrl, string $apiKey = ''): array
    {
        $base = rtrim(trim($cloudUrl), '/');
        if (! $this->isValidHttpUrl($base)) {
            return ['ok' => false, 'models' => [], 'error' => 'URL de API Cloud no válida.'];
        }

        $key = trim($apiKey);
        if ($key === '') {
            $key = (string) ($this->getConfig()['cloudApiKey'] ?? '');
        }
        if ($key === '') {
            return ['ok' => false, 'models' => [], 'error' => 'Introduce o guarda primero una API Key.'];
        }

        $isGemini = str_contains($base, 'generativelanguage.googleapis.com');
        $url      = $isGemini
            ? 'https://generativelanguage.googleapis.com/v1beta/models'
            : ($base . '/models');
        $headers  = $isGemini
            ? ['x-goog-api-key: ' . $key]
            : ['Authorization: Bearer ' . $key];

        $res = $this->httpRequest('GET', $url, $headers, null, 5, 10);
        if (! $res['ok']) {
            return ['ok' => false, 'models' => [], 'error' => $res['error']];
        }

        $decoded = $res['data'];
        $models  = [];
        if ($isGemini) {
            foreach (($decoded['models'] ?? []) as $item) {
                $methods = (array) ($item['supportedGenerationMethods'] ?? []);
                if (! in_array('generateContent', $methods, true)) {
                    continue;
                }
                $name = $this->stripModelPrefix((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    $models[] = $name;
                }
            }
        } else {
            foreach (($decoded['data'] ?? []) as $item) {
                $id = $this->stripModelPrefix((string) ($item['id'] ?? ''));
                if ($id !== '') {
                    $models[] = $id;
                }
            }
        }

        $models = array_values(array_unique($models));
        sort($models);

        return ['ok' => true, 'models' => $models];
    }

    /**
     * Ejecuta una acción de asistencia médica sobre los hallazgos del informe.
     *
     * @param array{modality?:string, study_description?:string, clinical_history?:string, findings?:string} $context
     * @return array{ok:bool, text?:string, error?:string}
     */
    public function assist(string $action, array $context): array
    {
        if (! $this->isAvailable()) {
            return ['ok' => false, 'error' => 'El asistente de IA no está habilitado o configurado.'];
        }

        $modality    = $this->sanitizeClinicalText((string) ($context['modality'] ?? ''), 80);
        $description = $this->sanitizeClinicalText((string) ($context['study_description'] ?? ''), 300);
        $clinical    = $this->sanitizeClinicalText((string) ($context['clinical_history'] ?? ''), 2000);
        $findings    = $this->sanitizeClinicalText((string) ($context['findings'] ?? ''), self::MAX_INPUT_CHARS);

        if (mb_strlen($findings) < 10) {
            return ['ok' => false, 'error' => 'Escribe al menos unos hallazgos clínicos antes de usar el asistente.'];
        }

        $studyHeader = trim(
            ($modality !== '' ? "Modalidad: {$modality}\n" : '')
            . ($description !== '' ? "Estudio: {$description}\n" : '')
            . ($clinical !== '' ? "Antecedentes / Motivo: {$clinical}\n" : '')
        );

        $prefix = $studyHeader !== '' ? ($studyHeader . "\n") : '';

        return match ($action) {
            'impression' => $this->chat(
                'Eres un radiólogo asistente. Redacta exclusivamente la sección de "Conclusión / Impresión Diagnóstica" en español médico formal, conciso y preciso a partir de los hallazgos proporcionados. No inventes hallazgos que no estén descritos, no incluyas encabezados ni saludos, y devuelve solo los puntos o párrafos de conclusión. Si usas lista, usa viñetas con "- ".',
                $prefix . "Hallazgos:\n" . $findings,
                0.2,
                1500
            ),
            'format' => $this->chat(
                'Eres un editor de informes médicos de radiología en español. Reorganiza y maqueta el texto proporcionado para que se vea limpio, estructurado y fácil de leer, manteniendo exactamente el mismo contenido clínico sin inventar ni omitir datos. Separa cada estructura anatómica o apartado en su propio párrafo, pon en negrita los subtítulos o apartados usando **Subtítulo:** (por ejemplo **Parénquima pulmonar:**, **Mediastino:**, **Estructuras óseas:**) y usa viñetas "- " si hay varios hallazgos enumerados. Devuelve únicamente el texto maquetado, sin comentarios adicionales.',
                $prefix . "Texto a ordenar:\n" . $findings,
                0.1,
                2500
            ),
            'patient_summary' => $this->chat(
                'Eres un médico que explica un informe radiológico a un paciente en español claro, empático y sencillo, evitando jerga técnica innecesaria. Resume qué se ha observado y recuerda brevemente al final que debe comentar el resultado con su médico tratante para su interpretación clínica. No des diagnósticos definitivos ni alarmistas, y evita símbolos Markdown como ** o ###.',
                $prefix . "Informe / Hallazgos:\n" . $findings,
                0.3,
                1500
            ),
            default => ['ok' => false, 'error' => 'Acción de IA no válida.'],
        };
    }

    /**
     * Valida que la URL use estrictamente HTTP o HTTPS.
     */
    private function isValidHttpUrl(string $url): bool
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Elimina el prefijo "models/" si el proveedor lo incluye en el identificador.
     */
    private function stripModelPrefix(string $id): string
    {
        $id = trim($id);

        return str_starts_with($id, 'models/') ? substr($id, 7) : $id;
    }

    /**
     * Limpia posibles datos identificativos accidentales (correos, teléfonos, DNI/NIE) y acota longitud.
     */
    private function sanitizeClinicalText(string $text, int $maxLength = self::MAX_INPUT_CHARS): string
    {
        $clean = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($clean === '') {
            return '';
        }

        $clean = (string) preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[EMAIL]', $clean);
        $clean = (string) preg_replace('/\b(?:\+?\d{1,3}[\s.-]?)?(?:\d[\s.-]?){8,12}\b/', '[TEL/ID]', $clean);
        $clean = (string) preg_replace('/\b\d{7,8}[A-Z]\b/i', '[ID]', $clean);

        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength);
        }

        return trim($clean);
    }

    /**
     * Envía la petición de chat al proveedor configurado (Ollama nativo o API compatible OpenAI).
     *
     * @return array{ok:bool, text?:string, error?:string}
     */
    private function chat(string $systemPrompt, string $userPrompt, float $temperature, int $maxTokens): array
    {
        $cfg = $this->getConfig();

        if ($cfg['provider'] === 'ollama') {
            $base = rtrim((string) $cfg['ollamaUrl'], '/');
            if (! $this->isValidHttpUrl($base)) {
                return ['ok' => false, 'error' => 'URL de Ollama no válida.'];
            }
            $endpoint = $base . '/api/chat';
            $headers  = ['Content-Type: application/json'];
            $payload  = [
                'model'    => $cfg['ollamaModel'],
                'stream'   => false,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'options'  => [
                    'temperature' => $temperature,
                    'num_predict' => $maxTokens,
                ],
            ];
        } else {
            $base = rtrim((string) $cfg['cloudUrl'], '/');
            if (! $this->isValidHttpUrl($base)) {
                return ['ok' => false, 'error' => 'URL de API Cloud no válida.'];
            }
            $endpoint = $base . '/chat/completions';
            $headers  = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['cloudApiKey'],
            ];
            $payload  = [
                'model'       => $cfg['cloudModel'],
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ];
        }

        $res = $this->httpRequest('POST', $endpoint, $headers, $payload, 8, 120);
        if (! $res['ok']) {
            return ['ok' => false, 'error' => $res['error']];
        }

        $decoded = $res['data'];
        $content = $cfg['provider'] === 'ollama'
            ? ($decoded['message']['content'] ?? '')
            : ($decoded['choices'][0]['message']['content'] ?? '');

        $text = trim((string) $content);
        if ($text === '') {
            return ['ok' => false, 'error' => 'El modelo devolvió una respuesta vacía.'];
        }

        return ['ok' => true, 'text' => $text];
    }

    /**
     * Ejecuta una petición HTTP/HTTPS JSON con cURL restringido a HTTP(S) sin redirecciones.
     *
     * @param list<string>              $headers
     * @param array<string, mixed>|null $payload
     * @return array{ok:bool, data?:array<mixed>, error?:string}
     */
    private function httpRequest(
        string $method,
        string $url,
        array $headers = [],
        ?array $payload = null,
        int $connectTimeout = 5,
        int $timeout = 30
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'No se pudo inicializar el cliente HTTP.'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);
        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'Error de red al conectar con IA: ' . $curlErr];
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $apiMsg = $decoded['error']['message']
                ?? $decoded[0]['error']['message']
                ?? $decoded['error']
                ?? ('HTTP ' . $httpCode);
            return [
                'ok'    => false,
                'error' => 'Error del proveedor IA: ' . (is_string($apiMsg) ? $apiMsg : ('HTTP ' . $httpCode)),
            ];
        }

        return ['ok' => true, 'data' => is_array($decoded) ? $decoded : []];
    }
}
