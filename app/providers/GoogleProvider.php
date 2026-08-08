<?php
declare(strict_types=1);

/** Stub adapter — no live Google/Veo API calls in this foundation sprint. */
final class GoogleProvider implements ADProvider {
    public function id(): string { return 'google_veo'; }
    public function available(): bool { return false; }
    public function estimatedUsd(float $seconds): float { return 0.0; }

    public function submit(array $input): array {
        throw new RuntimeException('Google adapter is not implemented yet.');
    }

    public function poll(string $externalId): array {
        throw new RuntimeException('Google adapter is not implemented yet.');
    }
}
