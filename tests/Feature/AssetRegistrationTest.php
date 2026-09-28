<?php

declare(strict_types=1);

use Gabrielesbaiz\NovaFieldJson\FieldServiceProvider;

it('registers an asset name Nova can route', function () {
    // Nova serves assets from /nova-api/scripts/{script}, whose parameter
    // never matches a slash. A name carrying one 404s to the HTML error page
    // and the browser reports "Unexpected token '<'".
    expect(FieldServiceProvider::ASSET)
        ->not->toContain('/')
        ->toMatch('/^[A-Za-z0-9._-]+$/');
});
