<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Concerns;

use Gabrielesbaiz\NovaFieldJson\Enums\StorageFormat;
use Gabrielesbaiz\NovaFieldJson\Support\JsonSerializer;

/**
 * The fluent storage surface shared by Json and JsonEditor, so both behave
 * identically when pointed at the same column.
 */
trait HasJsonStorageOptions
{
    /** Flags handed to json_encode() when the column is not cast. */
    protected int $jsonFlags = JsonSerializer::DEFAULT_FLAGS;

    /** Encrypt the encoded structure before storing it. */
    protected bool $encryptValues = false;

    /** Force a representation instead of inferring one from the model's casts. */
    protected ?StorageFormat $storageFormat = null;

    /** Drop null leaves (and the empty parents they leave behind) before storing. */
    protected bool $prunesNulls = false;

    /**
     * Values applied underneath the stored data, keyed by dotted path.
     *
     * @var array<string, mixed>
     */
    protected array $defaultValues = [];

    /**
     * Override the flags used when encoding to a non-cast column.
     *
     * JSON_THROW_ON_ERROR is always added; silent null-on-failure is never
     * what you want for a column you are about to write.
     */
    public function jsonFlags(int $flags): static
    {
        $this->jsonFlags = $flags | JSON_THROW_ON_ERROR;

        return $this;
    }

    /**
     * Encrypt the stored JSON at rest.
     *
     * Throws at fill time if the column already uses an encrypting cast, since
     * encrypting twice silently corrupts the value.
     */
    public function encrypted(bool $encrypted = true): static
    {
        $this->encryptValues = $encrypted;

        return $this;
    }

    /**
     * Force how the structure is handed to the column.
     *
     * Replaces 1.x's ->ignoreCasting(); StorageFormat::Encoded is the direct
     * equivalent.
     */
    public function storeAs(StorageFormat $format): static
    {
        $this->storageFormat = $format;

        return $this;
    }

    /**
     * Remove null leaves, then any parents left empty, before storing.
     */
    public function pruneNulls(bool $prune = true): static
    {
        $this->prunesNulls = $prune;

        return $this;
    }

    /**
     * Values merged beneath the stored data. Anything already stored wins.
     *
     * @param  array<string, mixed>  $defaults  Keyed by dotted path, e.g. ['discount.type' => 'percent'].
     */
    public function defaults(array $defaults): static
    {
        $this->defaultValues = array_merge($this->defaultValues, $defaults);

        return $this;
    }

    /**
     * The defaults expanded from dotted paths into a nested structure.
     *
     * @return array<array-key, mixed>
     */
    public function defaultStructure(): array
    {
        $structure = [];

        foreach ($this->defaultValues as $path => $value) {
            data_set($structure, $path, $value);
        }

        return $structure;
    }
}
