<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Helpers;

final class RateLimiter
{
    public function allow(string $bucket, int $max, int $windowSeconds): bool
    {
        $path = $this->path($bucket);
        $now = time();
        $state = ['count' => 0, 'started_at' => $now];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $state = array_merge($state, $decoded);
            }
        }

        if (($state['started_at'] + $windowSeconds) <= $now) {
            $state = ['count' => 0, 'started_at' => $now];
        }

        $state['count']++;
        file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);

        return (int) $state['count'] <= $max;
    }

    private function path(string $bucket): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $bucket) ?: 'bucket';
        $dir = __DIR__ . '/../../storage/logs/rate-limit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . $safe . '.json';
    }
}
