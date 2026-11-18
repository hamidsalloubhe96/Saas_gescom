<?php

namespace App\Support;

/** Icônes SVG de la console (Lucide), lues une seule fois depuis resources/icons.json. */
class Icon
{
    private static ?array $icons = null;

    public static function svg(string $name): string
    {
        self::$icons ??= json_decode((string) @file_get_contents(resource_path('icons.json')), true) ?: [];

        return self::$icons[$name] ?? '';
    }
}
