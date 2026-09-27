<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

use Gabrielesbaiz\NovaFieldJson\Json;
use WeakMap;

/**
 * Hands out the Bucket for a given (model, column) pair.
 *
 * Storage is a WeakMap keyed on the model object itself, which buys three
 * things a plain array cannot:
 *
 *  - Entries vanish when the model does, so nothing leaks between requests and
 *    no terminating()/Octane-flush hook is needed.
 *  - Each model in an action's collection gets its own bucket, which is the
 *    fix for 1.x's $cleanedOut flag never resetting past the first model.
 *  - Identity keying cannot alias, unlike spl_object_id(), whose ids are
 *    recycled after collection.
 *
 * Laravel\Nova\Support\Fluent is an object too, so action fields work with no
 * special casing.
 */
final class BucketRegistry
{
    private static ?self $instance = null;

    /** @var WeakMap<object, array<string, Bucket>> */
    private WeakMap $buckets;

    private function __construct()
    {
        $this->buckets = new WeakMap;
    }

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Drop all accumulated state.
     *
     * @internal Test hook. Production code relies on the WeakMap emptying itself.
     */
    public static function flush(): void
    {
        self::$instance = null;
    }

    public function for(object $model, string $column, Json $group): Bucket
    {
        /** @var array<string, Bucket> $columns */
        $columns = $this->buckets[$model] ?? [];

        $columns[$column] ??= new Bucket($model, $column, $group->serializer());

        $this->buckets[$model] = $columns;

        return $columns[$column]->claim($group);
    }
}
