<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

arch('providers implement ProviderInterface')
    ->expect('Moneo\RequestForwarder\Providers')
    ->toImplement('Moneo\RequestForwarder\Providers\ProviderInterface')
    ->ignoring('Moneo\RequestForwarder\Providers\ProviderInterface');

arch('events are in the Events namespace')
    ->expect('Moneo\RequestForwarder\Events')
    ->toBeClasses();

arch('exceptions extend base Exception')
    ->expect('Moneo\RequestForwarder\Exceptions')
    ->toExtend('Exception');
