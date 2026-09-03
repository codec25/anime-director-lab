<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

function adats_find(array $items, string $id): ?array {
    foreach ($items as $item) if (is_array($item) && (string)($item['id'] ?? '') === $id) return $item;
    return null;
}

function adats_take_asset(array $take): ?array {
    $local = is_array($take['local'] ?? null) ? $take['local'] : (is_array($take['result_media']['local'] ?? null) ? $take['result_media']['local'] : null);
    $url = trim((string)($local['url'] ?? $take['remote_url'] ?? $take['result_media']['remote_url'] ?? ''));
    if ($url === '') return null;
    $asset = is_array($local) ? $local : [];
    $asset['kind'] = 'video';
    $asset['url'] = $url;
    $asset['role'] = 'character_video';
    $asset['source'] = 'approved_take';
    $asset['source_take_id'] = (string)($take['id'] ?? '');
    return $asset;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ad_json(['ok' => false, 'error' => 'POST required.'], 405);
    $data = ad_json_input();
    $shotId = trim((string)($data['shot_id'] ?? ''));
    $passId = trim((string)($data['pass_id'] ?? ''));
    if ($shotId === '' || $passId === '') ad_json(['ok' => false, 'error' => 'shot_id and pass_id are required.'], 422);

    $state = ad_state_read();
    $shot = adats_find((array)($state['shots'] ?? []), $shotId);
    if (!$shot) ad_json(['ok' => false, 'error' => 'Shot not found.'], 404);
    $orch = is_array($shot['act_two_orchestration'] ?? null) ? $shot['act_two_orchestration'] : [];
    $passes = array_values(array_filter((array)($orch['passes'] ?? []), 'is_array'));
    if (count($passes) !== 1) ad_json(['ok' => false, 'error' => 'Automatic scene-preserving performance is only safe for a single speaking character.'], 409);
    if ((string)($passes[0]['id'] ?? '') !== $passId) ad_json(['ok' => false, 'error' => 'Performance pass not found.'], 404);

    $baseTakeId = trim((string)($orch['base_take_id'] ?? $shot['selected_take_id'] ?? ''));
    $take = $baseTakeId !== '' ? adats_find((array)($state['takes'] ?? []), $baseTakeId) : null;
    if (!$take) ad_json(['ok' => false, 'error' => 'Keep a generated take before adding precision performance.'], 409);
    $asset = adats_take_asset($take);
    if (!$asset) ad_json(['ok' => false, 'error' => 'The approved take has no usable video.'], 409);

    $next = ad_state_mutate(function(array $s) use ($shotId, $passId, $asset, $baseTakeId): array {
        foreach ($s['shots'] as &$sh) {
            if ((string)($sh['id'] ?? '') !== $shotId) continue;
            $o = is_array($sh['act_two_orchestration'] ?? null) ? $sh['act_two_orchestration'] : [];
            foreach ($o['passes'] as &$pass) {
                if ((string)($pass['id'] ?? '') !== $passId) continue;
                $pass['isolated_character_video'] = $asset;
                $pass['character_source_mode'] = 'approved_take_video';
                $pass['source_take_id'] = $baseTakeId;
                if (!empty($pass['driving_performance'])) $pass['status'] = 'ready';
                $pass['last_error'] = null;
                break;
            }
            unset($pass);
            $o['updated_at'] = ad_now();
            $sh['act_two_orchestration'] = $o;
            break;
        }
        unset($sh);
        return $s;
    });

    ad_event('act_two_base_take_linked', ['shot_id' => $shotId, 'pass_id' => $passId, 'take_id' => $baseTakeId]);
    ad_json(['ok' => true, 'shot' => adats_find((array)$next['shots'], $shotId), 'source_take_id' => $baseTakeId]);
} catch (Throwable $e) {
    ad_json(['ok' => false, 'error' => ad_substr($e->getMessage(), 0, 400)], 500);
}
