<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Enums;

/**
 * How a resolved JSON structure is handed to the underlying column.
 */
enum StorageFormat: string
{
    /**
     * Assign the raw PHP array. Correct when the model casts the column
     * (`array`, `json`, `object`, `collection`, `AsArrayObject`, ...) and for
     * the in-memory `Fluent` bag Nova uses for action fields.
     */
    case Native = 'native';

    /** `json_encode()` the structure before assigning it. */
    case Encoded = 'encoded';

    /** `json_encode()` then `Crypt::encryptString()` before assigning it. */
    case Encrypted = 'encrypted';
}
