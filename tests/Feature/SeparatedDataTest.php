<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Json;
use Laravel\Nova\Fields\Text;
use Workbench\App\Models\Product;

it('lets two groups share one column without erasing each other', function () {
    // In 2.x the first child of a group nulled the whole column, so whichever
    // group filled second wiped the first -- unless you remembered
    // ->saveHistory(). Merging is now the default.
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')]),
        Json::make('meta', [Text::make('B', 'b')]),
    ], ['meta->a' => '1', 'meta->b' => '2']);

    expect($product->meta)->toBe(['a' => '1', 'b' => '2']);
});

it('leaves a sibling group untouched when only one group changes', function () {
    $product = new Product;
    $product->meta = ['a' => 'old', 'b' => 'keep'];

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')]),
    ], ['meta->a' => 'new']);

    expect($product->meta)->toBe(['a' => 'new', 'b' => 'keep']);
});

it('leaves a key alone when its field submitted nothing', function () {
    // "Not written" cannot mean "delete": Nova filters readonly, unauthorised
    // and view-hidden fields out before fill, so deleting here would destroy
    // data the user never touched. Absent means unchanged.
    $product = new Product;
    $product->meta = ['a' => 'kept', 'b' => 'old'];

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a'), Text::make('B', 'b')]),
    ], ['meta->b' => 'fresh']);

    expect($product->meta)->toBe(['a' => 'kept', 'b' => 'fresh']);
});

it('offers ->replaces() for a group that really does own the column', function () {
    $product = new Product;
    $product->meta = ['stale' => 'gone', 'b' => 'old'];

    fillFields($product, [
        Json::make('meta', [Text::make('B', 'b')])->replaces(),
    ], ['meta->b' => 'fresh']);

    expect($product->meta)->toBe(['b' => 'fresh']);
});

it('preserves keys written outside nova', function () {
    $product = new Product;
    $product->meta = ['external' => 'set-by-a-job'];

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')]),
    ], ['meta->a' => '1']);

    expect($product->meta)->toBe(['external' => 'set-by-a-job', 'a' => '1']);
});

it('discards everything else when a group replaces the column', function () {
    $product = new Product;
    $product->meta = ['external' => 'set-by-a-job'];

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')])->replaces(),
    ], ['meta->a' => '1']);

    expect($product->meta)->toBe(['a' => '1']);
});

it('refuses to let a replacing group share a column', function () {
    $product = new Product;

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')])->replaces(),
        Json::make('meta', [Text::make('B', 'b')]),
    ], ['meta->a' => '1', 'meta->b' => '2']);
})->throws(JsonFieldException::class, 'must be the only group');

it('keeps nested groups scoped to their own sub-path', function () {
    $product = new Product;
    $product->meta = ['discount' => ['type' => 'percent', 'note' => 'keep']];

    fillFields($product, [
        Json::make('meta', [
            Json::make('discount', [Text::make('Type', 'type')]),
        ]),
    ], ['meta->discount->type' => 'amount']);

    expect($product->meta)->toBe(['discount' => ['type' => 'amount', 'note' => 'keep']]);
});

it('keeps a rewritten key in its original position', function () {
    $product = new Product;
    $product->meta = ['a' => 'old', 'b' => 'keep', 'c' => 'also'];

    fillFields($product, [
        Json::make('meta', [Text::make('A', 'a')]),
    ], ['meta->a' => 'new']);

    expect(array_keys($product->meta))->toBe(['a', 'b', 'c']);
});

it('never deletes the value of a readonly field', function () {
    // Nova rejects readonly fields in FillsFields before any of them fill, so
    // a group must not treat "this field wrote nothing" as "drop this key".
    $product = new Product;
    $product->meta = ['a' => 'keep-me', 'b' => 'old'];

    fillFields($product, [
        Json::make('meta', [
            Text::make('A', 'a')->readonly(),
            Text::make('B', 'b'),
        ]),
    ], ['meta->b' => 'new']);

    expect($product->meta)->toBe(['a' => 'keep-me', 'b' => 'new']);
});

it('never deletes the value of a field the user cannot see', function () {
    $product = new Product;
    $product->meta = ['secret' => 'keep-me', 'b' => 'old'];

    fillFields($product, [
        Json::make('meta', [
            Text::make('Secret', 'secret')->canSee(fn () => false),
            Text::make('B', 'b'),
        ]),
    ], ['meta->b' => 'new']);

    expect($product->meta)->toBe(['secret' => 'keep-me', 'b' => 'new']);
});

it('never deletes a value whose field submitted nothing', function () {
    // A File field with no new upload posts no key at all; Nova leaves the
    // attribute alone, and so must we.
    $product = new Product;
    $product->meta = ['path' => 'uploads/a.png'];

    fillFields($product, [
        Json::make('meta', [Text::make('Path', 'path')]),
    ], []);

    expect($product->meta)->toBe(['path' => 'uploads/a.png']);
});
