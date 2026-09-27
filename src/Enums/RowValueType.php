<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Enums;

/**
 * The JSON type of a single key/value row, used by the key-value editor so a
 * value round-trips as the type the user chose rather than as a string.
 */
enum RowValueType: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Null = 'null';
    case Arr = 'array';
    case Obj = 'object';

    /**
     * Determine the type of an already-decoded JSON value.
     */
    public static function fromValue(mixed $value): self
    {
        return match (true) {
            $value === null => self::Null,
            is_bool($value) => self::Boolean,
            is_int($value), is_float($value) => self::Number,
            is_array($value) => array_is_list($value) ? self::Arr : self::Obj,
            default => self::String,
        };
    }

    /**
     * The option list handed to the front-end type picker.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    /**
     * Coerce a raw front-end value into this type.
     */
    public function coerce(mixed $value): mixed
    {
        return match ($this) {
            self::Null => null,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            self::Number => is_numeric($value) ? $value + 0 : 0,
            self::Arr => array_values((array) $value),
            self::Obj => (array) $value,
            self::String => is_scalar($value) ? (string) $value : '',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::String => 'String',
            self::Number => 'Number',
            self::Boolean => 'Boolean',
            self::Null => 'Null',
            self::Arr => 'Array',
            self::Obj => 'Object',
        };
    }
}
