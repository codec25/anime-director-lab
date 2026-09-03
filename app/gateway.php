<?php
declare(strict_types=1);

/** AI Gateway: creator workflows request capabilities, providers stay swappable. */
function ad_capabilities(): array {
    return [
        'ACT_IT' => 'Preserve a human performance on a locked character.',
        'ANIMATE_SHOT' => 'Animate a shot from approved direction.',
        'DESCRIBE_SHOT' => 'Text/direction-driven shot generation.',
        'MULTI_REFERENCE' => 'Bind character, world, image, video and audio references into generation.',
        'DIALOGUE' => 'Speaker-assigned dialogue with persistent per-character voice memory.',
        'CONTINUE_SHOT' => 'Continue a generated video into the next directed shot.',
        'LIP_SYNC' => 'Precision facial/dialogue performance through per-character Act-Two passes.',
        'ANIME_BOOST' => 'Stylized amplification layer over preserved performance.',
        'SOUND_EFFECT' => 'Impact/SFX direction (editable-effects future).',
    ];
}

function ad_provider_registry(): array {
    return [
        'runway_act_two' => [
            'class' => RunwayActTwoProvider::class,
            'label' => 'Runway Act-Two',
            'binding_key' => 'runway',
            'capabilities' => ['ACT_IT', 'LIP_SYNC', 'DIALOGUE', 'ANIMATE_SHOT'],
            'model' => 'act_two',
            'cost_per_second_usd' => 0.05,
            'best_for' => 'Precision facial acting, speech performance, gestures and per-character dialogue passes',
            'limitations' => 'One character input per pass; 3–30s driving performance. Multi-character scenes require separate passes and compositing.',
            'implemented' => true,
        ],
        'runway_gen45' => [
            'class' => RunwayDescribeProvider::class,
            'label' => 'Runway Gen-4.5',
            'binding_key' => 'runway',
            'capabilities' => ['DESCRIBE_SHOT', 'ANIMATE_SHOT'],
            'model' => 'gen4.5',
            'cost_per_second_usd' => 0.12,
            'best_for' => 'Simple natural-language shots from text or one strong visual anchor',
            'limitations' => '2–10s per generation. Single prompt image path; use Seedance Reference when multiple anchors matter.',
            'implemented' => true,
        ],
        'runway_seedance25_reference' => [
            'class' => RunwayReferenceProvider::class,
            'label' => 'Runway Seedance 2.5 Reference',
            'binding_key' => 'runway',
            'capabilities' => ['DESCRIBE_SHOT', 'MULTI_REFERENCE', 'ANIMATE_SHOT'],
            'model' => 'seedance2_5',
            'cost_per_second_usd' => 0.30,
            'best_for' => 'Character + environment + motion + audio reference-aware shots',
            'limitations' => '720p reference path. Up to 30 image, 10 video and 10 audio references; reference video/audio duration limits still apply.',
            'implemented' => true,
        ],
        'runway_seedance25_extend' => [
            'class' => RunwayContinueProvider::class,
            'label' => 'Runway Seedance 2.5 Extend',
            'binding_key' => 'runway',
            'capabilities' => ['CONTINUE_SHOT', 'MULTI_REFERENCE', 'ANIMATE_SHOT'],
            'model' => 'seedance2_5',
            'cost_per_second_usd' => 0.30,
            'best_for' => 'Native video continuation from the previous generated take',
            'limitations' => 'Video-to-video extend; 4–30s output. Uses the source video as continuity input and preserves its aspect ratio.',
            'implemented' => true,
        ],
        'vidu_motion_2_5' => [
            'class' => ViduProvider::class,
            'label' => 'Vidu Motion Sync 2.5',
            'binding_key' => 'vidu',
            'capabilities' => ['ACT_IT', 'ANIMATE_SHOT'],
            'model' => 'motion_control_2.5',
            'cost_per_second_usd' => 0.17,
            'best_for' => 'Full-body movement',
            'limitations' => '3–30s reference action; one character image.',
            'implemented' => true,
        ],
    ];
}

function ad_provider_instance(string $providerId): ADProvider {
    $registry = ad_provider_registry();
    if (!isset($registry[$providerId])) throw new InvalidArgumentException('Unknown provider.');
    $class = $registry[$providerId]['class'];
    /** @var ADProvider $provider */
    $provider = new $class();
    return $provider;
}

function ad_gateway_catalog(): array {
    $out = [];
    foreach (ad_provider_registry() as $id => $meta) {
        $provider = ad_provider_instance($id);
        $configured = $provider->available();
        $out[] = [
            'id'=>$id,'provider'=>$id,'label'=>$meta['label'],'binding_key'=>$meta['binding_key'],
            'capabilities'=>$meta['capabilities'],'availability'=>$configured,'available'=>$configured,
            'estimated_cost_per_second_usd'=>$meta['cost_per_second_usd'],'cost_per_second_usd'=>$meta['cost_per_second_usd'],
            'configured'=>$configured,'mock'=>ad_mock_mode(),'live'=>!ad_mock_mode() && $configured && !empty($meta['implemented']),
            'model'=>$meta['model'],'best_for'=>$meta['best_for'],'limitations'=>$meta['limitations'],'implemented'=>(bool)$meta['implemented'],
        ];
    }
    return $out;
}

function ad_providers_for_capability(string $capability): array {
    $capability = strtoupper($capability);
    return array_values(array_filter(ad_gateway_catalog(), static fn(array $p): bool => in_array($capability, $p['capabilities'], true)));
}

function ad_safe_provider_error(Throwable $e): array {
    $message = ad_substr($e->getMessage(), 0, 400);
    $message = preg_replace('/(api[_-]?key|bearer|token|authorization)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
    return ['error_code'=>'PROVIDER_ERROR','safe_error'=>$message];
}

function ad_require_live_public_https_url(string $url): void {
    $url = trim($url); $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? '')); $host = strtolower((string)($parts['host'] ?? ''));
    if ($url === '' || $scheme !== 'https' || $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || $host === '::1') {
        throw new RuntimeException('Live providers require public HTTPS media URLs. Deploy the app or configure ANIME_DIRECTOR_BASE_URL.');
    }
}

function ad_assert_live_media_urls(string $characterUrl, string $performanceUrl): void {
    if (ad_mock_mode()) return;
    ad_require_live_public_https_url($characterUrl); ad_require_live_public_https_url($performanceUrl);
}

function ad_provider_accepted_attempts(array $state, string $shotId, string $providerId): int {
    $count = 0;
    foreach ($state['jobs'] as $j) {
        if (($j['shot_id'] ?? '') !== $shotId || ($j['provider'] ?? '') !== $providerId) continue;
        if (trim((string)($j['provider_job_id'] ?? $j['external_id'] ?? '')) !== '') $count++;
    }
    return $count;
}

function ad_shot_has_takes(array $state, string $shotId): bool {
    foreach ($state['takes'] as $t) if (($t['shot_id'] ?? '') === $shotId) return true;
    return false;
}
