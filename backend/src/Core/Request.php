<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $jsonCache = null;

    public static function json(): array
    {
        if (self::$jsonCache !== null) {
            return self::$jsonCache;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return self::$jsonCache = [];
        }

        $data = json_decode($raw, true);
        return self::$jsonCache = is_array($data) ? $data : [];
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    /**
     * Normaliza $_FILES para un input tipo "fotos[]" en una lista de archivos individuales.
     */
    public static function files(string $key): array
    {
        if (!isset($_FILES[$key])) {
            return [];
        }

        $f = $_FILES[$key];
        if (!is_array($f['name'])) {
            return [$f];
        }

        $out = [];
        foreach ($f['name'] as $i => $name) {
            $out[] = [
                'name' => $name,
                'type' => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
            ];
        }

        return $out;
    }
}
