<?php
declare(strict_types=1);

final class ViduProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('VIDU_API_KEY'); }
    public function id(): string { return 'vidu_motion_2_5'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float { return round(max(3, $seconds) * 34 * 0.005, 4); }

    public function submit(array $input): array {
        if (ad_mock_mode()) return ['external_id' => ad_id('mock_vidu'), 'status' => 'submitted', 'raw' => ['mock' => true, 'input' => $input]];
        if ($this->key === '') throw new RuntimeException('VIDU_API_KEY is not configured.');
        $payload = [
            'template' => 'motion_control_2.5',
            'images' => [(string)$input['character_url']],
            'video_urls' => [(string)$input['performance_url']],
            'payload' => json_encode(['lab_job_id' => (string)($input['job_id'] ?? '')], JSON_UNESCAPED_SLASHES),
        ];
        $data = ad_http_json('POST', 'https://api.vidu.com/ent/v2/template', [
            'Authorization: Token ' . $this->key,
            'Content-Type: application/json',
        ], $payload);
        $id = (string)($data['task_id'] ?? '');
        if ($id === '') throw new RuntimeException('Vidu returned no task id.');
        return ['external_id' => $id, 'status' => 'submitted', 'raw' => $data];
    }

    public function poll(string $externalId): array {
        if (ad_mock_mode()) return ['status' => 'succeeded', 'output_url' => '', 'mock' => true, 'raw' => ['id' => $externalId, 'mock' => true]];
        $data = ad_http_json('GET', 'https://api.vidu.com/ent/v2/tasks/' . rawurlencode($externalId) . '/creations', [
            'Authorization: Token ' . $this->key,
            'Content-Type: application/json',
        ]);
        $state = strtolower((string)($data['state'] ?? 'processing'));
        if ($state === 'success') {
            $creations = is_array($data['creations'] ?? null) ? $data['creations'] : [];
            $url = is_array($creations[0] ?? null) ? (string)($creations[0]['url'] ?? '') : '';
            return ['status' => 'succeeded', 'output_url' => $url, 'credits' => (int)($data['credits'] ?? 0), 'raw' => $data];
        }
        if ($state === 'failed') return ['status' => 'failed', 'error' => (string)($data['err_code'] ?? 'Vidu generation failed.'), 'raw' => $data];
        return ['status' => in_array($state, ['processing'], true) ? 'processing' : 'queued', 'raw' => $data];
    }
}
