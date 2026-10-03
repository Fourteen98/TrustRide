<?php

use App\Support\Phone;

it('turns Ghana numbers into E.164', function (string $input) {
    expect(Phone::normalise($input))->toBe('+233241234567');
})->with(['0241234567', '233241234567', '+233241234567', '024 123 4567', '+233 24 123 4567']);

it('rejects numbers that are not Ghanaian mobile numbers', function (string $input) {
    expect(Phone::isValid($input))->toBeFalse();
})->with(['12345', '+14155550100', '02412345678', 'hello']);
