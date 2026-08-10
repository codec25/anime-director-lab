# iOS readiness

Anime Director remains a PHP/JavaScript web application. The current goal is excellent iPhone/iPad behavior plus a clean path to a future native shell, not a Swift rewrite.

## Current foundation

- `viewport-fit=cover` and safe-area layout support
- standalone/PWA metadata and application manifest
- 44px minimum mobile interaction targets and iOS-safe form sizing
- `window.ADPlatform` capability/runtime detection
- configurable `AD_CONFIG.apiBase` for a future packaged client
- existing PHP API and provider gateway remain reusable

## Deliberately deferred

- Service worker: wait until the real proof flow is stable so API/PHP responses are never accidentally cached.
- Capacitor project: add only when a native shell provides concrete value.
- Native camera, notifications, files, subscriptions and background jobs: expose behind platform adapters when each feature is introduced.
- Xcode/signing/TestFlight/App Store work: requires macOS later; normal product development remains Windows-compatible.

## Architecture rule

Feature code must call a capability adapter rather than assuming a browser or native implementation. The web/PWA path remains functional when native capabilities are unavailable.
