# Anime Director Lab 0.01 — The Akio Test

A deliberately small, standalone research lab for testing the core Anime Director hypothesis:

> Can one persistent original anime character convincingly perform a human-recorded movement, across repeated takes and providers?

## What this build does

- Locks one canonical character (`AKIO-v1` by default).
- Uploads controlled ACT IT performance clips (A1–A4 / B1–B4).
- Creates shot records separately from performances.
- Stores Anime Boost direction (`natural`, `anime`, `extreme`) separately from the source motion.
- Routes ACT IT generation through provider adapters.
- Includes current adapters for Runway Act-Two and Vidu Motion Sync 2.5.
- Enforces max 3 attempts per provider per shot.
- Polls asynchronous generation jobs.
- Downloads completed remote results locally when possible.
- Compares takes and lets the director select one.
- Scores the 12-part benchmark and calculates CPS/PPS/DUS.
- Records estimated spend so the experiment measures cost per accepted take.
- Has mock mode for verifying the workflow before spending AI credits.

## What this build intentionally does NOT do

- No accounts or subscriptions.
- No social/community features.
- No episode manager.
- No giant timeline.
- No AI image generation yet.
- No Wan GPU runner yet (Wan2.2-Animate remains an external/open-source benchmark).
- Anime Boost is direction metadata in 0.01; final generative/compositing amplification is a separate experiment after ACT IT fidelity is validated.

## Setup

1. Upload this folder as its own app. Do not merge it into SHORTS.
2. Copy `.env.example` to `.env`.
3. Set `ANIME_DIRECTOR_BASE_URL` to the public HTTPS URL of this folder.
4. Leave `ANIME_DIRECTOR_MOCK_MODE=1` while checking the workflow.
5. For live tests set it to `0` and provide provider keys:
   - `RUNWAY_API_KEY`
   - `VIDU_API_KEY`
6. Ensure PHP has cURL + fileinfo enabled and `storage/` + `data/` are writable by PHP.

## First live directed take

Use this checklist for the first controlled Runway Act-Two test:

1. Deploy the app over HTTPS and set `ANIME_DIRECTOR_BASE_URL` to its public URL.
2. Keep `ANIME_DIRECTOR_MOCK_MODE=1` while you lock the character and verify uploads.
3. Add `RUNWAY_API_KEY` only to the server environment, then switch `ANIME_DIRECTOR_MOCK_MODE=0` when you are ready for one paid attempt.
4. Upload a clear character reference and a 3–5 second A1 neutral-acting performance.
5. Create one `ACT_IT` shot and click **Runway ACT IT** once. The browser monitors the submitted job automatically; it does not submit automatic retries or extra generations.
6. Leave the page open while the job runs. If monitoring pauses, use **Resume monitoring** on the same job.
7. The app saves a completed result into `storage/results/` before creating a take. Score it, mark it usable if appropriate, and choose it for the scene.

If remote generation completes but the result file cannot be captured immediately, the existing job stays recoverable and is retried without creating another paid generation attempt.

See `ARCHITECTURE.md` for the foundation data model (Character Bible, performances vs takes, AI Gateway, Anime Boost, scenes).

## Benchmark order

1. A1 neutral acting
2. A2 emotional dialogue
3. A3 gesture
4. A4 upper-body action
5. B1 walk & turn
6. B2 groove / footwork
7. B3 martial combination
8. B4 jump / turn

Start with A1. Do not burn credits on all eight before the simplest test passes.

## Security

- Keep `.env` outside Git.
- Uploads are MIME-validated and randomly renamed.
- PHP/script execution is blocked inside `storage/` via `.htaccess`.
- Production use will need authentication, CSRF protection, quotas, object storage, retention controls, and provider webhook verification. This lab is not a public SaaS release.

## SHORTS relationship

SHORTS remains separate. This lab borrows only proven concepts: safe media storage, takes, asynchronous jobs, retries/attempt discipline, cost events, and selected-take workflow.
