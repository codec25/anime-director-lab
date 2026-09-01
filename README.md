# Anime Director

Anime Director is a Director-first AI anime filmmaking workspace.

The creator describes what should happen, generates a take, then revises or continues the scene while the app keeps character, shot, reference and continuity context underneath.

## Main workflow

1. Lock a character in Advanced.
2. Direct a shot in natural language.
3. Generate the shot with the configured describe provider.
4. Review the generated take.
5. Revise the same shot or continue into the next one.
6. Add image/video/audio references when needed.
7. Use ACT IT in Advanced when exact human motion is more important than text prompting.

## Product routes

- `/` → Anime Director entry
- `/director.php` → primary creator workspace
- `/lab.php` → Advanced workspace

## Current real capabilities

### Natural-language shot generation

Normal directed shots use the `DESCRIBE_SHOT` capability. The current implementation uses Runway Gen-4.5 for text/image-to-video.

### Character continuity

The Character Bible owns the canonical character identity and master references. Provider IDs never become the source of truth for the character.

### Shot references

Each shot can store image, video or audio references. The latest visual image reference can guide normal describe-to-video generation; other references stay attached to the shot for provider integrations that can consume them later.

### Conversational revisions

`Revise` updates the existing shot, stores revision history, keeps unspecified camera/ratio/style settings, and clears the old approval so a fresh take can be generated.

### Native video continuation

`Continue` creates a linked next shot. If the prior shot has a generated take, the next shot can use that actual video as its continuity source through Runway Seedance 2.5 video-to-video `extend`.

This is stronger than carrying only a text prompt: the previous moving video becomes the input to the next generation.

If there is no prior generated take yet, Anime Director tells the creator to generate the source shot first.

### ACT IT

Advanced retains performance-driven generation for acting, dance, martial movement and other motion where a human performance should drive the character.

## Provider safety

Secrets remain in server-side environment variables. The creator must explicitly start paid generation. Accepted paid attempts are capped at three per provider per shot.

Live Runway setup requires:

```text
RUNWAY_API_KEY=...
ANIME_DIRECTOR_BASE_URL=https://your-public-app-url
ANIME_DIRECTOR_MOCK_MODE=0
```

Never commit `.env`.

## Architecture principle

The normal creator experience must stay simple. If a control does not perform a real action, it should not appear in Director. Technical diagnostics and provider experimentation belong in Advanced.

See `ARCHITECTURE.md` for the production model and `HOSTINGER-UPLOAD.txt` for deployment files.
