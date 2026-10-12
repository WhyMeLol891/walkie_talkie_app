<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Class SecurityHelper
 * Input sanitization, XSS escaping, token signing and validation.
 */
class SecurityHelper
{
    /**
     * Escape HTML output (XSS prevention)
     */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize and validate nickname (1-24 characters)
     */
    public static function sanitizeNickname(string $nickname): string
    {
        // Remove script and style tags and their contents
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $nickname) ?? $nickname;
        $clean = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $clean) ?? $clean;
        $clean = trim(strip_tags($clean));
        // Remove control characters
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean) ?? '';
        // Collapse multiple spaces
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
        
        if (mb_strlen($clean) > 24) {
            $clean = mb_substr($clean, 0, 24);
        }
        return $clean;
    }

    /**
     * Sanitize channel name (1-64 characters, alphanumeric, hyphens, underscores)
     */
    public static function sanitizeChannel(string $channel): string
    {
        $clean = trim($channel);
        $clean = strtolower($clean);
        // Replace spaces and special symbols with hyphen
        $clean = preg_replace('/[^a-z0-9_-]+/', '-', $clean) ?? '';
        // Remove leading/trailing hyphens/underscores
        $clean = trim($clean, '-_');
        
        if (strlen($clean) > 64) {
            $clean = substr($clean, 0, 64);
        }
        return $clean;
    }

    /**
     * Generate unique random guest peer ID
     */
    public static function generatePeerId(): string
    {
        return 'peer_' . bin2hex(random_bytes(6));
    }

    /**
     * Sign a payload into an HMAC-SHA256 signed token
     */
    public static function signToken(array $payload, string $secret): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = self::base64UrlEncode($json ?: '{}');
        $signature = hash_hmac('sha256', $payloadB64, $secret);
        return $payloadB64 . '.' . $signature;
    }

    /**
     * Verify HMAC-SHA256 signed token and verify expiration
     *
     * @return array<string, mixed>|null Returns payload array if valid, null otherwise
     */
    public static function verifyToken(string $token, string $secret): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadB64, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $json = self::base64UrlDecode($payloadB64);
        if (!$json) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration if present
        if (isset($payload['exp']) && is_numeric($payload['exp'])) {
            if (time() > (int) $payload['exp']) {
                return null; // Expired
            }
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
