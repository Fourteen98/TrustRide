<?php

/*
 * Who is allowed to depend on whom. This is the table in build-plan.pdf, section 3.
 * If a module uses something it shouldn't, this test fails the build.
 *
 * Every module may also use App\Support (the shared event shape and helpers).
 */
const MODULE_MAY_USE = [
    'Access' => [],
    'Network' => ['Access'],
    'Riders' => ['Access', 'Network'],
    'Drivers' => ['Access', 'Network'],
    'Subscriptions' => ['Access', 'Riders', 'Network', 'Drivers', 'Payments'],
    'Trips' => ['Access', 'Subscriptions', 'Riders', 'Drivers', 'Network'],
    'Support' => ['Access', 'Riders', 'Drivers', 'Trips'],
    // These two know nothing about anyone, so they can move into another product later.
    'Payments' => [],
    'Messaging' => [],
];

foreach (MODULE_MAY_USE as $module => $allowed) {
    $forbidden = array_values(array_diff(array_keys(MODULE_MAY_USE), $allowed, [$module]));

    arch("{$module} only uses the modules it is allowed to")
        ->expect("Modules\\{$module}")
        ->not->toUse(array_map(fn (string $other) => "Modules\\{$other}", $forbidden));

    arch("{$module} events follow the shared shape")
        ->expect("Modules\\{$module}\\Events")
        ->toImplement('App\Support\Contracts\DomainEvent');
}

arch('modules never reach into a side')
    ->expect('Modules')
    ->not->toUse(['App\Admin', 'App\Api']);

arch('the shared support code knows no module')
    ->expect('App\Support')
    ->not->toUse('Modules');

arch('no debugging calls are left in')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
