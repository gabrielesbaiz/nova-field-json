<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson\Tests;

use Gabrielesbaiz\NovaFieldJson\FieldServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\NovaCoreServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createProductsTable();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            NovaCoreServiceProvider::class,
            FieldServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function createProductsTable(): void
    {
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->text('meta')->nullable();
            $table->text('settings')->nullable();
            $table->text('vault')->nullable();
            $table->timestamps();
        });
    }
}
