<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Pemilik app: satu-satunya user yang jatuh balik kepada kredential .env.
 *
 * Sejak Fasa 7a semua route berada di belakang middleware `auth` dan
 * `meta.connected`. Test yang memanggil route memerlukan seorang user yang
 * mempunyai kredential — bagi test sedia ada, itu pemilik.
 */
function pemilik(array $attributes = []): User
{
    return User::factory()->owner()->create($attributes);
}

function masukSebagaiPemilik(array $attributes = []): User
{
    $user = pemilik($attributes);

    test()->actingAs($user);

    return $user;
}
