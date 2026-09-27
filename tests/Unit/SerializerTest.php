<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\Enums\StorageFormat;
use Gabrielesbaiz\NovaFieldJson\Exceptions\JsonFieldException;
use Gabrielesbaiz\NovaFieldJson\Support\JsonSerializer;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Support\Fluent;
use Workbench\App\Models\LegacyProduct;
use Workbench\App\Models\Product;
use Workbench\App\Models\SecretProduct;

it('treats a cast column as native', function () {
    expect((new JsonSerializer)->formatFor(new Product, 'meta'))->toBe(StorageFormat::Native);
});

it('encodes an uncast column', function () {
    expect((new JsonSerializer)->formatFor(new LegacyProduct, 'meta'))->toBe(StorageFormat::Encoded);
});

it('treats a custom cast object as native', function () {
    expect((new JsonSerializer)->formatFor(new Product, 'settings'))->toBe(StorageFormat::Native);
});

it('treats a parameterised cast as native', function () {
    $model = new class extends Model
    {
        protected $table = 'products';

        protected $casts = ['meta' => AsCollection::class.':'.stdClass::class];
    };

    expect((new JsonSerializer)->formatFor($model, 'meta'))->toBe(StorageFormat::Native);
});

it('treats an encrypting cast as native', function () {
    expect((new JsonSerializer)->formatFor(new SecretProduct, 'vault'))->toBe(StorageFormat::Native);
});

it('never inspects casts on a Fluent, and leaves no junk attribute behind', function () {
    // Fluent::__call() does not throw for an unknown method -- it records an
    // attribute named after it and returns $this. 2.x's hasCast() probe
    // therefore polluted every action's ActionFields and always reported true.
    $fluent = new Fluent;

    expect((new JsonSerializer)->formatFor($fluent, 'meta'))->toBe(StorageFormat::Native)
        ->and($fluent->getAttributes())->not->toHaveKey('hasCast');
});

it('normalises every stored representation to an array', function (mixed $stored, array $expected) {
    expect((new JsonSerializer)->unserialize($stored))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty string' => ['', []],
    'empty array' => [[], []],
    'json object' => ['{"a":1}', ['a' => 1]],
    'json array' => ['[1,2]', [1, 2]],
    'php array' => [['a' => 1], ['a' => 1]],
    'collection' => [new Collection(['a' => 1]), ['a' => 1]],
]);

it('preserves nesting through an ArrayObject cast', function () {
    // collect($arrayObject)->toArray() -- what 2.x used -- flattens by
    // iteration and loses the nested level.
    $product = new Product;
    $product->settings = ['a' => ['b' => 1]];

    expect($product->settings)->toBeInstanceOf(ArrayObject::class)
        ->and((new JsonSerializer)->unserialize($product->settings))->toBe(['a' => ['b' => 1]]);
});

it('encodes without escaping unicode or slashes', function () {
    $encoded = (new JsonSerializer)->serialize(
        ['label' => 'caffè', 'path' => 'a/b'],
        new LegacyProduct,
        'meta',
    );

    expect($encoded)->toBe('{"label":"caffè","path":"a/b"}');
});

it('reports malformed stored json instead of silently returning null', function () {
    (new JsonSerializer)->unserialize('{not json', 'meta');
})->throws(JsonFieldException::class, 'not valid JSON');

it('round-trips an encrypted column', function () {
    $serializer = new JsonSerializer(encrypted: true);

    $stored = $serializer->serialize(['a' => 1], new LegacyProduct, 'meta');

    expect($stored)->toBeString()
        ->and($stored)->not->toContain('"a"')
        ->and($serializer->unserialize($stored, 'meta'))->toBe(['a' => 1]);
});

it('refuses to encrypt a column that an eloquent cast already encrypts', function () {
    (new JsonSerializer(encrypted: true))->assertNotDoublyEncrypted(new SecretProduct, 'vault');
})->throws(JsonFieldException::class, 'encrypt it twice');

it('reports an undecryptable value clearly', function () {
    (new JsonSerializer(encrypted: true))->unserialize('not-an-encrypted-payload', 'meta');
})->throws(JsonFieldException::class, 'could not be decrypted');

it('honours an explicit storage format over the model casts', function () {
    $serializer = new JsonSerializer(format: StorageFormat::Encoded);

    expect($serializer->serialize(['a' => 1], new Product, 'meta'))->toBe('{"a":1}');
});
