<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\Unifi')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\Unifi\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\Unifi\Exceptions')
    ->toExtend('Beziworld\Unifi\Exceptions\UnifiException')
    ->ignoring('Beziworld\Unifi\Exceptions\UnifiException');
