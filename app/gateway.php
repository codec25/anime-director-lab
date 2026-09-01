<?php
declare(strict_types=1);

/**
 * AI Gateway — application code requests CAPABILITIES, not raw model names.
 * Secrets stay server-side; the frontend only sees availability/cost metadata.
 */

function ad_capabilities(): array {
    return [
        'ACT_IT' => 'Preserve a human performance on a locked character.',
        'ANIMATE_SHOT' => 'Animate a shot from approved direction.',
        'DESCRIBE_SHOT' => 'Text/direction-driven shot generation.',
        'DIALOGUE' => 'Dialogue-driven performance assist (not wired).',
        'CONTINUE_SHOT' => 'Continue motion from a prior take (not wired).',
        'LIP_SYNC' => 'Lip sync assist (not wired).',
        'ANIME_BOOST' => 'Stylized amplification layer over preserved performance.',
        'SOUND_EFFECT' => 'Impact/SFX direction (editable-effects future).',
    ];
}

function ad_provider_registry(): array {
    return [
        'runway_act_two' => [
            'class' => RunwayProvider::class,
            'label' => 'Runway Act-Two',
            'binding_key' => 'runway',
            'capabilities' => ['ACT_IT', 'ANIMATE_SHOT'],
            'model' => 'act_two',
            'cost_per_second_usd' => 0.05,
            'best_for' => 'Acting / gesture / dialogue',
            'limitations' => 'Max ~30s driving performance; character image/video input.',
            'implemented' => true,
        ],
        'runway_gen45' => [
            'class' => RunwayDescribeProvider::class,
            'label' => 'Runway Gen-4.5',
            'binding_key' => 'runway',
            'capabilities' => ['DESCRIBE_SHOT', 'ANIMATE_SHOT'],
            'model' => 'gen4.5',
            'cost_per_second_usd' => 0.12,
            'best_for' => 'Natural-language anime shot generation from text or a locked reference image',
            'limitations' => '2–10s per generation. Text-only is landscape/portrait; image-to-video supports more ratios.',
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
        'kling_motion' => [
            'class' => KlingProvider::class,
            'label' => 'Kling Motion',
            'binding_key' => 'kling',
            'capabilities' => ['ACT_IT', 'ANIMATE_SHOT'],
            'model' => null,
            'cost_per_second_usd' => null,
            'best_for' => 'Future motion adapter',
            'limitations' => 'Adapter stub — no live API calls.',
            'implemented' => false,
        ],
        'google_veo' => [
            'class' => GoogleProvider::class,
            'label' => 'Google (Veo / future)',
            'binding_key' => 'google',
            'capabilities' => ['DESCRIBE_SHOT', 'ANIMATE_SHOT'],
            'model' => null,
            'cost_per_second_usd' => null,
            'best_for' => 'Future describe/animate adapter',
            'limitations' => 'Adapter stub — no live API calls.',
            'implemented' => false,
        ],
        'wan2_2_animate' => [
            'class' => WanProvider::class,
            'label' => 'Wan2.2-Animate-14B',
            'binding_key' => 'wan',
            'capabilities' => ['ACT_IT', 'ANIMATE_SHOT'],
            'model' => 'Wan2.2-Animate-14B',
            'cost_per_second_usd' => null,
            'best_for' => 'Open-source / local GPU benchmark',
            'limitations' => 'External GPU runner later — not hosted in this PHP lab.',
            'implemented' => false,
        ],
    ];
}

function ad_provider_instance(string $providerId): ADProvider {
    $registry = ad_provider_registry();
    if (!isset($registry[$providerId])) {
        throw new InvalidArgumentException('Unknown provider.');
    }
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
            'id' => $id,
            'provider' => $id,
            'label' => $meta['label'],
            'binding_key' => $meta['binding_key'],
            'capabilities' => $meta['capabilities'],
            'availability' => $configured,
            'available' => $configured,
            'estimated_cost_per_second_usd' => $meta['cost_per_second_usd'],
            'cost_per_second_usd' => $meta['cost_per_second_usd'],
            'configured' => $configured,
            'mock' => ad_mock_mode(),
            'live' => !ad_mock_mode() && $configured && !empty($meta['implemented']),
            'model' => $meta['model'],
            'best_for' => $meta['best_for'],
            'limitations' => $meta['limitations'],
            'implemented' => (bool)$meta['implemented'],
        ];
    }
    return $out;
}

function ad_providers_for_capability(string $capability): array {
    $capability = strtoupper($capability);
    return array_values(array_filter(
        ad_gateway_catalog(),
        static fn(array $p): bool => in_array($capability, $p['capabilities'], true)
    ));
}

function ad_safe_provider_error(Throwable $e): array {
    $message = ad_substr($e->getMessage(), 0, 400);
    // Never echo env/key material.
    $message = preg_replace('/(api[_-]?key|bearer|token|authorization)\s*[:=]\s*\S+/i', '$1=[redacted]', $message) ?? $message;
    return [
        'error_code' => 'PROVIDER_ERROR',
        'safe_error' => $message,
    ];
}

function ad_require_live_public_https_url(string $url): void {
    $url = trim($url);
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    if (
        $url === ''
        || $scheme !== 'https'
        || $host === ''
        || $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '[::1]'
        || $host === '::1'
    ) {
        throw new RuntimeException('Live providers require public HTTPS character and performance URLs. Deploy the lab or configure ANIME_DIRECTOR_BASE_URL.');
    }
}

/** Live ACT_IT media must be externally fetchable HTTPS URLs. Mock mode skips this. */
function ad_assert_live_media_urls(string $characterUrl, string $performanceUrl): void {
    if (ad_mock_mode()) return;
    ad_require_live_public_https_url($characterUrl);
    ad_require_live_public_https_url($performanceUrl);
}

/** Count only provider-accepted jobs (those with a real task id). */
function ad_provider_accepted_attempts(array $state, string $shotId, string $providerId): int {
    $count = 0;
    foreach ($state['jobs'] as $j) {
        if (($j['shot_id'] ?? '') !== $shotId || ($j['provider'] ?? '') !== $providerId) continue;
        $external = trim((string)($j['provider_job_id'] ?? $j['external_id'] ?? ''));
        if ($external !== '') $count++;
    }
    return $count;
}

function ad_shot_has_takes(array $state, string $shotId): bool {
    foreach ($state['takes'] as $t) {
        if (($t['shot_id'] ?? '') === $shotId) return true;
    }
    return false;
}
