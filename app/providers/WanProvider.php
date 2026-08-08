<?php
declare(strict_types=1);

/** Stub adapter — Wan remains an external/local GPU benchmark. */
final class WanProvider implements ADProvider {
    public function id(): string { return 'wan2_2_animate'; }
    public function available(): bool { return false; }
    public function estimatedUsd(float $seconds): float { return 0.0; }

    public function submit(array $input): array {
        throw new RuntimeException('Wan adapter requires an external GPU runner.');
    }

    public function poll(string $externalId): array {
        throw new RuntimeException('Wan adapter requires an external GPU runner.');
    }
}
