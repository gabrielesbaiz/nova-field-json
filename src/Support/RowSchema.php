<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Support;

use Closure;
use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Support\Fluent;

/**
 * The sub-field schema behind JsonEditor::repeatable().
 *
 * Every row gets its own freshly built field objects. Sharing one set across
 * rows looks like it works right up until resolve() runs: each row would
 * overwrite the previous row's $field->value, and the front end would render N
 * copies of the last row. An array of fields is therefore cloned per row.
 *
 * @see \Laravel\Nova\Fields\Repeater\Repeatable
 */
final class RowSchema
{
    /** @var (Closure(NovaRequest): iterable<int, Field>)|iterable<int, Field> */
    private $fields;

    /**
     * @param  (Closure(NovaRequest): iterable<int, Field>)|iterable<int, Field>  $fields
     */
    public function __construct(Closure|iterable $fields)
    {
        $this->fields = $fields;
    }

    /**
     * Build one row's fields, resolved against that row's data.
     *
     * @param  array<array-key, mixed>  $data
     * @return FieldCollection<int, Field>
     */
    public function fieldsFor(NovaRequest $request, array $data = []): FieldCollection
    {
        return FieldCollection::make($this->build($request))
            ->each(static function (Field $field) use ($data): void {
                $field->compact();
                $field->resolve($data, $field->attribute);
            });
    }

    /**
     * The blank row the front end clones when the user adds one.
     *
     * @return array<int, mixed>
     */
    public function template(NovaRequest $request): array
    {
        return $this->fieldsFor($request)->jsonSerialize();
    }

    /**
     * One entry per stored row, each carrying fully serialised Nova fields so
     * the front end can render real Nova components.
     *
     * @param  array<array-key, mixed>  $rows
     * @return array<int, array{key: string, fields: array<int, mixed>, values: array<array-key, mixed>}>
     */
    public function resolveRows(NovaRequest $request, array $rows): array
    {
        return collect($rows)
            ->values()
            ->map(fn (mixed $row, int $index): array => [
                'key' => (string) $index,
                'fields' => $this->fieldsFor($request, (array) $row)->jsonSerialize(),
                'values' => (array) $row,
            ])
            ->all();
    }

    /**
     * Fill every submitted row, letting each sub-field fill itself.
     *
     * The request keys are `{attribute}.{index}.fields.{field}` -- the same
     * shape Nova's own repeater posts. It is not cosmetic: the front end's
     * HandlesValidationErrors mixin derives a sub-field's error key as
     * `{parent}.{index}.fields.{attribute}`, so any other shape leaves
     * per-row validation errors rendering nowhere.
     *
     * @return array{0: array<int, array<array-key, mixed>>, 1: Collection<int, callable>}
     */
    public function fillRows(NovaRequest $request, string $requestAttribute): array
    {
        /** @var Collection<int, callable> $deferred */
        $deferred = collect();

        $rows = collect((array) ($request->input($requestAttribute) ?? []))
            ->values()
            ->map(function (mixed $row, int $index) use ($request, $requestAttribute, $deferred): array {
                $sink = new Fluent;

                FieldCollection::make($this->build($request))
                    ->withoutUnfillable()
                    ->each(static function (Field $field) use ($request, $sink, $requestAttribute, $index, $deferred): void {
                        $result = $field->fillInto(
                            $request,
                            $sink,
                            $field->attribute,
                            "{$requestAttribute}.{$index}.fields.{$field->attribute}",
                        );

                        if (is_callable($result)) {
                            $deferred->push($result);
                        }
                    });

                return $sink->getAttributes();
            })
            ->all();

        return [$rows, $deferred];
    }

    /**
     * Validation rules for every submitted row.
     *
     * @return array<string, mixed>
     */
    public function rulesFor(NovaRequest $request, string $key): array
    {
        $rules = [];

        foreach (array_keys((array) $request->input($key, [])) as $index) {
            foreach ($this->build($request) as $field) {
                $rules["{$key}.{$index}.fields.{$field->attribute}"] = $field->rules;
            }
        }

        return $rules;
    }

    /**
     * Human-readable names so messages read "Tiers #1 Price", not
     * "tiers.0.fields.price".
     *
     * @return array<string, string>
     */
    public function attributeNamesFor(NovaRequest $request, string $key, string $name): array
    {
        $names = [];

        foreach (array_keys((array) $request->input($key, [])) as $index) {
            foreach ($this->build($request) as $field) {
                $position = is_numeric($index) ? ((int) $index) + 1 : $index;

                $names["{$key}.{$index}.fields.{$field->attribute}"] = "{$name} #{$position} {$field->name}";
            }
        }

        return $names;
    }

    /**
     * A fresh set of field objects for one row.
     *
     * @return array<int, Field>
     */
    private function build(NovaRequest $request): array
    {
        $fields = $this->fields instanceof Closure
            ? ($this->fields)($request)
            : $this->fields;

        $built = [];

        foreach ($fields as $field) {
            if ($field instanceof MissingValue) {
                continue;
            }

            if (! $field instanceof Field) {
                throw JsonFieldException::notARowField($field);
            }

            // A static array hands back the same objects every time; cloning
            // keeps each row's resolved value to itself.
            $built[] = clone $field;
        }

        return $built;
    }
}
