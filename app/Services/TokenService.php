<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Helpers\SecurityHelper;

/**
 * Class TokenService
 * Issues and validates HMAC-SHA256 authenticated peer session tokens.
 */
class TokenService
{
    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret ?? (string) App::getConfig('secret', 'walkie_talkie_secret_salt_2026');
    }

    /**
     * Create a signed security token for a peer in a channel
     *
     * @param string $peerId
     * @param string $nickname
     * @param string $channel
     * @param int $ttlSeconds Time-to-live in seconds (default 86400 = 24h)
     * @return string
     */
    public function generatePeerToken(string $peerId, string $nickname, string $channel, int $ttlSeconds = 86400): string
    {
        $payload = [
            'peer_id' => $peerId,
            'nickname' => $nickname,
            'channel' => $channel,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
        ];

        return SecurityHelper::signToken($payload, $this->secret);
    }

    /**
     * Verify an incoming peer token
     *
     * @param string $token
     * @return array{peer_id: string, nickname: string, channel: string, iat: int, exp: int}|null
     */
    public function verifyPeerToken(string $token): ?array
    {
        $payload = SecurityHelper::verifyToken($token, $this->secret);
        if (!$payload) {
            return null;
        }

        if (empty($payload['peer_id']) || empty($payload['channel']) || empty($payload['nickname'])) {
            return null;
        }

        return $payload;
    }
}
