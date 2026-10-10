<?php

/**
 * Halaman undang-undang ialah prasyarat App Review Meta, dan kewajipan
 * PDPA Akta 709 secara berasingan.
 */
beforeEach(function () {
    config()->set('dynoads.company', [
        'name' => 'Dynopro Sdn Bhd',
        'registration_no' => '202201234567 (1456789-X)',
        'address' => 'No 1, Jalan Ujian, 40000 Shah Alam, Selangor',
        'email' => 'hello@dynoads.my',
        'phone' => '0187922844',
    ]);
});

it('notis privasi dan terma boleh dibuka tanpa log masuk', function () {
    // Penyemak Meta membukanya sendiri semasa App Review, tanpa akaun.
    $this->get('/privasi')->assertOk();
    $this->get('/terma')->assertOk();
});

it('notis privasi menamakan Data User seperti dikehendaki seksyen 7', function () {
    $this->get('/privasi')
        ->assertSee('Dynopro Sdn Bhd')
        ->assertSee('202201234567 (1456789-X)')
        ->assertSee('Jalan Ujian', false)
        ->assertSee('hello@dynoads.my');
});

it('notis privasi menjerit bila butiran syarikat belum diisi', function () {
    // Notis yang membawa butiran syarikat yang direka lebih teruk daripada
    // tiada notis. Ia mesti mustahil untuk tidak perasan.
    config()->set('dynoads.company.name', null);
    config()->set('dynoads.company.registration_no', null);

    $this->get('/privasi')
        ->assertOk()
        ->assertSee('Notis ini belum lengkap')
        ->assertSee('COMPANY_NAME');
});

it('notis privasi menyenaraikan setiap jenis data yang app betul-betul kumpul', function () {
    $this->get('/privasi')
        ->assertSee('Maklumat akaun')
        ->assertSee('Sambungan Meta anda')
        ->assertSee('Kandungan iklan anda')
        ->assertSee('Prestasi iklan')
        ->assertSee('Maklumat teknikal');
});

it('notis privasi menyatakan hak subjek data dan tempoh 21 hari', function () {
    $this->get('/privasi')
        ->assertSee('Menarik balik kebenaran anda')
        ->assertSee('21 hari')
        ->assertSee('pdp.gov.my');
});

it('terma menyatakan dengan jelas siapa bayar duit iklan', function () {
    // Kekeliruan paling mahal dalam produk macam ni: peniaga sangka kita yang
    // caj dia untuk iklan. Meta yang bil dia, terus.
    $this->get('/terma')
        ->assertSee('Meta mengebil anda terus')
        ->assertSee('tidak')
        ->assertSee('menjamin');
});

it('setiap skrin app memaut ke notis privasi dan terma', function () {
    masukSebagaiPemilik();

    $this->get('/sambung')
        ->assertSee(route('privasi'), false)
        ->assertSee(route('terma'), false);
});
