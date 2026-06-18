<?php

declare(strict_types=1);

// Architecture tests encode the OMNIBUS Law as executable rules (testing-sdd §2.1).

arch('php preset')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'eval', 'exec', 'shell_exec'])
    ->not->toBeUsed();

arch('weak hashes are banned')
    ->expect(['md5', 'sha1'])
    ->not->toBeUsed();

arch('domain models are final')
    ->expect('App\Domain\Ticketing\Models')
    ->toBeClasses()
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('value objects are final and readonly')
    ->expect('App\Domain\Shared\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('ticketing value objects are final and readonly')
    ->expect('App\Domain\Ticketing\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('actions are final')
    ->expect('App\Domain\Ticketing\Actions')
    ->toBeFinal();

arch('enums are backed')
    ->expect('App\Domain\Ticketing\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('domain does not depend on the HTTP layer')
    ->expect('App\Domain')
    ->not->toUse('Illuminate\Http\Request');
