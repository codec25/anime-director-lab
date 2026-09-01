# Anime Director — Architecture

## Product mission

Anime Director is not a generic prompt-to-video page and not a benchmark dashboard.

**Direct it. Perform it when needed. Generate takes. Build the scene.**

The creator-facing product is `director.php`. Natural-language direction is the default workflow. The advanced workspace lives in `lab.php` for character setup, performance-driven animation, take review, and deeper controls.

## Canonical routes

- `index.php` → redirects to Director
- `director.php` → primary creator experience
- `lab.php` → advanced/technical workspace
- `api.php` → shared production/state API
- `director-api.php` → describe-to-video generation jobs

There must not be two competing product home screens.

## Production hierarchy

```
PRODUCTION
├── Character Bible
├── World Bible
├── Scenes
│   └── Shots
│       ├── Direction
│       ├── Optional human performance
│       ├── Generation jobs
│       └── Generated takes
└── Final cut / export
```

## Character ownership

Character identity belongs to Anime Director, not a provider. The Character Bible stores versioned identity notes and canonical references. Provider-specific bindings stay optional beneath that identity.

## Two creation paths

### DIRECT IT

Natural-language direction creates `DESCRIBE_IT` shots. A describe-to-video provider can generate takes directly from those shots while preserving the locked character reference when supported.

### ACT IT

When body motion, acting, dance, martial movement, gesture, or timing needs stronger fidelity, the creator can upload a human performance in Advanced and generate an `ACT_IT` take.

These paths create the same underlying shot/take records. They are not separate products.

## AI gateway

Application code requests capabilities rather than exposing raw provider model logic throughout the UI.

Current capability vocabulary includes:

- `ACT_IT`
- `DESCRIBE_SHOT`
- `ANIMATE_SHOT`
- `CONTINUE_SHOT`
- `DIALOGUE`
- `LIP_SYNC`
- `ANIME_BOOST`
- `SOUND_EFFECT`

Implemented provider paths currently include Runway performance driving and Runway describe-to-video. Other adapters remain optional until truly wired.

Secrets stay server-side. Paid generation is never silently retried.

## Jobs and takes

A generation job records provider, capability, model, attempt, task id, status, timestamps, estimated cost, and safe errors. Completed provider output becomes a generated take attached to the same shot.

Policy: maximum 3 provider-accepted attempts per provider per shot unless deliberately changed later.

## Anime Boost

Anime Boost is direction metadata layered over a shot. It can encode anticipation, exaggeration, impact, follow-through, speed, camera reaction, and VFX intensity. The UI must not claim an effect is rendered unless the active provider or post pipeline really supports it.

## Product rule

Only show creator-facing controls that perform a real action. Experimental diagnostics, scoring, provider comparison, and performance tooling belong in Advanced. Dead-end buttons, duplicate scripts, fake attachment controls, and competing navigation paths should be removed rather than preserved for history.

## Next production capabilities

Priority order:

1. true per-shot reference attachments
2. edit/revise an existing shot through conversation
3. continue a selected take into the next shot
4. scene/world continuity memory
5. dialogue, voice, and lip sync
6. sound design
7. multi-shot orchestration and export
