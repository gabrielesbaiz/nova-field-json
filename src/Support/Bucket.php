<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Json;

/**
 * The accumulated state of one JSON column on one model, for the duration of
 * one fill.
 *
 * 1.x kept this state on the Json field instance, which is the wrong owner: a
 * single field instance fills many models during an action, and many field
 * instances share one column when fields are split across panels. Keying the
 * state to the (model, column) pair fixes both.
 *
 * The bucket is seeded from storage exactly once and is thereafter the sole
 * source of truth. Nothing re-reads the model, which is what stops a partially
 * written column from being read back as if it were the original structure.
 */
final class Bucket
{
    /** @var array<array-key, mixed> */
    private array $data = [];

    private bool $seeded = false;

    private bool $dirty = false;

    /**
     * spl_object_id() of every group that has claimed this bucket.
     *
     * @var array<int, true>
     */
    private array $claimed = [];

    private bool $exclusive = false;

    public function __construct(
        private readonly object $model,
        public readonly string $column,
        private readonly JsonSerializer $serializer,
    ) {}

    /**
     * Register a group against this bucket, once per (model, column, group).
     *
     * Nothing is cleared here. Nova rejects readonly, computed, unauthorised
     * and view-hidden fields in FillsFields *before* any field fills, and a
     * field whose request key is absent writes nothing -- so "this path was
     * not written" cannot be read as "the user removed this value" without
     * deleting data the user never touched. Absent means unchanged, exactly as
     * it does for an ordinary Nova field; ->replaces() is the opt-in for a
     * group that genuinely owns its whole column.
     */
    public function claim(Json $group): self
    {
        $id = spl_object_id($group);

        if (isset($this->claimed[$id])) {
            return $this;
        }

        $this->claimed[$id] = true;

        $this->seed();

        if ($group->replacesColumn()) {
            if (count($this->claimed) > 1) {
                throw JsonFieldException::conflictingReplaces($this->column);
            }

            $this->exclusive = true;
            $this->data = [];
        } elseif ($this->exclusive) {
            throw JsonFieldException::conflictingReplaces($this->column);
        }

        if (($defaults = $group->defaultStructure()) !== []) {
            $this->data = array_replace_recursive($defaults, $this->data);
        }

        $this->dirty = true;

        return $this;
    }

    /**
     * Record one leaf value at a dotted path relative to the column.
     */
    public function set(string $path, mixed $value): void
    {
        data_set($this->data, $path, $value);

        $this->dirty = true;
    }

    /**
     * The structure as it will be stored.
     *
     * @return array<array-key, mixed>
     */
    public function resolve(): array
    {
        return $this->serializer->pruneNulls()
            ? self::withoutNulls($this->data)
            : $this->data;
    }

    /**
     * Push the accumulated structure onto the model.
     *
     * Called after every leaf rather than once at the end: Nova runs the
     * collected post-fill callbacks *after* $model->save(), and the action
     * event snapshots dirty attributes *before* it, so deferring the write
     * would drop the column out of the change log. The dirty flag keeps the
     * repeated writes cheap, and for a cast column the value handed over is a
     * plain array, so Eloquent still encodes exactly once at save.
     */
    public function writeTo(object $model): void
    {
        if (! $this->dirty) {
            return;
        }

        $this->serializer->assertNotDoublyEncrypted($model, $this->column);

        $model->{$this->column} = $this->serializer->serialize($this->resolve(), $model, $this->column);

        $this->dirty = false;
    }

    private function seed(): void
    {
        if ($this->seeded) {
            return;
        }

        $this->data = $this->serializer->unserialize(
            $this->model->{$this->column} ?? null,
            $this->column,
        );

        $this->seeded = true;
    }

    /**
     * Drop null leaves, then any parent left empty by that removal.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function withoutNulls(array $data): array
    {
        $isList = array_is_list($data);
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = self::withoutNulls($value);

                if ($value === []) {
                    continue;
                }
            } elseif ($value === null) {
                continue;
            }

            $result[$key] = $value;
        }

        return $isList ? array_values($result) : $result;
    }
}
