<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

use Gabrielesbaiz\NovaFieldJson\Json;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Support\Fluent;

/**
 * The fill callback bound to every leaf field inside a Json group.
 *
 * 1.x read each leaf's value straight off the request with a bare
 * $request->exists() check, which skipped every field that overrides
 * fillAttributeFromRequest() -- Boolean stored "1" instead of true, Code::json()
 * stored a string instead of an array, File never uploaded, and a user's own
 * ->fillUsing() was misread as a value retriever rather than a filler.
 *
 * Instead we let the field fill *itself*, into a scratch Fluent, using its own
 * native pipeline, and then lift the single value it produced into the bucket.
 * This is the same technique Nova uses for repeater rows.
 *
 * @see \Laravel\Nova\Fields\Repeater\Presets\JSON::set()
 */
final class LeafFiller
{
    public function __construct(
        private Json $group,
        private readonly Field $field,
        private string $column,
        private string $path,
        private readonly mixed $originalFillCallback = null,
    ) {}

    /**
     * @param  \Illuminate\Database\Eloquent\Model|Fluent  $model
     */
    public function __invoke(
        NovaRequest $request,
        object $model,
        string $attribute,
        ?string $requestAttribute = null
    ): mixed {
        $bucket = BucketRegistry::instance()->for($model, $this->column, $this->group);

        $scratch = new Fluent;
        $leafKey = AttributePath::leafKey($attribute);

        $deferred = $this->withOriginalCallback(
            fn (): mixed => $this->field->fillInto(
                $request,
                $scratch,
                $leafKey,
                $requestAttribute ?? $attribute,
            )
        );

        // Distinguish "the field wrote null" from "the field wrote nothing":
        // only the former should land in the column.
        $attributes = $scratch->getAttributes();

        if (Arr::has($attributes, $leafKey)) {
            $bucket->set($this->path, data_get($attributes, $leafKey));
        }

        $bucket->writeTo($model);

        // File and friends return a callable Nova invokes after the save.
        return $deferred;
    }

    /**
     * Re-point this filler when its group is nested inside another one.
     */
    public function rebind(Json $group, string $column, string $path): void
    {
        $this->group = $group;
        $this->column = $column;
        $this->path = $path;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Run the field's own fill pipeline instead of ours.
     *
     * Required because Field::fillAttribute() short-circuits to $fillCallback
     * when one is set; without the swap this would recurse forever.
     */
    private function withOriginalCallback(callable $work): mixed
    {
        $ours = $this->field->fillCallback;

        $this->field->fillUsing($this->originalFillCallback);

        try {
            return $work();
        } finally {
            $this->field->fillUsing($ours);
        }
    }
}
