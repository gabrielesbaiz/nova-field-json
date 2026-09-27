<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson;

use Closure;
use Gabrielesbaiz\NovaFieldJson\Concerns\ForwardsFieldCalls;
use Gabrielesbaiz\NovaFieldJson\Concerns\SerializesJsonValues;
use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Support\AttributePath;
use Gabrielesbaiz\NovaFieldJson\Support\LeafFiller;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Laravel\Nova\Contracts\RelatableField;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\FieldMergeValue;
use Laravel\Nova\Fields\Unfillable;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Makeable;
use Laravel\Nova\Panel;

/**
 * Stores a group of ordinary Nova fields inside a single JSON column.
 *
 * This is a field *composer*, not a field: it rewrites each child's attribute
 * to `column->key` so Nova resolves it through the column, binds a filler that
 * routes the child's value into a shared accumulator, and then flattens itself
 * into the parent field list. Rendering is done entirely by Nova's own
 * components for the wrapped fields, which is why the group ships no assets.
 *
 * ```php
 * Json::make('meta', [
 *     Select::make(__('Discount Type'), 'type')->options([...]),
 *     Number::make(__('Discount Value'), 'value'),
 *
 *     Json::make('shipping', [
 *         Boolean::make(__('Free'), 'free'),
 *     ]),
 * ])
 * ```
 *
 * @see JsonEditor for the editor field, which edits a column's JSON directly.
 *
 * Every method below is forwarded to each child field by ForwardsFieldCalls
 * and returns the group, so IDEs and static analysis see them as real methods.
 *
 * @method self hideFromIndex()
 * @method self hideFromDetail()
 * @method self hideWhenCreating()
 * @method self hideWhenUpdating()
 * @method self showOnIndex(callable|bool $callback = true)
 * @method self showOnDetail(callable|bool $callback = true)
 * @method self showOnCreating(callable|bool $callback = true)
 * @method self showOnUpdating(callable|bool $callback = true)
 * @method self onlyOnIndex()
 * @method self onlyOnDetail()
 * @method self onlyOnForms()
 * @method self exceptOnForms()
 * @method self stacked(bool $stacked = true)
 * @method self compact(bool $compact = true)
 * @method self show(callable|bool $callback = true)
 * @method self hide(callable|bool $callback = true)
 * @method self textAlign(string $alignment)
 * @method self required(callable|bool $param = true)
 * @method self placeholder(string $text)
 * @method self displayUsing(callable $displayCallback)
 * @method self resolveUsing(callable $resolveCallback)
 * @method self sortable(bool $value = true)
 * @method self nullable(bool $nullable = true, array<int|string, mixed>|Closure|null $values = null)
 * @method self nullValues(array<int|string, mixed>|Closure $nullValues)
 * @method self helpWidth(string $helpWidth)
 * @method self readonly(callable|bool $callback = true)
 * @method self immutable(callable|bool $callback = true)
 * @method self mutable(bool $mutable = true)
 * @method self default(mixed $callback)
 * @method self computed()
 * @method self rules(mixed ...$rules)
 * @method self creationRules(mixed ...$rules)
 * @method self updateRules(mixed ...$rules)
 * @method self help(string $text)
 * @method self withMeta(array<string, mixed> $meta)
 * @method self canSee(Closure $callback)
 * @method self canSeeWhen(string $ability, mixed $arguments = null)
 * @method self showWhenPeeking()
 * @method self peekable(callable|bool $callback = true)
 * @method self copyable()
 * @method self filterable(callable|null $filterBy = null)
 * @method self fullWidth()
 * @method self dependsOn(array<int, string>|string $attributes, callable $mixin)
 * @method self dependsOnCreating(array<int, string>|string $attributes, callable $mixin)
 * @method self dependsOnUpdating(array<int, string>|string $attributes, callable $mixin)
 */
class Json extends FieldMergeValue
{
    use ForwardsFieldCalls, Macroable {
        ForwardsFieldCalls::__call insteadof Macroable;
        Macroable::__call as protected macroCall;
    }

    // NOTE: no Conditionable -- FieldMergeValue already exposes when()/unless()
    // through Illuminate's ConditionallyLoadsAttributes, with resource-filtering
    // semantics rather than fluent-builder ones.
    use Makeable;
    use SerializesJsonValues;
    use Tappable;

    /** The database column this group writes to. */
    public readonly string $column;

    /** Nova assigns this when the group is flattened into a panel. */
    public ?Panel $panel = null;

    /** Dotted path of this group inside the column; '' for the outermost group. */
    protected string $prefix = '';

    /**
     * Dotted paths, relative to the column, owned by this group's leaves.
     *
     * @var array<int, string>
     */
    protected array $ownedPaths = [];

    /** Discard everything else in the column instead of merging into it. */
    protected bool $replaces = false;

    /**
     * @param  (callable():(iterable<int, object>))|iterable<int, object>  $fields
     */
    public function __construct(string $column, callable|iterable $fields = [])
    {
        $this->column = $column;

        parent::__construct($this->prepareFields($fields));
    }

