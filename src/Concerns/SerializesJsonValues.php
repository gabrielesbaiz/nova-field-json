<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Concerns;

use Gabrielesbaiz\NovaFieldJson\Support\JsonSerializer;

/**
 * Bridges the fluent option surface to the serializer that acts on it.
 *
 * @see HasJsonStorageOptions
 */
trait SerializesJsonValues
{
    use HasJsonStorageOptions;

    /**
     * Build a serializer reflecting the current options.
     *
     * Deliberately not memoised: options are set at field-definition time and
     * read at fill time, and a stale serializer would silently ignore them.
     */
    public function serializer(): JsonSerializer
    {
        return new JsonSerializer(
            flags: $this->jsonFlags,
            encrypted: $this->encryptValues,
            format: $this->storageFormat,
            pruneNulls: $this->prunesNulls,
        );
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>|string
     */
    protected function serializeJson(array $value, object $model, string $column): array|string
    {
        $serializer = $this->serializer();

        $serializer->assertNotDoublyEncrypted($model, $column);

        return $serializer->serialize($value, $model, $column);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function unserializeJson(mixed $stored, string $column = ''): array
    {
        return $this->serializer()->unserialize($stored, $column);
    }
}
