<?php
declare(strict_types=1);

namespace App\Core;

final class Helpers
{
    public static function config(string $key, mixed $default = null): mixed
    {
        $config = $GLOBALS['app_config'] ?? [];
        return $config[$key] ?? $default;
    }

    public static function basePath(string $path = ''): string
    {
        $base = rtrim((string) self::config('base_path', ''), '/');
        $path = '/' . ltrim($path, '/');
        return ($base === '' ? '' : $base) . $path;
    }

    public static function url(string $path = ''): string
    {
        return self::basePath($path);
    }

    public static function absoluteUrl(string $path = ''): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . self::url($path);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function csrfToken(): string
    {
        return Csrf::token();
    }

    public static function asset(string $path): string
    {
        return self::url($path);
    }

    public static function randomId(int $bytes = 8): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $value, int $maxLength = 64): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[^\pL\pN]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        if ($value === '') {
            $value = 'channel';
        }
        return mb_substr($value, 0, $maxLength);
    }
}
