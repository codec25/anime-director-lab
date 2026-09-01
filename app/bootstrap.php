<?php
declare(strict_types=1);

define('AD_ROOT', dirname(__DIR__));
define('AD_DATA_DIR', AD_ROOT . '/data');
define('AD_STORAGE_DIR', AD_ROOT . '/storage');

function ad_ensure_runtime_dirs(): void {
    foreach ([
        AD_DATA_DIR,
        AD_STORAGE_DIR . '/characters',
        AD_STORAGE_DIR . '/performances',
        AD_STORAGE_DIR . '/results',
        AD_STORAGE_DIR . '/references',
    ] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create runtime directory: ' . $dir);
        }
    }
}
ad_ensure_runtime_dirs();

function ad_load_env(): void {
    $path = AD_ROOT . '/.env';
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}
ad_load_env();

function ad_env(string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value === false || trim((string)$value) === '') ? $default : trim((string)$value);
}

function ad_mock_mode(): bool {
    return in_array(strtolower(ad_env('ANIME_DIRECTOR_MOCK_MODE', '1')), ['1','true','yes','on'], true);
}

function ad_json_input(): array {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function ad_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ad_id(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(8));
}

function ad_now(): string { return gmdate('c'); }

function ad_substr(string $value, int $start, int $length): string {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}

function ad_base_url(): string {
    $configured = rtrim(ad_env('ANIME_DIRECTOR_BASE_URL'), '/');
    if ($configured !== '') return $configured;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $script = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api.php')));
    $script = $script === '/' ? '' : rtrim($script, '/');
    return ($https ? 'https' : 'http') . '://' . $host . $script;
}

function ad_public_media_url(string $relativePath): string {
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (str_contains($relativePath, '..')) throw new InvalidArgumentException('Invalid media path.');
    return ad_base_url() . '/' . $relativePath;
}

require_once AD_ROOT . '/app/schema.php';
require_once AD_ROOT . '/app/state.php';
require_once AD_ROOT . '/app/storage.php';
require_once AD_ROOT . '/app/providers/ProviderInterface.php';
require_once AD_ROOT . '/app/providers/RunwayProvider.php';
require_once AD_ROOT . '/app/providers/RunwayActTwoProvider.php';
require_once AD_ROOT . '/app/providers/RunwayDescribeProvider.php';
require_once AD_ROOT . '/app/providers/RunwayReferenceProvider.php';
require_once AD_ROOT . '/app/providers/RunwayContinueProvider.php';
require_once AD_ROOT . '/app/providers/ViduProvider.php';
require_once AD_ROOT . '/app/providers/KlingProvider.php';
require_once AD_ROOT . '/app/providers/GoogleProvider.php';
require_once AD_ROOT . '/app/providers/WanProvider.php';
require_once AD_ROOT . '/app/gateway.php';
