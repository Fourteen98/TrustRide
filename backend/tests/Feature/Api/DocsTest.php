<?php

use Modules\Access\Models\Staff;

beforeEach(fn () => syncAccess());

it('keeps the API docs away from guests outside local', function () {
    $this->get('/docs/api')->assertForbidden();
    $this->get('/docs/api.json')->assertForbidden();
});

it('shows the API docs to logged-in staff', function () {
    $this->actingAs(staff(Staff::SUPER_ADMIN), 'platform');

    $this->get('/docs/api')->assertOk();

    $document = $this->get('/docs/api.json')->assertOk()->json();

    expect($document['paths'])->toHaveKeys(['/auth/code', '/auth/verify', '/auth/me', '/rider/v1/home', '/driver/v1/home'])
        ->and($document['paths']['/auth/verify']['post']['tags'])->toBe(['Login'])
        ->and($document['paths']['/rider/v1/home']['get']['tags'])->toBe(['Rider'])
        // Login is public. Everything else needs the bearer token.
        ->and($document['paths']['/auth/code']['post']['security'])->toBe([])
        ->and($document['paths']['/auth/me']['get'])->not->toHaveKey('security')
        ->and($document['components']['schemas'])->toHaveKey('AccountResource');
});
