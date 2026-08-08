<?php
declare(strict_types=1);

interface ADProvider {
    public function id(): string;
    public function available(): bool;
    public function submit(array $input): array;
    public function poll(string $externalId): array;
    public function estimatedUsd(float $seconds): float;
}

function ad_http_json(string $method, string $url, array $headers = [], ?array $body = null): array {
    $ch = curl_init($url);
    if (!$ch) throw new RuntimeException('Could not initialize provider request.');
    $allHeaders = array_merge(['Accept: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $allHeaders,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Provider network error: ' . $error);
    $data = json_decode((string)$raw, true);
    if (!is_array($data)) $data = ['raw' => ad_substr((string)$raw, 0, 2000)];
    if ($code < 200 || $code >= 300) {
        $message = (string)($data['error'] ?? $data['message'] ?? ('Provider HTTP ' . $code));
        throw new RuntimeException(ad_substr($message, 0, 400));
    }
    return $data;
}
