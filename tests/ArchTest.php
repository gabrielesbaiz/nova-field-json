<?php

declare(strict_types=1);

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->each->not->toBeUsed();

arch('the package declares strict types throughout')
    ->expect('Gabrielesbaiz\NovaFieldJson')
    ->toUseStrictTypes();

arch('support classes are final')
    ->expect('Gabrielesbaiz\NovaFieldJson\Support')
    ->toBeFinal();

arch('enums are backed')
    ->expect('Gabrielesbaiz\NovaFieldJson\Enums')
    ->toBeStringBackedEnums();

arch('exceptions extend the package exception')
    ->expect('Gabrielesbaiz\NovaFieldJson\Exceptions')
    ->toExtend(RuntimeException::class);
