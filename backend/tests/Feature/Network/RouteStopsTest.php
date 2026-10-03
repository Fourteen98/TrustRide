<?php

use Modules\Network\Actions\SetRouteStops;
use Modules\Network\Models\Route;
use Modules\Network\Models\Stop;

it('keeps a route\'s stops in the order given', function () {
    [$a, $b, $c] = [Stop::create(['name' => 'A']), Stop::create(['name' => 'B']), Stop::create(['name' => 'C'])];
    $route = Route::create(['name' => 'A to C', 'destination' => 'C']);

    app(SetRouteStops::class)->handle($route, [
        ['stop_id' => $b->id], ['stop_id' => $a->id, 'minutes_from_start' => 8], ['stop_id' => $c->id, 'minutes_from_start' => 20],
    ]);

    expect($route->stops->pluck('name')->all())->toBe(['B', 'A', 'C'])
        ->and($route->stops[1]->pivot->minutes_from_start)->toBe(8)
        ->and($route->organisation->slug)->toBe('trustride');

    // Setting them again replaces the list.
    app(SetRouteStops::class)->handle($route, [['stop_id' => $a->id], ['stop_id' => $c->id]]);
    expect($route->fresh()->stops->pluck('name')->all())->toBe(['A', 'C']);
});

it('refuses a route with one stop or with the same stop twice', function () {
    $a = Stop::create(['name' => 'A']);
    $route = Route::create(['name' => 'A', 'destination' => 'A']);

    expect(fn () => app(SetRouteStops::class)->handle($route, [['stop_id' => $a->id]]))->toThrow(DomainException::class)
        ->and(fn () => app(SetRouteStops::class)->handle($route, [['stop_id' => $a->id], ['stop_id' => $a->id]]))->toThrow(DomainException::class);
});
