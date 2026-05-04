<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fixed-window rate limiter stored as JSON files (per client key). Not for multi-node clusters.
 */
final class TokenGenerateRateLimiter
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly string $storageDir,
    ) {
    }

    /**
     * @return array{allowed: bool, retry_after: int|null}
     */
    public function hit(string $clientKey): array
    {
        if ($this->maxAttempts <= 0) {
            return ['allowed' => true, 'retry_after' => null];
        }

        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0755, true) && !is_dir($this->storageDir)) {
            return ['allowed' => true, 'retry_after' => null];
        }

        $windowIndex = (int)floor(time() / max(1, $this->windowSeconds));
        $path = $this->storageDir . '/tg_' . hash('sha256', $clientKey) . '.json';

        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return ['allowed' => true, 'retry_after' => null];
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                return ['allowed' => true, 'retry_after' => null];
            }

            rewind($fh);
            $raw = stream_get_contents($fh);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $w = is_array($data) ? (int)($data['w'] ?? -1) : -1;
            $c = is_array($data) ? (int)($data['c'] ?? 0) : 0;

            if ($w !== $windowIndex) {
                $w = $windowIndex;
                $c = 0;
            }

            if ($c >= $this->maxAttempts) {
                $windowEnd = ($windowIndex + 1) * $this->windowSeconds;
                $retry = max(1, $windowEnd - time());

                return ['allowed' => false, 'retry_after' => $retry];
            }

            $c++;
            $payload = json_encode(['w' => $w, 'c' => $c]);
            if ($payload === false) {
                return ['allowed' => true, 'retry_after' => null];
            }

            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, $payload);
            fflush($fh);

            return ['allowed' => true, 'retry_after' => null];
        } catch (\Throwable) {
            return ['allowed' => true, 'retry_after' => null];
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
