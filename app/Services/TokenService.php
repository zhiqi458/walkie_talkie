<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Helpers;

final class TokenService
{
    public function issue(array $claims): string
    {
        $payload = $claims;
        $payload['iat'] = time();
        $payload['exp'] = $payload['exp'] ?? (time() + 3600);

        $encoded = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        $signature = hash_hmac('sha256', $encoded, (string) Helpers::config('signal_secret', 'change-me'));

        return $encoded . '.' . $signature;
    }

    public function validate(string $token): array|false
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, (string) Helpers::config('signal_secret', 'change-me'));
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $json = $this->base64UrlDecode($encoded);
        $claims = json_decode($json, true);
        if (!is_array($claims)) {
            return false;
        }

        if (($claims['exp'] ?? 0) < time()) {
            return false;
        }

        return $claims;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
