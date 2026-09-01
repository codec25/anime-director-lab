# Anime Director

Anime Director is a Director-first anime filmmaking workspace.

The normal creator flow is conversational: describe the scene, action, camera, mood, or change; create a shot; generate takes; keep the character and production context attached. When exact body motion matters, switch to the Advanced workspace and drive the character from a recorded performance.

## Entry points

- `index.php` — canonical entry; redirects to Director
- `director.php` — primary creator UI
- `lab.php` — advanced character, performance, take, scene, and quality controls
- `api.php` — shared production API
- `director-api.php` — describe-to-video generation API

## Current working paths

### DIRECT IT

1. lock a character in Advanced
2. describe a shot in Director
3. Anime Director stores it as a production shot
4. generate a describe-to-video take when the provider is configured
5. review the take in the same production

### ACT IT

1. lock a character
2. upload a performance video
3. create an ACT IT shot
4. generate through a supported performance provider
5. compare/select the generated take

## Product principles

- Director is the product; Advanced is support tooling
- character identity is provider-independent
- shots, jobs, and takes stay attached to one production model
- paid generation requires explicit action
- do not show creator-facing controls that do nothing
- do not pretend planned capabilities are live
- preserve a path to iPhone/iPad and later native packaging without rebuilding the backend

## Configuration

Copy `.env.example` to `.env` on the server and configure the provider keys you intend to use. Provider secrets stay server-side.

For live provider access, uploaded media must be reachable through public HTTPS URLs. Configure `ANIME_DIRECTOR_BASE_URL` when automatic base URL detection is not appropriate.

## Next priorities

1. true shot-level image/video reference attachments
2. conversational revise/edit of an existing shot
3. continue-shot from a selected take
4. world/scene continuity memory
5. dialogue, voice, lip sync, and sound
6. multi-shot orchestration and export
