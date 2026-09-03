# Anime Director — Architecture

## Product mission

Anime Director is a Director-first AI anime filmmaking workspace. The creator describes, performs, revises, references and continues shots while the app preserves production context underneath.

The normal product should feel simple:

`Direct → Generate → Review → Revise / Continue → Build Scene`

The Advanced workspace exists for technical controls such as ACT IT, provider comparisons and manual shot details. It must not compete with the creator-facing workflow.

## Canonical routes

- `index.php` → product entry / Director redirect
- `director.php` → primary creator workspace
- `lab.php` → Advanced workspace
- `api.php` → shared production/state API
- `director-api.php` → references, revisions and describe-to-video generation
- `continue-api.php` → native video continuation

There must not be two competing creator home screens.

## Production hierarchy

```text
PRODUCTION
├── Character Bible
├── World / location context
├── Scenes
│   └── Shots
│       ├── Director instructions
│       ├── Shot references
│       ├── Revision history
│       ├── Continuity source
│       ├── Performance source (optional ACT IT)
│       ├── Generation Jobs
│       └── Generated Takes
└── Final sequence / export
```

## Character continuity

Character identity belongs to the Character Bible, never to a provider ID. The locked master reference and character notes remain reusable across provider changes.

## Director references

A shot can own image, video or audio references. References stay attached to the shot and therefore survive revisions.

Current generation behavior:
- latest image shot reference can become the primary visual prompt for `DESCRIBE_SHOT`
- other reference types remain production context until the selected provider supports native multi-reference binding

## Revisions

A revision modifies the existing shot instead of creating an unrelated replacement. Revision history preserves the previous direction. Unspecified camera, ratio and style fields stay intact. Any previous selected take is cleared when the shot is revised so a new execution can be reviewed explicitly.

## Native continuation

A continued shot records:
- `continuity_from_shot_id`
- `source_take_id`
- the new next-action direction

When the previous shot has a usable generated take, Anime Director uses Runway Seedance 2.5 video-to-video `extend` through the `CONTINUE_SHOT` capability. The prior generated video is supplied directly as `promptVideo`, so continuity is based on the real source video rather than only text metadata.

If the previous shot has no generated take yet, the continuation shot is still created but native continuation generation remains blocked until a source take exists.

Normal first-shot generation remains separate and uses `DESCRIBE_SHOT` (currently Runway Gen-4.5).

## Two creation paths

### DIRECT IT

Natural-language direction creates `DESCRIBE_IT` shots. A describe-to-video provider can generate normal shots directly from those instructions and character/reference context.

### ACT IT

When motion fidelity matters more — acting, dance, martial movement, gesture, timing — the creator can upload a human performance in Advanced and generate an `ACT_IT` take.

Both paths use the same scenes, shots and takes. They are not separate products.

## Capability gateway

Application code requests capabilities rather than exposing provider-specific model logic throughout the UI:

- `ACT_IT`
- `ANIMATE_SHOT`
- `DESCRIBE_SHOT`
- `CONTINUE_SHOT`
- `DIALOGUE`
- `LIP_SYNC`
- `ANIME_BOOST`
- `SOUND_EFFECT`

Current implemented provider paths:
- Runway Act-Two → `ACT_IT`
- Runway Gen-4.5 → `DESCRIBE_SHOT`
- Runway Seedance 2.5 Extend → `CONTINUE_SHOT`
- Vidu Motion Sync 2.5 → `ACT_IT`

Secrets remain server-side.

## Jobs and spend safety

Generation jobs track provider/model/capability, attempts, task IDs, timestamps, estimated cost and safe errors. Paid provider attempts remain capped at three per provider per shot. Creator-facing generation requires an explicit action; the app must not silently spend credits.

## Anime Boost

Anime Boost is direction metadata layered over a shot. It can encode anticipation, exaggeration, impact, follow-through, speed, camera reaction and VFX intensity. The UI must not claim an effect is rendered unless the active provider or post pipeline really supports it.

## Product rule

Only show creator-facing controls that perform a real action. Experimental diagnostics, scoring, provider comparison and performance tooling belong in Advanced. Dead-end buttons, duplicate scripts, fake controls and competing navigation paths should be removed rather than preserved for history.

## Next production layers

1. provider-native multi-reference binding
2. persistent world/location memory
3. dialogue, voices and lip sync
4. sound design
5. longer multi-shot orchestration and final-cut export
