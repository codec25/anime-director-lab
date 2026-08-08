<?php
declare(strict_types=1);

/** Stub adapter — no live Kling API calls in this foundation sprint. */
final class KlingProvider implements ADProvider {
    public function id(): string { return 'kling_motion'; }
    public function available(): bool { return false; }
    public function estimatedUsd(float $seconds): float { return 0.0; }

    public function submit(array $input): array {
        throw new RuntimeException('Kling adapter is not implemented yet.');
    }

    public function poll(string $externalId): array {
        throw new RuntimeException('Kling adapter is not implemented yet.');
    }
}
