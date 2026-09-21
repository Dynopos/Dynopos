<?php

use App\Support\Phone;

/**
 * Format nombor mula menjadi penting sejak Fasa 7a: nombor yang peniaga taip
 * kini benar-benar dihantar ke Meta dalam promoted_object, dan Meta menolak
 * apa-apa yang bukan digit tanpa +.
 */
it('menerima cara orang biasa menaip nombor Malaysia', function (string $input) {
    expect(Phone::normalise($input))->toBe('60123456789');
})->with([
    '0123456789',
    '012-345 6789',
    '+60123456789',
    '60123456789',
    '  012 345 6789  ',
    '+6012-3456789',
]);

it('menolak nombor yang tidak munasabah', function (?string $input) {
    expect(Phone::normalise($input))->toBeNull();
})->with([null, '', '123', 'abcd', '601234567890123']);

it('memaparkan semula dalam bentuk yang orang kenal', function () {
    expect(Phone::pretty('60123456789'))->toBe('012-345 6789')
        ->and(Phone::pretty('0123456789'))->toBe('012-345 6789');
});

it('memulangkan input asal bila ia tak boleh ditafsir', function () {
    expect(Phone::pretty('bukan nombor'))->toBe('bukan nombor');
});
