<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function requireFields(array $data, array $fields): void
    {
        $faltantes = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                $faltantes[] = $field;
            }
        }
        if ($faltantes !== []) {
            Response::error('Faltan campos obligatorios: ' . implode(', ', $faltantes), 422);
        }
    }

    public static function positiveNumber(mixed $value, string $label): float
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            Response::error("{$label} debe ser un número mayor a cero.", 422);
        }
        return (float) $value;
    }

    public static function nonNegativeNumber(mixed $value, string $label): float
    {
        if (!is_numeric($value) || (float) $value < 0) {
            Response::error("{$label} debe ser un número mayor o igual a cero.", 422);
        }
        return (float) $value;
    }
}
