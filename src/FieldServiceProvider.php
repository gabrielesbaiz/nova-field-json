<?php

declare(strict_types=1);

namespace Gabrielesbaiz\NovaFieldJson;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Asset name registered with Nova.
     *
     * Nova serves an asset from /nova-api/scripts/{script}, a route with no
     * slash in its parameter, so a name carrying one never routes: the browser
     * gets the 404 page and reports "Unexpected token '<'" on line 1 of what
     * it expected to be JavaScript.
     */
    public const ASSET = 'gabrielesbaiz-nova-field-json';

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/nova-field-json'),
        ], 'nova-field-json-lang');

        Nova::serving(function (): void {
            $this->registerAssets();
            $this->registerTranslations();
        });
    }

    /**
     * Register the compiled editor assets.
     *
     * Nova::mix() reads dist/mix-manifest.json and registers both the script
     * and the stylesheet, with the manifest hash providing cache busting.
     * The guard keeps a source checkout (where dist/ has not been built)
     * usable for the Json composer, which needs no assets at all.
     */
    protected function registerAssets(): void
    {
        $manifest = __DIR__.'/../dist/mix-manifest.json';

        if (is_file($manifest)) {
            Nova::mix(self::ASSET, $manifest);
        }
    }

    protected function registerTranslations(): void
    {
        $locale = __DIR__."/../resources/lang/{$this->app->getLocale()}.json";

        if (is_file($locale)) {
            Nova::translations($locale);
        }
    }
}
