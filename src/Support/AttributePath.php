<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

/**
 * Translates between the two path dialects this package straddles.
 *
 * Nova addresses a nested value through a field attribute joined by `->`
 * (`meta->discount->type`), because Field::resolveAttribute() resolves with
 * `data_get($resource, str_replace('->', '.', $attribute))`. Internally we
 * address the same value with a dotted path relative to the column
 * (`discount.type`), because that is what `data_set()`/`data_get()` want.
 *
 * The separator is deliberately not configurable: Nova hard-codes `->` in
 * Field::resolveAttribute(), so any other separator renders every field blank.
 */
final class AttributePath
{
    public const SEPARATOR = '->';

    /**
     * Build the Nova field attribute for a leaf.
     *
     * @param  string  $prefix  Dotted path of the owning group inside the column.
     * @param  string  $key  Dotted path of the leaf inside that group.
     */
    public static function toAttribute(string $column, string $prefix, string $key): string
    {
        return self::fromDotted($column, self::join($prefix, $key));
    }

    /**
     * Build a Nova field attribute from a dotted path relative to the column.
     */
    public static function fromDotted(string $column, string $path): string
    {
        $segments = array_merge(
            [$column],
            $path === '' ? [] : explode('.', $path),
        );

        return implode(self::SEPARATOR, array_filter($segments, static fn (string $s): bool => $s !== ''));
    }

    /**
     * Convert a Nova field attribute into a dotted path relative to the column.
     */
    public static function toDotted(string $attribute, string $column): string
    {
        $prefix = $column.self::SEPARATOR;

        if (str_starts_with($attribute, $prefix)) {
            $attribute = substr($attribute, strlen($prefix));
        }

        return str_replace(self::SEPARATOR, '.', $attribute);
    }

    /**
     * The final segment of a field attribute.
     */
    public static function leafKey(string $attribute): string
    {
        $segments = explode(self::SEPARATOR, $attribute);

        return (string) end($segments);
    }

    /**
     * Join a dotted prefix and a key into a dotted path.
     */
    public static function join(string $prefix, string $key): string
    {
        return implode('.', array_filter([$prefix, $key], static fn (string $s): bool => $s !== ''));
    }
}
