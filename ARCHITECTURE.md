# Anime Director Lab — Architecture

## 1. Product mission

Anime Director is not a generic AI anime generator.

**Perform it. Direct it. Animate it.**

The creator remains the director/performer. AI executes difficult animation work. The lab validates whether one locked original character can convincingly perform a human-recorded movement across takes and providers.

## 2. Production hierarchy

```
PRODUCTION
├── Character Bible
├── World Bible (stub)
├── Scenes
│   └── Shots
│       ├── Performances (human source)
│       ├── Generation Jobs
│       └── Generated Takes
└── Final Cut (selected takes / future export)
```

Current lab defaults to one production and Scene 01.

## 3. Character Bible ownership

The Character Bible owns identity:

- versioned character record (`AKIO-v1`)
- identity notes (body, face, hair, eyes, outfit, movement, voice)
- canonical reference roles (`master_front`, `three_quarter`, `portrait`, …)
- optional `provider_bindings` beneath the bible

Canonical character identity must never depend on a provider ID.

Creation paths prepared in UX:

1. Upload character
2. Import character sheet
3. Generated reference (future)
4. Draw character (future)

## 4. Performance vs generated take

Critical distinction:

- **Performance** = human-recorded source media for ACT IT
- **Generated take** = provider output for a shot/job

A recorded performance is never collapsed into final scene media. Directors compare generated takes and choose one.

## 5. AI Gateway

Application code requests **capabilities**, not raw vendor model names:

`ACT_IT`, `ANIMATE_SHOT`, `DESCRIBE_SHOT`, `DIALOGUE`, `CONTINUE_SHOT`, `LIP_SYNC`, `ANIME_BOOST`, `SOUND_EFFECT`

Providers are adapters. Implemented: Runway, Vidu. Stubs: Kling, Google, Wan/local.

Secrets stay server-side in `.env`. Frontend only receives availability/cost/limitation metadata.

## 6. Generation jobs

Jobs track capability, provider, model, attempt, provider job id, timestamps, estimated/actual cost, and safe errors.

Statuses: `queued` → `submitted` → `processing` → `completed` | `failed` | `cancelled`.

Policy: **max 3 attempts per provider per shot**. No silent paid retries beyond that.

## 7. Anime Boost

Anime Boost is a **distinct direction layer** after performance direction.

Modes: `natural` | `anime` | `extreme`.

Structured metadata (anticipation, exaggeration, impact, follow-through, speed, camera reaction, vfx intensity) is stored even when providers cannot consume it yet.

Two future layers:

1. AI motion/style enhancement
2. Editable post/compositing effects (speed lines, impact flash, SFX, …)

Do not claim these are fully solved provider features today.

## 8. Benchmark system

A1–A4 / B1–B4 tests with 12-part scoring, CPS / PPS / DUS, usable yes/no, attempt + cost tracking, and a compact provider×test summary.

## 9. What is experimental

- Mock ACT IT workflow (no paid spend)
- DESCRIBE IT architecture/UI without live generation
- Anime Boost as creative direction metadata
- Scene filmstrip / cheap animatic placeholders
- Stub provider adapters

## 10. Deliberately not built yet

- Accounts / auth / subscriptions / billing
- Social, community, marketplace
- Full drawing canvas or local character generator
- Giant timeline / After Effects compositor
- Live Kling / Google / Wan integrations
- World Bible depth, collaboration, SHORTS merge
