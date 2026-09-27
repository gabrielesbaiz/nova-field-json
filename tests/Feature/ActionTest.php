<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Json;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Support\Fluent;
use Workbench\App\Models\Product;

it('collects nested action field values into a fluent bag', function () {
    $fields = new Fluent;

    fillFields($fields, [
        Json::make('meta', [
            Text::make('Type', 'type'),
            Json::make('discount', [Text::make('Value', 'value')]),
        ]),
    ], ['meta->type' => 'percent', 'meta->discount->value' => '12']);

    expect($fields->meta)->toBe([
        'type' => 'percent',
        'discount' => ['value' => '12'],
    ]);
});

it('leaves no junk attribute on action fields', function () {
    // 2.x probed $model->hasCast(); on a Fluent that silently recorded an
    // attribute called "hasCast" and reported the column as castable.
    $fields = new Fluent;

    fillFields($fields, [
        Json::make('meta', [Text::make('Type', 'type')]),
    ], ['meta->type' => 'percent']);

    expect($fields->getAttributes())->toHaveKeys(['meta'])
        ->and($fields->getAttributes())->not->toHaveKey('hasCast');
});

it('never encodes for an action, even when the column is uncast', function () {
    $fields = new Fluent;

    fillFields($fields, [
        Json::make('unmapped', [Text::make('Type', 'type')]),
    ], ['unmapped->type' => 'percent']);

    expect($fields->unmapped)->toBeArray();
});

it('fills every model in an action loop independently', function () {
    // The 2.x $cleanedOut flag lived on the field instance and was never
    // reset, so from the second model onward the column was never cleared.
    $json = Json::make('meta', [Text::make('A', 'a'), Text::make('B', 'b')]);

    $models = collect(range(1, 3))->map(function (int $i): Product {
        $product = new Product;
        $product->meta = ['a' => "stale-{$i}", 'b' => "stale-{$i}", 'keep' => $i];

        return $product;
    });

    foreach ($models as $i => $product) {
        $n = $i + 1;

        fillFields($product, [$json], ['meta->a' => "fresh-{$n}", 'meta->b' => "fresh-{$n}"]);
    }

    expect($models[0]->meta)->toBe(['a' => 'fresh-1', 'b' => 'fresh-1', 'keep' => 1])
        ->and($models[1]->meta)->toBe(['a' => 'fresh-2', 'b' => 'fresh-2', 'keep' => 2])
        ->and($models[2]->meta)->toBe(['a' => 'fresh-3', 'b' => 'fresh-3', 'keep' => 3]);
});

it('resolves child defaults for an action', function () {
    // 2.x left resolve() empty and never implemented resolveForAction(),
    // which silently dropped ->default() on action fields.
    $json = Json::make('meta', [
        Text::make('Type', 'type')->default('percent'),
    ]);

    $json->resolveForAction(app(Laravel\Nova\Http\Requests\NovaRequest::class));

    expect($json->fields()[0]->value)->toBe('percent');
});
