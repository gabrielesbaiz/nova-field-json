<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

use Gabrielesbaiz\NovaFieldJson\Enums\StorageFormat;
use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;
use Traversable;

/**
 * Decides how a JSON structure crosses the boundary between PHP and storage.
 *
 * Immutable so that a Bucket can hold one for the lifetime of a fill without
 * worrying about the owning field being reconfigured mid-request.
 */
final class JsonSerializer
{
    public const DEFAULT_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Eloquent cast types that already store and retrieve a PHP array.
     *
     * @var array<int, string>
     */
    private const NATIVE_CAST_TYPES = [
        'array', 'json', 'object', 'collection',
        'encrypted:array', 'encrypted:json', 'encrypted:object', 'encrypted:collection',
    ];

    public function __construct(
        private readonly int $flags = self::DEFAULT_FLAGS,
        private readonly bool $encrypted = false,
        private readonly ?StorageFormat $format = null,
        private readonly bool $pruneNulls = false,
    ) {}

    public function pruneNulls(): bool
    {
        return $this->pruneNulls;
    }

    public function flags(): int
    {
        return $this->flags;
    }

    /**
     * Determine the representation this model/column pair expects.
     */
    public function formatFor(object $model, string $column): StorageFormat
    {
        // An explicit ->storeAs() always wins.
        if ($this->format !== null) {
            return $this->format;
        }

        if ($this->encrypted) {
            return StorageFormat::Encrypted;
        }

        // Nova hands action fields a Fluent bag, which has no casts. Calling
        // hasCast() on it would not fail loudly -- Fluent::__call() writes an
        // attribute named "hasCast" and returns $this -- so guard explicitly.
        if (! $model instanceof Model) {
            return StorageFormat::Native;
        }

        return $this->modelCastsJson($model, $column)
            ? StorageFormat::Native
            : StorageFormat::Encoded;
    }

    /**
     * Prepare a resolved structure for assignment to the column.
     *
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>|string
     */
    public function serialize(array $value, object $model, string $column): array|string
    {
        $format = $this->formatFor($model, $column);

        if ($format === StorageFormat::Native) {
            return $value;
        }

        $encoded = $this->encode($value, $column);

        return $format === StorageFormat::Encrypted
            ? Crypt::encryptString($encoded)
            : $encoded;
    }

    /**
     * Normalise whatever the column currently holds into a PHP array.
     *
     * @return array<array-key, mixed>
     */
    public function unserialize(mixed $stored, string $column = ''): array
    {
        if ($stored === null || $stored === '' || $stored === []) {
            return [];
        }

        if ($this->encrypted && is_string($stored)) {
            $stored = $this->decrypt($stored, $column);
        }

        if (is_string($stored)) {
            return $this->decode($stored, $column);
        }

        // AsCollection, AsEnumCollection, and anything else Arrayable.
        if ($stored instanceof Arrayable) {
            return $stored->toArray();
        }

        // AsArrayObject and friends. Note that collect($arrayObject)->toArray()
        // flattens by iteration and loses nesting, which is why this is explicit.
        if ($stored instanceof Traversable) {
            return iterator_to_array($stored);
        }

        if (is_array($stored)) {
            return $stored;
        }

        if (is_object($stored)) {
            return $this->decode($this->encode((array) $stored, $column), $column);
        }

        return [];
    }

    /**
     * Guard against layering our own encryption on top of an encrypting cast.
     */
    public function assertNotDoublyEncrypted(object $model, string $column): void
    {
        if (! $this->encrypted || ! $model instanceof Model) {
            return;
        }

        $cast = $model->getCasts()[$column] ?? null;

        $encrypting = is_string($cast) && (
            str_starts_with($cast, 'encrypted')
            || str_contains(Str::before($cast, ':'), 'Encrypted')
        );

        if ($encrypting) {
            throw JsonFieldException::doubleEncryption($column);
        }
    }

    /**
     * Does the model already translate this column to and from a PHP array?
     */
    private function modelCastsJson(Model $model, string $column): bool
    {
        if ($model->hasCast($column, self::NATIVE_CAST_TYPES)) {
            return true;
        }

        $cast = $model->getCasts()[$column] ?? null;

        if (! is_string($cast)) {
            return false;
        }

        // Parameterised casts arrive as "Class:arg", e.g. AsCollection::of(Foo::class).
        $class = Str::before($cast, ':');

        return class_exists($class)
            && (is_a($class, Castable::class, true) || is_a($class, CastsAttributes::class, true));
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function encode(array $value, string $column): string
    {
        try {
            return json_encode($value, $this->flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw JsonFieldException::undecodable($column, $e->getMessage());
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(string $stored, string $column): array
    {
        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw JsonFieldException::undecodable($column, $e->getMessage());
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function decrypt(string $stored, string $column): string
    {
        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            throw JsonFieldException::undecryptable($column);
        }
    }
}
