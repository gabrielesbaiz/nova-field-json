<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Json;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

function group(): Json
{
    return Json::make('meta', [
        Text::make('A', 'a'),
        Json::make('nested', [Number::make('B', 'b')]),
    ]);
}

it('forwards show and hide methods to every child, nested ones included', function () {
    $json = group()->hideFromIndex();

    expect(collect($json->fields())->pluck('showOnIndex')->all())->toBe([false, false]);
});

it('forwards field methods that 2.x rejected outright', function () {
    $request = app(NovaRequest::class);

    $json = group()
        ->readonly()
        ->help('Some help')
        ->rules('required')
        ->placeholder('Type here');

    foreach ($json->fields() as $field) {
        expect($field->isReadonly($request))->toBeTrue()
            ->and($field->helpText)->toBe('Some help')
            ->and($field->rules)->toBe(['required']);
    }
});

it('applies an opt-in trait method only to the fields that support it', function () {
    // dependsOn() lives in SupportsDependentFields, which individual field
    // classes opt into -- it is not on Field itself.
    $json = Json::make('meta', [
        Text::make('A', 'a'),
        Number::make('B', 'b'),
    ]);

    $json->dependsOn('other', fn () => null);

    expect($json)->toBeInstanceOf(Json::class);
});

it('rejects an unknown method with a message that names the column', function () {
    group()->definitelyNotAFieldMethod();
})->throws(JsonFieldException::class, "Json::make('meta', [...])->definitelyNotAFieldMethod()");

it('suggests a near match for a mistyped method', function () {
    try {
        group()->hideFromIndexx();
    } catch (JsonFieldException $e) {
        expect($e->getMessage())->toContain('->hideFromIndex()');

        return;
    }

    $this->fail('Expected a JsonFieldException.');
});

it('points at each() as the escape hatch', function () {
    try {
        group()->somethingExotic();
    } catch (JsonFieldException $e) {
        expect($e->getMessage())->toContain('->each(');

        return;
    }

    $this->fail('Expected a JsonFieldException.');
});

it('reaches every child through each()', function () {
    $seen = [];

    group()->each(function (Field $field) use (&$seen): void {
        $seen[] = $field->attribute;
    });

    expect($seen)->toBe(['meta->a', 'meta->nested->b']);
});

it('no longer sprays a property write onto every child', function () {
    // 2.x forwarded any property write to every child, which swallowed typos
    // and leaked the group's own protected state onto the fields.
    $json = group();

    try {
        $json->someProperty = 'x';
    } catch (JsonFieldException $e) {
        expect($e->getMessage())->toContain('->each(')
            ->and($json->fields()[0])->not->toHaveProperty('someProperty');

        return;
    }

    $this->fail('Expected a JsonFieldException.');
});

it('prefers a macro over the forwarding allowlist', function () {
    Json::macro('shout', fn (): string => 'HELLO');

    expect(group()->shout())->toBe('HELLO');
});
