<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Support\AttributePath;

it('builds a root-level attribute', function () {
    expect(AttributePath::toAttribute('meta', '', 'type'))->toBe('meta->type');
});

it('builds a nested attribute', function () {
    expect(AttributePath::toAttribute('meta', 'discount', 'type'))->toBe('meta->discount->type');
});

it('expands a dotted leaf key into separate segments', function () {
    expect(AttributePath::toAttribute('meta', '', 'a.b'))->toBe('meta->a->b');
});

it('converts an attribute back to a dotted path relative to the column', function () {
    expect(AttributePath::toDotted('meta->discount->type', 'meta'))->toBe('discount.type')
        ->and(AttributePath::toDotted('meta->type', 'meta'))->toBe('type');
});

it('reads the final attribute segment', function () {
    expect(AttributePath::leafKey('meta->discount->type'))->toBe('type')
        ->and(AttributePath::leafKey('type'))->toBe('type');
});

it('joins dotted paths, skipping empty segments', function () {
    expect(AttributePath::join('', 'type'))->toBe('type')
        ->and(AttributePath::join('discount', 'type'))->toBe('discount.type')
        ->and(AttributePath::join('a', 'b.c'))->toBe('a.b.c');
});

it('round-trips a dotted path through an attribute', function () {
    $attribute = AttributePath::fromDotted('meta', 'a.b.c');

    expect($attribute)->toBe('meta->a->b->c')
        ->and(AttributePath::toDotted($attribute, 'meta'))->toBe('a.b.c');
});
