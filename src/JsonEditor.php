<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson;

use Closure;
use Gabrielesbaiz\NovaFieldJson\Concerns\SerializesJsonValues;
use Gabrielesbaiz\NovaFieldJson\Enums\EditorMode;
use Gabrielesbaiz\NovaFieldJson\Enums\RowValueType;
use Gabrielesbaiz\NovaFieldJson\Support\RowSchema;
use Illuminate\Validation\ValidationException;
use JsonException;
use Laravel\Nova\Fields\Collapsable;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\SupportsDependentFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

/**
 * Edits a JSON column directly, with a tree, key/value, raw or repeatable UI.
 *
 * Where Json composes ordinary Nova fields into a column, JsonEditor owns the
 * column and renders its own editor -- the right tool when the shape is not
 * known up front, or when the user needs to add and remove keys.
 *
 * ```php
 * JsonEditor::make('metadata')->tree(),
 *
 * JsonEditor::make('tiers')->repeatable(fn () => [
 *     Text::make('Label', 'label')->rules('required'),
 *     Number::make('Price', 'price')->rules('numeric'),
 * ])->min(1)->max(10)->sortable(),
 * ```
 */
class JsonEditor extends Field
{
    use Collapsable;
    use SerializesJsonValues;
    use SupportsDependentFields;

    public $component = 'json-editor-field';

    /**
     * The editor is wide and interactive; opt back in with ->showOnIndex().
     *
     * @var bool
     */
    public $showOnIndex = false;

    protected EditorMode $mode = EditorMode::Tree;

    protected ?RowSchema $rows = null;

    protected ?int $minRows = null;

    protected ?int $maxRows = null;

    /**
     * Rows are drag-reorderable.
     *
     * Deliberately shadows Field::sortable(), which means "sortable column on
     * the index". Nova's own Repeater makes the same trade, and this field is
     * hidden from the index by default, so the redefinition is unambiguous.
     * Use ->sortableColumn() for index sorting.
     */
    protected bool $sortableRows = false;

    protected int $height = 320;

    protected bool $allowTypeChange = true;

    /** @var array<int, string> */
    protected array $lockedKeys = [];

    /** How deep the tree and viewer start out expanded. */
    protected int $expandDepth = 1;

    public function raw(): static
    {
        return $this->mode(EditorMode::Raw);
    }

    public function keyValue(): static
    {
        return $this->mode(EditorMode::KeyValue);
    }

    public function tree(): static
    {
        return $this->mode(EditorMode::Tree);
    }

    /**
     * Render the column as repeated rows of the given Nova fields.
     *
     * Prefer the closure form: it guarantees a fresh set of field objects per
     * row. An array works too and is cloned per row.
     *
     * @param  (Closure(NovaRequest): iterable<int, Field>)|iterable<int, Field>  $fields
     */
    public function repeatable(Closure|iterable $fields): static
    {
        $this->rows = new RowSchema($fields);

        return $this->mode(EditorMode::Repeatable);
    }

