<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Exceptions;

use RuntimeException;

class JsonFieldException extends RuntimeException
{
    /**
     * A value that is not a Nova field was passed into a Json group.
     */
    public static function notAField(string $column, mixed $value): self
    {
        $given = get_debug_type($value);

        return new self(
            "Json::make('{$column}', [...]) only accepts Laravel\\Nova\\Fields\\Field instances "
            ."or nested Json groups; [{$given}] given. Panels, tabs and merge values cannot be "
            .'stored inside a JSON column.'
        );
    }

    /**
     * A non-field was passed into a repeatable row schema.
     */
    public static function notARowField(mixed $value): self
    {
        $given = get_debug_type($value);

        return new self(
            'JsonEditor::repeatable() only accepts Laravel\\Nova\\Fields\\Field instances; '
            ."[{$given}] given. Panels and merge values cannot be repeated as rows."
        );
    }

    /**
     * A field was passed that can never round-trip through a JSON column.
     */
    public static function unsupportedField(string $column, object $field): self
    {
        $class = $field::class;

        return new self(
            "[{$class}] cannot be used inside Json::make('{$column}', [...]). Relationship and "
            .'unfillable fields hydrate the model directly instead of producing a storable value.'
        );
    }

    /**
     * A method was called on the group that is not forwarded to its children.
     *
     * @param  array<int, string>  $allowlist
     */
    public static function unforwardableMethod(string $column, string $method, array $allowlist): self
    {
        $message = "Json::make('{$column}', [...])->{$method}() is not a forwardable field method.";

        if ($suggestions = self::nearest($method, $allowlist)) {
            $message .= ' Did you mean '.implode(', ', array_map(
                static fn (string $name): string => "->{$name}()",
                $suggestions,
            )).'?';
        }

        return new self(
            $message.' Use ->each(fn (Field $field) => $field->'.$method.'(...)) to reach every child directly.'
        );
    }

    /**
     * A forwarded method exists on the group but on none of its children.
     */
    public static function methodUnsupportedByChildren(string $column, string $method): self
    {
        return new self(
            "None of the fields in Json::make('{$column}', [...]) support ->{$method}(). "
            .'Remove the call, or apply it to the individual fields that do.'
        );
    }

    /**
     * A property was written directly onto the group.
     */
    public static function cannotSetProperty(string $column, string $property): self
    {
        return new self(
            "Cannot set [\${$property}] on Json::make('{$column}', [...]). Writing a property on a "
            .'Json group no longer forwards it to every child field. '
            ."Use ->each(fn (Field \$field) => \$field->{$property} = ...) instead."
        );
    }

    /**
     * The stored column value could not be decoded.
     */
    public static function undecodable(string $column, string $reason): self
    {
        return new self(
            "The value stored in [{$column}] is not valid JSON and cannot be read by the Json field: {$reason}"
        );
    }

    /**
     * The stored column value could not be decrypted.
     */
    public static function undecryptable(string $column): self
    {
        return new self(
            "The value stored in [{$column}] could not be decrypted. This usually means APP_KEY was "
            .'rotated after the value was written, or ->encrypted() was added to a column that '
            .'already holds plain JSON.'
        );
    }

    /**
     * ->encrypted() was combined with an encrypting Eloquent cast.
     */
    public static function doubleEncryption(string $column): self
    {
        return new self(
            "[{$column}] already uses an encrypting cast, so ->encrypted() would encrypt it twice "
            .'and corrupt the stored value. Remove one of the two.'
        );
    }

    /**
     * A ->replaces() group is sharing a column with another group.
     */
    public static function conflictingReplaces(string $column): self
    {
        return new self(
            "A Json group on [{$column}] calls ->replaces(), so it must be the only group writing "
            .'to that column -- it discards everything the others store. Drop ->replaces() to let '
            .'the groups merge, or move the other groups to their own column.'
        );
    }

    /**
     * Rank allowlist entries by edit distance to the attempted method.
     *
     * @param  array<int, string>  $allowlist
     * @return array<int, string>
     */
    protected static function nearest(string $method, array $allowlist, int $limit = 3): array
    {
        $scored = [];

        foreach ($allowlist as $candidate) {
            $distance = levenshtein(strtolower($method), strtolower($candidate));

            if ($distance <= max(3, (int) floor(strlen($method) / 3))) {
                $scored[$candidate] = $distance;
            }
        }

        asort($scored);

        return array_slice(array_keys($scored), 0, $limit);
    }
}
