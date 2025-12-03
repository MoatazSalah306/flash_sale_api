<?php

namespace App\Enums;

trait EnumHelpers
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
    public static function implodedValues(string $glue = ','): string
    {
        return implode($glue, self::values());
    }

    public static function except(array $excluded): array
    {
        return array_values(array_diff(self::values(), $excluded));
    }

    public static function keys(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function toArray(): array
    {
        return array_combine(self::keys(), self::values());
    }
}
