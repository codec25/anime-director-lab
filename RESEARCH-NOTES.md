# Fast verification — August 8, 2026

The first benchmark is intentionally limited to provider capabilities that are currently verifiable.

## Runway Act-Two
- Character performance endpoint is available in the Runway API.
- Current pricing: 5 credits/second; developer credits are $0.01 each.
- Driving performance is a video; character can be an image/video; maximum duration is 30 seconds.
- Lab role: primary acting / dialogue / gesture benchmark.

## Vidu Motion Sync 2.5
- Current Motion Sync V2 template accepts one character image plus a 3–30 second reference action video.
- Motion Control 2.5 costs 34 credits/second; Vidu credits are $0.005 each = about $0.17/second raw generation.
- Lab role: primary full-body movement benchmark.

## Wan2.2-Animate-14B
- Official open-source baseline accepts a character image and driving video and supports character animation/replacement.
- Keep as an external GPU benchmark; do not force it into the Hostinger/PHP app.

## Wan-Animate-2
- New research published August 2026 directly consumes driving video and reports improved identity/motion fidelity plus viewpoint control.
- Paper says public Base weights will be released; do not treat it as a live production dependency until that release is verifiable.