    /**
     * Every leaf field in the group, nested groups flattened in place.
     *
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return collect($this->data)
            ->flatMap(static fn (object $field): array => $field instanceof self ? $field->fields() : [$field])
            ->all();
    }

    /**
     * @return array<int, Field>
     */
    public function toArray(): array
    {
        return $this->fields();
    }

    /**
     * The dotted paths inside the column that this group's fields map to.
     *
     * Introspection only -- nothing is cleared on the strength of it. Useful
     * when a field is not landing where you expected and you want to see the
     * mapping the group actually built.
     *
     * @return array<int, string>
     */
    public function ownedPaths(): array
    {
        return $this->ownedPaths;
    }

    public function replacesColumn(): bool
    {
        return $this->replaces;
    }

    /**
     * Treat this group as the sole owner of the column.
     *
     * Everything not written by this group -- sibling groups, keys written
     * outside Nova -- is discarded on save. It is an error for a column to
     * have a replacing group and any other group.
     */
    public function replaces(bool $replaces = true): static
    {
        $this->replaces = $replaces;

        return $this;
    }

    /**
     * Nova calls resolve() on entries it finds in a field list; the group
     * itself has no value of its own, so there is nothing to resolve.
     */
    public function resolve(mixed $resource, ?string $attribute = null): void
    {
        //
    }

    /**
     * Resolve every leaf for use in an action.
     *
     * 1.x left resolve() empty and never implemented this, which silently
     * dropped ->default() on any field used inside an action.
     */
    public function resolveForAction(NovaRequest $request): static
    {
        foreach ($this->fields() as $field) {
            $field->resolveForAction($request);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * Deliberately does not delegate to FieldMergeValue::prepareFields(): that
     * runs Illuminate's filter(), which flattens any nested MergeValue into its
     * children. A nested Json group would be dissolved into already-bound
     * leaves before we ever saw it, and we would then re-bind them as if they
     * were direct children -- losing their path inside the group. So expand
     * conditional entries around the groups instead of through them.
     *
     * @param  (callable():(iterable<int, object>))|iterable<int, object>  $fields
     * @return array<int, Field>
     */
    protected function prepareFields(callable|iterable $fields): iterable
    {
        if (! is_iterable($fields)) {
            $fields = $fields();
        }

        $prepared = [];

        foreach ($fields instanceof Collection ? $fields->all() : $fields as $field) {
            if ($field instanceof MissingValue) {
                continue;
            }

            if ($field instanceof self) {
                array_push($prepared, ...$field->rebase($this, $this->column, $this->prefix)->fields());

                continue;
            }

            // Expand mergeWhen()/when() entries, which filter() resolves for us.
            foreach ($this->filter([$field]) as $expanded) {
                $prepared[] = $this->bindLeaf($this->assertFillable($expanded));
            }
        }

        foreach ($prepared as $field) {
            $this->ownedPaths[] = AttributePath::toDotted($field->attribute, $this->column);
        }

        return $prepared;
    }

    /**
     * Re-point a nested group -- and every leaf under it -- at the outer
     * group's column and path.
     *
     * The nested group is written to in place. Its fields are the same objects
     * either way, so cloning the group would only give the illusion of safety.
     */
    protected function rebase(self $parent, string $column, string $parentPrefix): self
    {
        $prefix = AttributePath::join($parentPrefix, $this->column);

        foreach ($this->fields() as $field) {
            // The leaf's path relative to *this* group -- which may itself be
            // several levels deep, so the last attribute segment is not enough.
            $relative = AttributePath::toDotted($field->attribute, $this->column);
            $path = AttributePath::join($prefix, $relative);

            $field->attribute = AttributePath::fromDotted($column, $path);

            if ($field->fillCallback instanceof LeafFiller) {
                $field->fillCallback->rebind($parent, $column, $path);
            }
        }

        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Rewrite a leaf's attribute into the column and bind its filler.
     */
    protected function bindLeaf(Field $field): Field
    {
        // Nova does not type Field::$attribute, and a numeric key such as
        // Boolean::make('New', 1) arrives as an int.
        $key = (string) $field->attribute;
        $path = AttributePath::join($this->prefix, $key);

        $field->attribute = AttributePath::toAttribute($this->column, $this->prefix, $key);

        $field->fillUsing(new LeafFiller(
            group: $this,
            field: $field,
            column: $this->column,
            path: $path,
            originalFillCallback: $field->fillCallback,
        ));

        return $field;
    }

    /**
     * Reject anything that cannot round-trip through a JSON column.
     */
    protected function assertFillable(object $field): Field
    {
        if (! $field instanceof Field) {
            throw JsonFieldException::notAField($this->column, $field);
        }

        // Relationship fields hydrate the model (or a pivot) directly rather
        // than producing a storable value; 1.x accepted them and silently did
        // nothing.
        if ($field instanceof Unfillable || $field instanceof RelatableField) {
            throw JsonFieldException::unsupportedField($this->column, $field);
        }

        return $field;
    }
}
