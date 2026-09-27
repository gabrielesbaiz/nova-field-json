<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\JsonEditor;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Workbench\App\Models\LegacyProduct;
use Workbench\App\Models\Product;

function resolveEditor(JsonEditor $field, object $resource): mixed
{
    $field->resolve($resource);

    return $field->value;
}

it('resolves raw mode as pretty printed json', function () {
    $product = new Product;
    $product->meta = ['a' => 1];

    expect(resolveEditor(JsonEditor::make('Meta', 'meta')->raw(), $product))
        ->toBe("{\n    \"a\": 1\n}");
});

it('resolves tree mode as the decoded structure', function () {
    $product = new Product;
    $product->meta = ['a' => ['b' => 1]];

    expect(resolveEditor(JsonEditor::make('Meta', 'meta')->tree(), $product))
        ->toBe(['a' => ['b' => 1]]);
});

it('resolves key-value mode as typed rows', function () {
    $product = new Product;
    $product->meta = ['label' => 'x', 'count' => 3, 'active' => true, 'none' => null];

    expect(resolveEditor(JsonEditor::make('Meta', 'meta')->keyValue(), $product))->toBe([
        ['key' => 'label', 'value' => 'x', 'type' => 'string'],
        ['key' => 'count', 'value' => 3, 'type' => 'number'],
        ['key' => 'active', 'value' => true, 'type' => 'boolean'],
        ['key' => 'none', 'value' => null, 'type' => 'null'],
    ]);
});

it('round-trips a deep structure through tree mode', function () {
    $product = new Product;
    $value = ['a' => ['b' => [1, 2, 3]], 'c' => true];

    fillFields($product, [JsonEditor::make('Meta', 'meta')->tree()], [
        'meta' => json_encode($value),
    ]);

    expect($product->meta)->toBe($value);
});

it('preserves list versus map through tree mode', function () {
    $product = new Product;

    fillFields($product, [JsonEditor::make('Meta', 'meta')->tree()], [
        'meta' => '{"list":[1,2],"map":{"0":1,"2":2}}',
    ]);

    expect($product->meta['list'])->toBe([1, 2])
        ->and($product->meta['map'])->toBe(['0' => 1, '2' => 2]);
});

it('coerces key-value rows to their declared type', function () {
    $product = new Product;

    fillFields($product, [JsonEditor::make('Meta', 'meta')->keyValue()], [
        'meta' => json_encode([
            ['key' => 'asNumber', 'value' => '1', 'type' => 'number'],
            ['key' => 'asString', 'value' => '1', 'type' => 'string'],
            ['key' => 'asBool', 'value' => 'true', 'type' => 'boolean'],
            ['key' => 'asNull', 'value' => 'ignored', 'type' => 'null'],
            ['key' => '', 'value' => 'dropped', 'type' => 'string'],
        ]),
    ]);

    expect($product->meta)->toBe([
        'asNumber' => 1,
        'asString' => '1',
        'asBool' => true,
        'asNull' => null,
    ]);
});

it('rejects malformed json as a validation error, not a server error', function () {
    fillFields(new Product, [JsonEditor::make('Meta', 'meta')->raw()], ['meta' => '{not json']);
})->throws(ValidationException::class);

it('encodes for an uncast column and stores an array for a cast one', function () {
    $cast = new Product;
    $uncast = new LegacyProduct;

    fillFields($cast, [JsonEditor::make('Meta', 'meta')], ['meta' => '{"a":1}']);
    fillFields($uncast, [JsonEditor::make('Meta', 'meta')], ['meta' => '{"a":1}']);

    expect($cast->meta)->toBe(['a' => 1])
        ->and($uncast->meta)->toBe('{"a":1}');
});

it('round-trips an encrypted column', function () {
    $product = new LegacyProduct;

    fillFields($product, [JsonEditor::make('Meta', 'meta')->encrypted()], ['meta' => '{"a":1}']);

    expect($product->meta)->toBeString()
        ->and($product->meta)->not->toContain('"a"');

    $field = JsonEditor::make('Meta', 'meta')->encrypted()->tree();

    expect(resolveEditor($field, $product))->toBe(['a' => 1]);
});

