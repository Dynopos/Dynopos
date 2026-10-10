@php
    $syarikat = config('dynoads.company');
    $lengkap = filled($syarikat['name']) && filled($syarikat['registration_no']) && filled($syarikat['address']);
    $emel = $syarikat['email'] ?: null;
@endphp

<x-layouts.legal title="Notis Privasi">
    <p class="hint mt-2">
        Versi {{ config('dynoads.privacy.version') }} · Akta Perlindungan Data Peribadi 2010 (Akta 709)
    </p>

    @unless ($lengkap)
        <div class="mt-5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm leading-relaxed text-amber-900 dark:text-amber-200">
            <p class="font-bold">Notis ini belum lengkap.</p>
            <p class="mt-1.5">
                Butiran syarikat belum ditetapkan. Seksyen 7 Akta 709 mewajibkan notis ini menamakan
                Data User — nama berdaftar, nombor pendaftaran dan alamat.
            </p>
            <p class="mt-1.5 text-amber-800/80 dark:text-amber-200/70">
                Isi <code>COMPANY_NAME</code>, <code>COMPANY_SSM</code>, <code>COMPANY_ADDRESS</code>,
                <code>COMPANY_EMAIL</code> dan <code>COMPANY_PHONE</code> dalam <code>.env</code>,
                kemudian <code>php artisan optimize:clear</code>.
            </p>
        </div>
    @endunless

    <h2 class="mt-7 text-base font-bold">Siapa kami</h2>
    <div class="card mt-2 text-sm">
        <p><strong>{{ $syarikat['name'] ?: '[Nama syarikat belum ditetapkan]' }}</strong></p>
        <p class="t-muted">No. pendaftaran: {{ $syarikat['registration_no'] ?: '[belum ditetapkan]' }}</p>
        <p class="t-muted">{{ $syarikat['address'] ?: '[alamat belum ditetapkan]' }}</p>
        <p class="t-muted">{{ $emel ?: '[emel belum ditetapkan]' }} · {{ $syarikat['phone'] ?: '[telefon belum ditetapkan]' }}</p>
    </div>
    <p class="mt-2 text-sm">
        Kami ialah Data User bagi maklumat peribadi yang anda berikan melalui Dyno Ads
        (dynoads.my). Notis ini menerangkan apa yang kami kumpul, kenapa, dan hak anda ke atasnya.
    </p>

    <h2 class="mt-7 text-base font-bold">Apa yang kami kumpul</h2>
    <div class="mt-2 space-y-3 text-sm">
        <div>
            <p class="font-semibold">Maklumat akaun</p>
            <p class="t-muted">Nama, alamat emel, nombor telefon, dan kata laluan. Kata laluan
            disimpan sebagai hash — kami sendiri tidak boleh membacanya.</p>
        </div>
        <div>
            <p class="font-semibold">Sambungan Meta anda</p>
            <p class="t-muted">Token akses, ID akaun iklan, ID dan nama Page Facebook, nombor
            WhatsApp untuk terima lead, dan ID pengguna Meta anda. Token disimpan berenkripsi.</p>
        </div>
        <div>
            <p class="font-semibold">Kandungan iklan anda</p>
            <p class="t-muted">Gambar dan video yang anda muat naik, teks masalah dan tawaran yang
            anda tulis, dan caption yang dijana.</p>
        </div>
        <div>
            <p class="font-semibold">Prestasi iklan</p>
            <p class="t-muted">Perbelanjaan, tayangan, klik dan bilangan lead — ditarik dari Meta
            untuk iklan yang app ini sendiri buat.</p>
        </div>
        <div>
            <p class="font-semibold">Maklumat teknikal</p>
            <p class="t-muted">Alamat IP dan jenis pelayar, disimpan dalam rekod sesi untuk
            mengekalkan log masuk anda dan mengesan percubaan log masuk yang mencurigakan.</p>
        </div>
    </div>

    <h2 class="mt-7 text-base font-bold">Kenapa kami kumpul</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
        <li>Mencipta dan menguruskan akaun anda</li>
        <li>Membuat dan melancarkan iklan di Meta bagi pihak anda</li>
        <li>Menjana caption untuk iklan anda</li>
        <li>Memaparkan prestasi iklan anda</li>
        <li>Menghubungi anda berkenaan perkhidmatan ini</li>
    </ul>
    <p class="mt-2 text-sm">
        Kami tidak menggunakan data anda untuk tujuan lain tanpa memberitahu anda dan meminta
        kebenaran semula.
    </p>

    <h2 class="mt-7 text-base font-bold">Dengan siapa kami kongsi</h2>
    <div class="mt-2 space-y-3 text-sm">
        <div>
            <p class="font-semibold">Meta Platforms (Facebook)</p>
            <p class="t-muted">Menerima gambar, video, teks iklan, nombor WhatsApp dan tetapan
            sasaran anda — kerana di situlah iklan anda dijalankan. Data diproses di luar Malaysia.</p>
        </div>
        <div>
            <p class="font-semibold">Pembekal AI penulis caption</p>
            <p class="t-muted">Menerima <strong>hanya</strong> teks masalah dan tawaran yang anda
            tulis, untuk menjana caption. Tiada nama, emel, nombor telefon atau gambar dihantar.
            Diproses di luar Malaysia.</p>
        </div>
        <div>
            <p class="font-semibold">Pembekal pelayan</p>
            <p class="t-muted">Menyimpan pangkalan data dan fail yang anda muat naik.</p>
        </div>
    </div>
    <p class="mt-2 text-sm">
        Kami <strong>tidak menjual</strong> data anda, dan tidak berkongsi dengan sesiapa selain
        yang disenaraikan di atas.
    </p>

    <h2 class="mt-7 text-base font-bold">Pemindahan ke luar negara</h2>
    <p class="mt-2 text-sm">
        Meta dan pembekal AI memproses data di luar Malaysia. Dengan menggunakan Dyno Ads untuk
        melancarkan iklan, anda bersetuju dengan pemindahan ini — ia tidak dapat dielakkan kerana
        platform iklan itu sendiri berada di luar negara.
    </p>

    <h2 class="mt-7 text-base font-bold">Berapa lama kami simpan</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
        <li>Token Meta — dipadam serta-merta apabila anda memutuskan sambungan</li>
        <li>Data akaun dan iklan — {{ config('dynoads.privacy.retention_days') }} hari selepas
            anda menutup akaun, kemudian dipadam</li>
        <li>Rekod permintaan pemadaman — disimpan sebagai bukti permintaan anda dilayan</li>
    </ul>

    <h2 class="mt-7 text-base font-bold">Hak anda</h2>
    <p class="mt-2 text-sm">Di bawah Akta 709, anda berhak:</p>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
        <li>Mengetahui bagaimana data anda diproses</li>
        <li>Melihat data peribadi yang kami simpan tentang anda</li>
        <li>Membetulkan maklumat yang tidak tepat</li>
        <li>Menarik balik kebenaran anda</li>
        <li>Menghalang pemprosesan yang menyebabkan kerugian atau tekanan</li>
        <li>Menghalang pemprosesan untuk pemasaran langsung</li>
        <li>Bebas daripada keputusan yang dibuat secara automatik sepenuhnya</li>
    </ul>
    <p class="mt-2 text-sm">
        Untuk menggunakan mana-mana hak ini, emel kami di
        <strong>{{ $emel ?: '[emel belum ditetapkan]' }}</strong>. Kami akan membalas dalam
        tempoh <strong>21 hari</strong>.
    </p>

    <h2 class="mt-7 text-base font-bold">Keputusan automatik</h2>
    <p class="mt-2 text-sm">
        Dyno Ads <strong>tidak</strong> membuat keputusan automatik yang memberi kesan kepada anda.
        Iklan hanya dibuat dalam keadaan dijeda, dan tiada satu sen dibelanjakan sehingga anda
        sendiri menekan butang RUN. Jika kami menambah apa-apa automasi yang membelanjakan duit
        atau menjeda iklan dengan sendirinya, kami akan memberitahu anda terlebih dahulu.
    </p>

    <h2 class="mt-7 text-base font-bold">Keselamatan</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
        <li>Semua lalu lintas melalui HTTPS</li>
        <li>Kata laluan di-hash; token Meta disimpan berenkripsi</li>
        <li>Data setiap peniaga diasingkan di peringkat pangkalan data — anda tidak boleh melihat
            data peniaga lain, dan mereka tidak boleh melihat data anda</li>
        <li>Token tidak pernah ditulis ke dalam log atau mesej ralat</li>
    </ul>
    <p class="mt-2 text-sm">
        Jika berlaku pelanggaran data yang berisiko tinggi kepada anda, kami akan melaporkannya
        kepada Pesuruhjaya Perlindungan Data Peribadi dalam tempoh 72 jam dan memberitahu anda
        tanpa berlengah.
    </p>

    <h2 class="mt-7 text-base font-bold">Memadam data Facebook anda</h2>
    <p class="mt-2 text-sm">
        Anda boleh membuang Dyno Ads daripada tetapan Facebook anda dan memilih untuk memadam data.
        Facebook akan memberitahu kami, dan kami akan memadam token serta butiran akaun iklan dan
        Page anda, kemudian memberi anda pautan status untuk menyemaknya.
    </p>

    <h2 class="mt-7 text-base font-bold">Perubahan pada notis ini</h2>
    <p class="mt-2 text-sm">
        Jika kami mengubah tujuan penggunaan data anda, kami akan mengemas kini versi notis ini dan
        memberitahu anda sebelum perubahan itu berkuat kuasa.
    </p>

    <h2 class="mt-7 text-base font-bold">Aduan</h2>
    <p class="mt-2 text-sm">
        Hubungi kami dahulu di <strong>{{ $emel ?: '[emel belum ditetapkan]' }}</strong>.
        Jika anda tidak berpuas hati, anda boleh membuat aduan kepada Jabatan Perlindungan Data
        Peribadi Malaysia di <a href="https://www.pdp.gov.my" class="underline">pdp.gov.my</a>.
    </p>
</x-layouts.legal>
