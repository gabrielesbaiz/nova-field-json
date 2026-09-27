<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Concerns;

use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Laravel\Nova\Fields\Field;

/**
 * Applies a field method to every leaf in the group.
 *
 * 1.x gated this on method_exists(FieldElement::class, ...), which allowed the
 * twelve show/hide methods and nothing else -- ->readonly(), ->rules(),
 * ->help() and ->dependsOn() all threw an argument-less BadMethodCallException.
 * The list below is an explicit allowlist verified against Nova 5.7, and the
 * per-field guard matters because several of these live in opt-in traits
 * (notably ->dependsOn(), which is in SupportsDependentFields rather than on
 * Field itself).
 */
trait ForwardsFieldCalls
{
    /**
     * Field methods that are meaningful to apply to every leaf at once.
     *
     * @var array<int, string>
     */
    protected const FORWARDED = [
        // FieldElement
        'hideFromIndex', 'hideFromDetail', 'hideWhenCreating', 'hideWhenUpdating',
        'showOnIndex', 'showOnDetail', 'showOnCreating', 'showOnUpdating',
        'onlyOnIndex', 'onlyOnDetail', 'onlyOnForms', 'exceptOnForms',

        // Field
        'stacked', 'compact', 'show', 'hide', 'textAlign', 'required',
        'placeholder', 'displayUsing', 'resolveUsing', 'sortable',
        'nullable', 'nullValues', 'helpWidth',

        // MutableFields
        'readonly', 'immutable', 'mutable', 'default', 'computed',

        // HandlesValidation
        'rules', 'creationRules', 'updateRules',

        // Metrics\HasHelpText
        'help',

        // Metable / AuthorizedToSee / ProxiesCanSeeToGate
        'withMeta', 'canSee', 'canSeeWhen',

        // Opt-in traits: present on some fields only, hence the per-field guard.
        'showWhenPeeking', 'peekable', 'copyable', 'filterable', 'fullWidth',
        'dependsOn', 'dependsOnCreating', 'dependsOnUpdating',
    ];

    /**
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (! in_array($method, static::FORWARDED, true)) {
            throw JsonFieldException::unforwardableMethod($this->column, $method, static::FORWARDED);
        }

        $fields = $this->fields();
        $applied = 0;

        foreach ($fields as $field) {
            if (! $this->fieldSupports($field, $method)) {
                continue;
            }

            $field->{$method}(...$parameters);
            $applied++;
        }

        if ($applied === 0 && $fields !== []) {
            throw JsonFieldException::methodUnsupportedByChildren($this->column, $method);
        }

        return $this;
    }

    /**
     * Writing a property on the group used to spray it onto every child, which
     * silently swallowed typos and leaked the group's own protected state.
     */
    public function __set(string $key, mixed $value): void
    {
        throw JsonFieldException::cannotSetProperty($this->column, $key);
    }

    /**
     * Apply a callback to every leaf field in the group, nested ones included.
     *
     * The escape hatch for anything the allowlist does not cover. Overrides
     * FieldMergeValue::each(), which only walks the group's direct children
     * and passes an index as a second argument.
     *
     * Keeps FieldMergeValue::each()'s (field, index) signature so the two stay
     * interchangeable; a one-argument closure works just as well.
     *
     * @param  callable(Field, int): mixed  $callback
     */
    public function each(callable $callback): static
    {
        foreach ($this->fields() as $index => $field) {
            $callback($field, $index);
        }

        return $this;
    }

    /**
     * Alias of each(), for call sites where it reads better.
     *
     * @param  callable(Field, int): mixed  $callback
     */
    public function apply(callable $callback): static
    {
        return $this->each($callback);
    }

    /**
     * Nova's Field has no __call(), so a method that is not declared simply
     * does not exist on that field -- which is exactly the case the opt-in
     * traits produce.
     */
    private function fieldSupports(Field $field, string $method): bool
    {
        return method_exists($field, $method);
    }
}
