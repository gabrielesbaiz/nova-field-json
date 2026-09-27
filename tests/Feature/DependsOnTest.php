<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Json;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/*
|--------------------------------------------------------------------------
| Dependent fields
|--------------------------------------------------------------------------
|
| A child of a group is an ordinary Nova field, so ->dependsOn() drives it
| exactly as it drives a column-backed field. The one thing to know is that
| the group rewrites a child's attribute to `column->key`, and dependsOn()
| keys on attributes -- so the watched attribute has to be the rewritten one.
|
 */

it('runs a child dependsOn callback keyed by the rewritten attribute', function () {
    $json = Json::make('meta', [
        Select::make('Type', 'type')->options(['percent' => 'Percent', 'amount' => 'Amount']),

        Text::make('Value', 'value')
            ->dependsOn('meta->type', function (Text $field, NovaRequest $request, $formData): void {
                $field->readonly($formData->get('meta->type') === 'percent');
                $field->withMeta(['seen' => $formData->get('meta->type')]);
            }),
    ]);

    $fields = flattenFields([$json]);

    expect(array_map(static fn ($field): string => $field->attribute, $fields))
        ->toBe(['meta->type', 'meta->value']);

    $request = NovaRequest::create('/nova-api/products/creation-fields', 'POST', [
        'meta->type' => 'percent',
        'meta->value' => '12',
    ]);
    $request->setContainer(app());

    $value = $fields[1]->applyDependsOn($request);

    expect($value->meta()['seen'] ?? null)->toBe('percent')
        ->and($value->isReadonly($request))->toBeTrue();
});

it('serializes the rewritten attribute so the front end watches the right field', function () {
    $json = Json::make('meta', [
        Select::make('Type', 'type'),
        Text::make('Value', 'value')->dependsOn('meta->type', fn () => null),
    ]);

    $request = NovaRequest::create('/', 'GET');
    $request->setContainer(app());

    $payload = flattenFields([$json])[1]->jsonSerialize();

    expect($payload['dependsOn'])->toHaveKey('meta->type');
});

it('captures the pre-rewrite attribute when a field instance is passed', function () {
    // Dependent::__construct() reads $field->attribute immediately, which is
    // before the group rewrites it -- so a Field instance records 'type' and
    // the front end watches a field that does not exist. Pass the string.
    $type = Select::make('Type', 'type');

    $json = Json::make('meta', [
        $type,
        Text::make('Value', 'value')->dependsOn($type, fn () => null),
    ]);

    $request = NovaRequest::create('/', 'GET');
    $request->setContainer(app());

    $payload = flattenFields([$json])[1]->jsonSerialize();

    expect($payload['dependsOn'])->toHaveKey('type')
        ->and($payload['dependsOn'])->not->toHaveKey('meta->type');
});