    public function mode(EditorMode $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function min(int $min): static
    {
        $this->minRows = $min;

        return $this;
    }

    public function max(int $max): static
    {
        $this->maxRows = $max;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param  bool  $value
     */
    public function sortable($value = true): static
    {
        $this->sortableRows = (bool) $value;

        return $this;
    }

    /**
     * Make the underlying column sortable on the index, the way
     * Field::sortable() would.
     */
    public function sortableColumn(bool $value = true): static
    {
        $this->sortable = $value;

        return $this;
    }

    /** Editor height in pixels. */
    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    /** Forbid changing a value's JSON type in the key/value and tree editors. */
    public function allowTypeChange(bool $allow = true): static
    {
        $this->allowTypeChange = $allow;

        return $this;
    }

    /**
     * Keys the user may edit the value of but not rename or remove.
     *
     * @param  array<int, string>  $keys  Dotted paths.
     */
    public function lockedKeys(array $keys): static
    {
        $this->lockedKeys = $keys;

        return $this;
    }

    public function expandDepth(int $depth): static
    {
        $this->expandDepth = $depth;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int|string, mixed>
     */
    public function getCreationRules(NovaRequest $request): array
    {
        return array_merge_recursive(parent::getCreationRules($request), $this->rowRules($request));
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int|string, mixed>
     */
    public function getUpdateRules(NovaRequest $request): array
    {
        return array_merge_recursive(parent::getUpdateRules($request), $this->rowRules($request));
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    public function getValidationAttributeNames(NovaRequest $request): array
    {
        $names = parent::getValidationAttributeNames($request);

        if (! $this->isRepeatable() || $request->isMethod('GET')) {
            return $names;
        }

        return array_merge(
            $names,
            $this->rows->attributeNamesFor($request, $this->validationKey(), $this->name),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $request = app(NovaRequest::class);

        return array_merge(parent::jsonSerialize(), array_filter([
            'mode' => $this->mode->value,
            'height' => $this->height,
            'expandDepth' => $this->expandDepth,
            'collapsable' => $this->collapsable,
            'collapsedByDefault' => $this->collapsedByDefault,
            'min' => $this->minRows,
            'max' => $this->maxRows,
            'sortableRows' => $this->sortableRows,
            'allowTypeChange' => $this->allowTypeChange,
            'lockedKeys' => $this->lockedKeys,
            'valueTypes' => RowValueType::options(),
            'rowTemplate' => $this->rows?->template($request),
            'translations' => [
                'addRow' => Nova::__('Add row'),
                'removeRow' => Nova::__('Remove row'),
                'invalidJson' => Nova::__('Invalid JSON'),
                'emptyValue' => Nova::__('No value'),
            ],
        ], static fn (mixed $value): bool => $value !== null));
    }

    public function isRepeatable(): bool
    {
        return $this->mode === EditorMode::Repeatable && $this->rows !== null;
    }

    /**
     * Shape the stored structure for the active editor.
     *
     * @param  \Laravel\Nova\Resource|\Illuminate\Database\Eloquent\Model|object|array<array-key, mixed>  $resource
     */
    protected function resolveAttribute($resource, string $attribute): mixed
    {
        $data = $this->unserializeJson(parent::resolveAttribute($resource, $attribute), $attribute);

        return match ($this->mode) {
            EditorMode::Raw => json_encode($data, $this->jsonFlags | JSON_PRETTY_PRINT),
            EditorMode::KeyValue => self::toKeyValueRows($data),
            EditorMode::Repeatable => $this->rows?->resolveRows(app(NovaRequest::class), $data) ?? [],
            EditorMode::Tree => $data,
        };
    }

    /**
     * {@inheritDoc}
     */
    protected function fillAttributeFromRequest(
        NovaRequest $request,
        string $requestAttribute,
        object $model,
        string $attribute
    ): mixed {
        if (! $request->exists($requestAttribute)) {
            return null;
        }

        if ($this->isRepeatable()) {
            [$rows, $deferred] = $this->rows->fillRows($request, $requestAttribute);

            $this->store($model, $attribute, $rows);

            return static fn () => $deferred->each(static fn (callable $callback) => $callback());
        }

        $value = match ($this->mode) {
            EditorMode::KeyValue => self::fromKeyValueRows($this->decodeInput($request->input($requestAttribute))),
            default => $this->decodeInput($request->input($requestAttribute)),
        };

        $this->store($model, $attribute, $value);

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    protected function store(object $model, string $attribute, array $value): void
    {
        if ($this->prunesNulls) {
            $value = self::withoutNulls($value);
        }

        $this->fillModelWithData($model, $this->serializeJson($value, $model, $attribute), $attribute);
    }

    /**
     * Every non-repeatable mode posts one JSON string, exactly as Nova's own
     * KeyValue field does.
     *
     * @return array<array-key, mixed>
     */
    protected function decodeInput(mixed $input): array
    {
        if (is_array($input)) {
            return $input;
        }

        if ($input === null || $input === '') {
            return [];
        }

        try {
            $decoded = json_decode((string) $input, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            // A malformed payload is user error, not a server error -- the
            // front end guards it, but the server must not trust that.
            throw ValidationException::withMessages([
                $this->validationKey() => [
                    Nova::__('The :field field must contain valid JSON.', ['field' => $this->name]),
                ],
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowRules(NovaRequest $request): array
    {
        if (! $this->isRepeatable() || $request->isMethod('GET')) {
            return [];
        }

        $key = $this->validationKey();

        $rules = [$key => array_values(array_filter([
            'array',
            $this->minRows !== null ? "min:{$this->minRows}" : null,
            $this->maxRows !== null ? "max:{$this->maxRows}" : null,
        ]))];

        return array_merge($rules, $this->rows->rulesFor($request, $key));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<int, array{key: string, value: mixed, type: string}>
     */
    protected static function toKeyValueRows(array $data): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $rows[] = [
                'key' => (string) $key,
                'value' => $value,
                'type' => RowValueType::fromValue($value)->value,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @return array<string, mixed>
     */
    protected static function fromKeyValueRows(array $rows): array
    {
        $data = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ($row['key'] ?? '') === '') {
                continue;
            }

            $type = RowValueType::tryFrom((string) ($row['type'] ?? 'string')) ?? RowValueType::String;

            $data[(string) $row['key']] = $type->coerce($row['value'] ?? null);
        }

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    protected static function withoutNulls(array $data): array
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
