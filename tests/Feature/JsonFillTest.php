<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Json;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Workbench\App\Models\LegacyProduct;
use Workbench\App\Models\Product;

it('rewrites child attributes into the column', function () {
    $json = Json::make('meta', [
        Select::make('Type', 'type'),
        Number::make('Value', 'value'),
    ]);

    expect(attributesOf([$json]))->toBe(['meta->type', 'meta->value']);
});

it('stores every child in one cast column', function () {
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Select::make('Type', 'type'),
            Number::make('Value', 'value'),
        ]),
    ], ['meta->type' => 'percent', 'meta->value' => '12']);

    expect($product->meta)->toBe(['type' => 'percent', 'value' => '12']);
});

it('stores every child in one uncast column', function () {
    // 2.x re-read the column between children, so the second child saw the
    // first child's encoded string and produced [0 => '{"type":...}', ...].
    $product = new LegacyProduct;

    fillFields($product, [
        Json::make('meta', [
            Select::make('Type', 'type'),
            Number::make('Value', 'value'),
        ]),
    ], ['meta->type' => 'percent', 'meta->value' => '12']);

    expect($product->meta)->toBeString()
        ->and(json_decode($product->meta, true))->toBe(['type' => 'percent', 'value' => '12']);
});

it('accepts a numeric attribute key', function () {
    // Nova does not type Field::$attribute, so Boolean::make('New', 1) hands
    // the group an int. 3.0.0 type-errored on it in AttributePath::join().
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Json::make('selling_type', [
                Boolean::make('New', 1),
                Boolean::make('Used', 2),
            ]),
        ]),
    ], ['meta->selling_type->1' => true, 'meta->selling_type->2' => false]);

    expect($product->meta)->toBe(['selling_type' => [1 => true, 2 => false]]);
});

it('nests groups to arbitrary depth', function () {
    $json = Json::make('meta', [
        Text::make('Top', 'top'),
        Json::make('b', [
            Json::make('c', [
                Text::make('Deep', 'd'),
            ]),
        ]),
    ]);

    expect(attributesOf([$json]))->toBe(['meta->top', 'meta->b->c->d']);

    $product = new Product;
    fillFields($product, [$json], ['meta->top' => 'x', 'meta->b->c->d' => 'y']);

    expect($product->meta)->toBe(['top' => 'x', 'b' => ['c' => ['d' => 'y']]]);
});

it('lets each child run its own fill pipeline', function () {
    // 2.x read values straight off the request, so every field that overrides
    // fillAttributeFromRequest was bypassed.
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Boolean::make('Active', 'active'),
            Code::make('Payload', 'payload')->json(),
        ]),
    ], ['meta->active' => '1', 'meta->payload' => '{"nested":true}']);

    expect($product->meta['active'])->toBeTrue()
        ->and($product->meta['payload'])->toBe(['nested' => true]);
});

it('honours a custom fillUsing on a child', function () {
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Text::make('Code', 'code')->fillUsing(
                static function ($request, $model, $attribute, $requestAttribute) {
                    $model->{$attribute} = strtoupper((string) $request->input($requestAttribute));
                }
            ),
        ]),
    ], ['meta->code' => 'abc']);

    expect($product->meta)->toBe(['code' => 'ABC']);
});

it('passes deferred callbacks back to nova', function () {
    $product = new Product;
    $ran = false;

    $callbacks = fillFields($product, [
        Json::make('meta', [
            Text::make('Code', 'code')->fillUsing(
                static function ($request, $model, $attribute, $requestAttribute) use (&$ran) {
                    $model->{$attribute} = $request->input($requestAttribute);

                    return static function () use (&$ran): void {
                        $ran = true;
                    };
                }
            ),
        ]),
    ], ['meta->code' => 'abc']);

    expect($callbacks)->toHaveCount(1)
        ->and($ran)->toBeFalse();

    $callbacks[0]();

    expect($ran)->toBeTrue();
});

it('stores a null for a nullable child but keeps the key', function () {
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Text::make('Code', 'code')->nullable(),
        ]),
    ], ['meta->code' => '']);

    expect($product->meta)->toBe(['code' => null]);
});

it('omits null leaves when pruning', function () {
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [
            Text::make('Code', 'code')->nullable(),
            Text::make('Label', 'label'),
        ])->pruneNulls(),
    ], ['meta->code' => '', 'meta->label' => 'x']);

    expect($product->meta)->toBe(['label' => 'x']);
});

it('applies defaults only where nothing is stored', function () {
    $product = new Product;
    $product->meta = ['discount' => ['type' => 'amount']];

    fillFields($product, [
        Json::make('meta', [
            Text::make('Label', 'label'),
        ])->defaults([
            'discount.type' => 'percent',
            'discount.value' => 0,
        ]),
    ], ['meta->label' => 'x']);

    expect($product->meta)->toBe([
        'discount' => ['type' => 'amount', 'value' => 0],
        'label' => 'x',
    ]);
});

it('rejects a value that is not a field', function () {
    Json::make('meta', [new stdClass]);
})->throws(JsonFieldException::class, 'only accepts');

it('rejects a field that cannot produce a storable value', function () {
    // Heading implements Unfillable; 2.x accepted it and silently stored nothing.
    Json::make('meta', [Heading::make('Section')]);
})->throws(JsonFieldException::class, 'cannot be used inside');

it('reports the dotted paths its fields map to', function () {
    $json = Json::make('meta', [
        Text::make('Label', 'label'),
        Json::make('discount', [
            Text::make('Type', 'type'),
        ]),
    ]);

    expect($json->ownedPaths())->toBe(['label', 'discount.type']);
});
