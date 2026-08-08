<?php
declare(strict_types=1);

final class RunwayProvider implements ADProvider {
    private string $key;
    public function __construct() { $this->key = ad_env('RUNWAY_API_KEY'); }
    public function id(): string { return 'runway_act_two'; }
    public function available(): bool { return ad_mock_mode() || $this->key !== ''; }
    public function estimatedUsd(float $seconds): float { return round(max(3, $seconds) * 0.05, 4); }

    public function submit(array $input): array {
        if (ad_mock_mode()) return $this->mockSubmit($input);
        if ($this->key === '') throw new RuntimeException('RUNWAY_API_KEY is not configured.');
        $duration = max(3, min(30, (int)ceil((float)($input['duration_seconds'] ?? 5))));
        $payload = [
            'model' => 'act_two',
            'promptImage' => (string)$input['character_url'],
            'promptPerformance' => (string)$input['performance_url'],
            'ratio' => (string)($input['ratio'] ?? '1280:720'),
            'duration' => $duration,
        ];
        $data = ad_http_json('POST', 'https://api.dev.runwayml.com/v1/character_performance', [
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'X-Runway-Version: 2024-11-06',
        ], $payload);
        $id = (string)($data['id'] ?? '');
        if ($id === '') throw new RuntimeException('Runway returned no task id.');
        return ['external_id' => $id, 'status' => 'submitted', 'raw' => $data];
    }

    public function poll(string $externalId): array {
        if (ad_mock_mode()) return $this->mockPoll($externalId);
        $data = ad_http_json('GET', 'https://api.dev.runwayml.com/v1/tasks/' . rawurlencode($externalId), [
            'Authorization: Bearer ' . $this->key,
            'X-Runway-Version: 2024-11-06',
        ]);
        $status = strtoupper((string)($data['status'] ?? 'PENDING'));
        if ($status === 'SUCCEEDED') {
            $output = $data['output'] ?? [];
            $url = is_array($output) ? (string)($output[0] ?? '') : '';
            return ['status' => 'succeeded', 'output_url' => $url, 'raw' => $data];
        }
        if ($status === 'FAILED') return ['status' => 'failed', 'error' => (string)($data['failure'] ?? 'Runway generation failed.'), 'raw' => $data];
        return ['status' => in_array($status, ['RUNNING','THROTTLED'], true) ? 'processing' : 'queued', 'raw' => $data];
    }

    private function mockSubmit(array $input): array {
        return ['external_id' => ad_id('mock_runway'), 'status' => 'submitted', 'raw' => ['mock' => true, 'input' => $input]];
    }
    private function mockPoll(string $externalId): array {
        return ['status' => 'succeeded', 'output_url' => '', 'mock' => true, 'raw' => ['id' => $externalId, 'mock' => true]];
    }
}
