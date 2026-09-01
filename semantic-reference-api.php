<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$action = (string)($_GET['action'] ?? 'roles');

function adsr_roles(): array {
    return ['character','environment','prop','style','motion','voice','sound','reference'];
}
function adsr_find_shot(array $shots, string $id): ?array {
    foreach ($shots as $shot) if ((string)($shot['id'] ?? '') === $id) return $shot;
    return null;
}
function adsr_role(string $role): string {
    $role = strtolower(trim($role));
    if (!in_array($role, adsr_roles(), true)) throw new InvalidArgumentException('Unknown reference role.');
    return $role;
}

try {
    if ($action === 'roles') {
        ad_json(['ok'=>true,'roles'=>adsr_roles()]);
    }

    if ($action === 'set-role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d = ad_json_input();
        $scope = strtolower(trim((string)($d['scope'] ?? 'shot')));
        $referenceId = trim((string)($d['reference_id'] ?? ''));
        $shotId = trim((string)($d['shot_id'] ?? ''));
        $role = adsr_role((string)($d['role'] ?? 'reference'));
        if ($referenceId === '') ad_json(['ok'=>false,'error'=>'Reference id is required.'],422);
        if (!in_array($scope,['shot','world'],true)) ad_json(['ok'=>false,'error'=>'Invalid reference scope.'],422);
        if ($scope === 'shot' && $shotId === '') ad_json(['ok'=>false,'error'=>'Shot id is required.'],422);

        $changed = false;
        $next = ad_state_mutate(function(array $state) use ($scope,$referenceId,$shotId,$role,&$changed): array {
            if ($scope === 'world') {
                $refs = (array)($state['world']['references'] ?? []);
                foreach ($refs as &$ref) {
                    if ((string)($ref['id'] ?? '') !== $referenceId) continue;
                    $ref['role'] = $role;
                    $changed = true;
                    break;
                }
                unset($ref);
                $state['world']['references'] = $refs;
                if ($changed) {
                    $state['world']['version'] = max(1,(int)($state['world']['version'] ?? 1)) + 1;
                    $state['world']['updated_at'] = ad_now();
                }
            } else {
                foreach ($state['shots'] as &$shot) {
                    if ((string)($shot['id'] ?? '') !== $shotId) continue;
                    $refs = (array)($shot['references'] ?? []);
                    foreach ($refs as &$ref) {
                        if ((string)($ref['id'] ?? '') !== $referenceId) continue;
                        $ref['role'] = $role;
                        $changed = true;
                        break;
                    }
                    unset($ref);
                    $shot['references'] = $refs;
                    if ($changed) $shot['updated_at'] = ad_now();
                    break;
                }
                unset($shot);
            }
            return $state;
        });
        if (!$changed) ad_json(['ok'=>false,'error'=>'Reference not found.'],404);
        ad_event('reference_role_changed',['scope'=>$scope,'shot_id'=>$shotId ?: null,'reference_id'=>$referenceId,'role'=>$role]);
        ad_json(['ok'=>true,'role'=>$role,'state'=>$next]);
    }

    ad_json(['ok'=>false,'error'=>'Unknown semantic-reference action.'],404);
} catch (Throwable $e) {
    ad_json(['ok'=>false,'error'=>ad_substr($e->getMessage(),0,400)],500);
}
