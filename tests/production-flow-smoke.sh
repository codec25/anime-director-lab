#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
STATE="data/lab.json"
BACKUP="$(mktemp)"
HAD_STATE=0
if [[ -f "$STATE" ]]; then cp "$STATE" "$BACKUP"; HAD_STATE=1; fi
SERVER_PID=""
cleanup(){
  if [[ -n "$SERVER_PID" ]]; then kill "$SERVER_PID" 2>/dev/null || true; fi
  if [[ "$HAD_STATE" == "1" ]]; then cp "$BACKUP" "$STATE"; else rm -f "$STATE"; fi
  rm -f "$BACKUP"
}
trap cleanup EXIT

export ANIME_DIRECTOR_MOCK_MODE=1
php tests/seed-production-flow.php >/dev/null
php -S 127.0.0.1:8124 >/tmp/anime-director-production-flow.log 2>&1 &
SERVER_PID=$!
for i in {1..40}; do
  if curl -fsS 'http://127.0.0.1:8124/api.php?action=state' >/tmp/ad-flow-state.json; then break; fi
  sleep 0.2
done

curl -fsS 'http://127.0.0.1:8124/timeline-api.php?action=state' >/tmp/ad-flow-timeline-1.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-timeline-1.json"),true);$c=$d["timeline"]["clips"][0]??[];if(($c["take_id"]??"")!=="take_flow_1")fwrite(STDERR,"Timeline did not start on approved take 1\n")||exit(1);if(count($c["take_options"]??[])!==2)fwrite(STDERR,"Timeline did not expose both takes\n")||exit(1);if(($c["scene_ambience"]["id"]??"")!=="amb_flow"||($c["scene_music"]["id"]??"")!=="music_flow")fwrite(STDERR,"Generated scene audio did not reach Finish\n")||exit(1);'

curl -fsS -X POST -H 'Content-Type: application/json' --data '{"shot_id":"shot_flow_1","take_id":"take_flow_2"}' 'http://127.0.0.1:8124/take-api.php?action=select' >/tmp/ad-flow-select.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-select.json"),true);if(($d["shot"]["selected_take_id"]??"")!=="take_flow_2")exit(1);'

curl -fsS -X POST -H 'Content-Type: application/json' --data '{"shot_id":"shot_flow_1","title":"Reveal the creature","intent":"Cut behind Akio and reveal the creature.","direction":"Keep Akio, wardrobe, alley and lighting continuous."}' 'http://127.0.0.1:8124/director-api.php?action=continue-shot' >/tmp/ad-flow-continue.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-continue.json"),true);$s=$d["shot"]??[];if(($s["continuity_from_shot_id"]??"")!=="shot_flow_1"||($s["source_take_id"]??"")!=="take_flow_2")fwrite(STDERR,"Continuation did not inherit selected take 2\n")||exit(1);'

curl -fsS 'http://127.0.0.1:8124/continuity-review-api.php?action=state' >/tmp/ad-flow-continuity.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-continuity.json"),true);$shots=$d["scenes"][0]["shots"]??[];$first=$shots[0]??[];$codes=array_column($first["warnings"]??[],"code");if(!in_array("PRECISION_FAILED",$codes,true))fwrite(STDERR,"Continuity review missed failed precision pass\n")||exit(1);if(($first["selected_take_id"]??"")!=="take_flow_2")fwrite(STDERR,"Continuity review did not follow approved take\n")||exit(1);'

curl -fsS -X POST 'http://127.0.0.1:8124/sound-repair-api.php?action=sync' >/tmp/ad-flow-sound.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-sound.json"),true);if(empty($d["ok"]))exit(1);'

curl -fsS -X POST 'http://127.0.0.1:8124/timeline-api.php?action=reset' >/tmp/ad-flow-reset.json
curl -fsS 'http://127.0.0.1:8124/timeline-api.php?action=manifest' >/tmp/ad-flow-manifest.json
php -r '$d=json_decode(file_get_contents("/tmp/ad-flow-manifest.json"),true);$clips=$d["manifest"]["clips"]??[];$first=$clips[0]??[];if(($first["take_id"]??"")!=="take_flow_2")fwrite(STDERR,"Final manifest ignored approved take 2\n")||exit(1);if(($first["scene_ambience"]["id"]??"")!=="amb_flow"||($first["scene_music"]["id"]??"")!=="music_flow")fwrite(STDERR,"Final manifest lost scene sound\n")||exit(1);if(($d["manifest"]["duration_seconds"]??0)<=0)fwrite(STDERR,"Final manifest has no duration\n")||exit(1);'

echo 'Production flow smoke test passed.'