it('prunes nulls when asked', function () {
    $product = new Product;

    fillFields($product, [JsonEditor::make('Meta', 'meta')->pruneNulls()], [
        'meta' => '{"a":1,"b":null,"c":{"d":null}}',
    ]);

    expect($product->meta)->toBe(['a' => 1]);
});

it('works against a sub-path of a column', function () {
    $product = new Product;
    $product->meta = ['settings' => ['theme' => 'dark']];

    expect(resolveEditor(JsonEditor::make('Settings', 'meta->settings')->tree(), $product))
        ->toBe(['theme' => 'dark']);
});

it('fills repeatable rows in order', function () {
    $product = new Product;

    fillFields($product, [
        JsonEditor::make('Tiers', 'meta')->repeatable(fn () => [
            Text::make('Label', 'label'),
            Number::make('Price', 'price'),
        ]),
    ], [
        'meta' => [
            ['fields' => ['label' => 'Small', 'price' => '10']],
            ['fields' => ['label' => 'Large', 'price' => '20']],
        ],
    ]);

    expect($product->meta)->toBe([
        ['label' => 'Small', 'price' => '10'],
        ['label' => 'Large', 'price' => '20'],
    ]);
});

it('gives each repeatable row its own field values when passed a plain array', function () {
    // Sharing one set of field objects across rows means each row's resolve()
    // overwrites the last, and the front end renders N copies of row N.
    $product = new Product;
    $product->meta = [
        ['label' => 'Small'],
        ['label' => 'Large'],
    ];

    $field = JsonEditor::make('Tiers', 'meta')->repeatable([Text::make('Label', 'label')]);

    $rows = resolveEditor($field, $product);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['fields'][0]['value'])->toBe('Small')
        ->and($rows[1]['fields'][0]['value'])->toBe('Large');
});

it('emits row count and per-row rules', function () {
    $request = NovaRequest::create('/', 'POST', [
        'tiers' => [['fields' => ['label' => 'Small']]],
    ]);
    $request->setContainer(app());

    $rules = JsonEditor::make('Tiers', 'tiers')
        ->repeatable(fn () => [Text::make('Label', 'label')->rules('required')])
        ->min(1)
        ->max(10)
        ->getCreationRules($request);

    expect($rules['tiers'])->toContain('array', 'min:1', 'max:10')
        ->and($rules['tiers.0.fields.label'])->toBe(['required']);
});

it('names per-row validation attributes readably', function () {
    $request = NovaRequest::create('/', 'POST', [
        'tiers' => [['fields' => ['label' => 'Small']]],
    ]);
    $request->setContainer(app());

    $names = JsonEditor::make('Tiers', 'tiers')
        ->repeatable(fn () => [Text::make('Label', 'label')])
        ->getValidationAttributeNames($request);

    expect($names['tiers.0.fields.label'])->toBe('Tiers #1 Label');
});

it('exposes the editor configuration to the front end', function () {
    $meta = JsonEditor::make('Tiers', 'meta')
        ->repeatable(fn () => [Text::make('Label', 'label')])
        ->min(1)
        ->max(5)
        ->sortable()
        ->height(500)
        ->jsonSerialize();

    expect($meta['mode'])->toBe('repeatable')
        ->and($meta['min'])->toBe(1)
        ->and($meta['max'])->toBe(5)
        ->and($meta['sortableRows'])->toBeTrue()
        ->and($meta['height'])->toBe(500)
        ->and($meta['rowTemplate'])->toBeArray()
        ->and($meta['valueTypes'])->toBeArray();
});

it('keeps row sortability separate from index column sortability', function () {
    $field = JsonEditor::make('Meta', 'meta')->sortable();

    expect($field->jsonSerialize()['sortableRows'])->toBeTrue()
        ->and($field->sortable)->toBeFalse();

    expect(JsonEditor::make('Meta', 'meta')->sortableColumn()->sortable)->toBeTrue();
});

it('is hidden from the index by default', function () {
    expect(JsonEditor::make('Meta', 'meta')->showOnIndex)->toBeFalse();
});
