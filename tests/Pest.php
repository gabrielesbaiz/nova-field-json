<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Json;
use Gabrielesbaiz\NovaFieldJson\Support\BucketRegistry;
use Gabrielesbaiz\NovaFieldJson\Tests\TestCase;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

uses(TestCase::class)->in(__DIR__);

// The registry is process-wide; a leaked bucket would let one test's model
// state bleed into the next.
uses()->beforeEach(fn () => BucketRegistry::flush())->in(__DIR__);

/**
 * Flatten a field list the way Nova's resource field resolution does.
 *
 * @param  array<int, object>  $fields
 * @return array<int, Field>
 */
function flattenFields(array $fields): array
{
    return collect($fields)
        ->flatMap(static fn (object $field): array => $field instanceof Json ? $field->fields() : [$field])
        ->all();
}

/**
 * Drive a fill the way Laravel\Nova\FillsFields does, without the HTTP layer.
 *
 * @param  array<int, object>  $fields
 * @param  array<string, mixed>  $input
 * @return array<int, callable> The deferred post-save callbacks Nova would run.
 */
function fillFields(object $model, array $fields, array $input): array
{
    $request = NovaRequest::create('/nova-api/products', 'POST', $input);
    $request->setContainer(app());

    return collect(flattenFields($fields))
        ->map(static fn (Field $field) => $field->fill($request, $model))
        ->filter(static fn ($callback) => is_callable($callback))
        ->values()
        ->all();
}

/**
 * The attribute names Nova would post for a field list.
 *
 * @param  array<int, object>  $fields
 * @return array<int, string>
 */
function attributesOf(array $fields): array
{
    return array_map(static fn (Field $field): string => $field->attribute, flattenFields($fields));
}
