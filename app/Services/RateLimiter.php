<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/**
 * Class RateLimiter
 * Lightweight file-based token bucket / sliding window rate limiter.
 */
class RateLimiter
{
    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = (string) (App::getConfig('paths')['ratelimit'] ?? (dirname(__DIR__, 2) . '/storage/ratelimit'));
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
    }

    /**
     * Check if an action is allowed for a given key within a time window
     *
     * @param string $key Identifier (e.g. IP address, peerId)
     * @param int $maxAttempts Maximum allowed hits in window
     * @param int $windowSeconds Window duration in seconds
     * @return bool True if allowed, false if limit exceeded
     */
    public function hit(string $key, int $maxAttempts = 30, int $windowSeconds = 10): bool
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        $file = $this->storageDir . '/' . md5($safeKey) . '.json';
        $now = time();

        $fp = fopen($file, 'c+');
        if (!$fp) {
            return true; // Fail open if file system has error
        }

        flock($fp, LOCK_EX);
        try {
            $raw = stream_get_contents($fp);
            $data = $raw ? json_decode($raw, true) : null;

            if (!is_array($data) || ($data['reset_at'] ?? 0) < $now) {
                // Initialize new window
                $data = [
                    'count' => 1,
                    'reset_at' => $now + $windowSeconds,
                ];
            } else {
                $data['count'] = (int) ($data['count'] ?? 0) + 1;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);

            return $data['count'] <= $maxAttempts;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }
}
