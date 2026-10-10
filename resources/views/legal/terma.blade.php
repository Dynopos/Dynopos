@php
    $syarikat = config('dynoads.company');
    $emel = $syarikat['email'] ?: '[emel belum ditetapkan]';
@endphp

<x-layouts.legal title="Terma Perkhidmatan">
    <p class="hint mt-2">Dikemas kini {{ config('dynoads.privacy.version') }}</p>

    <h2 class="mt-7 text-base font-bold">Apa perkhidmatan ini</h2>
    <p class="mt-2 text-sm">
        Dyno Ads membantu anda membuat iklan Facebook dan Instagram yang menghantar pelanggan ke
        WhatsApp anda. Anda bekalkan gambar atau video; kami tulis caption, susun iklan mengikut
        tetapan yang terbukti, dan bandingkan mana yang paling murah kosnya.
    </p>

    <h2 class="mt-7 text-base font-bold">Akaun dan duit iklan anda sendiri</h2>
    <p class="mt-2 text-sm">
        Iklan dijalankan atas <strong>akaun iklan Meta anda sendiri</strong>, dengan kaedah
        pembayaran anda sendiri. <strong>Meta mengebil anda terus.</strong> Kami tidak memegang,
        memproses atau mengambil sebarang bahagian daripada duit iklan anda.
    </p>
    <p class="mt-2 text-sm">
        Anda bertanggungjawab ke atas kos iklan yang dibelanjakan melalui akaun anda, termasuk
        iklan yang dilancarkan melalui perkhidmatan ini.
    </p>

    <h2 class="mt-7 text-base font-bold">Tiada duit dibelanjakan tanpa kebenaran anda</h2>
    <p class="mt-2 text-sm">
        Setiap campaign, ad set dan iklan dibuat dalam keadaan <strong>dijeda</strong>. Tiada satu
        sen dibelanjakan sehingga anda sendiri menekan butang RUN. Kami tidak menghidupkan iklan
        bagi pihak anda.
    </p>

    <h2 class="mt-7 text-base font-bold">Kami tidak menjamin hasil</h2>
    <p class="mt-2 text-sm">
        Perkhidmatan ini membantu anda beriklan dengan lebih teratur. Ia <strong>tidak</strong>
        menjamin lead, jualan, atau kos per lead tertentu. Prestasi iklan bergantung pada produk
        anda, harga, kawasan, persaingan dan algoritma Meta — perkara yang berada di luar kawalan
        kami.
    </p>

    <h2 class="mt-7 text-base font-bold">Tanggungjawab anda</h2>
    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
        <li>Anda memiliki hak ke atas gambar, video dan teks yang anda muat naik</li>
        <li>Kandungan anda mematuhi polisi pengiklanan Meta dan undang-undang Malaysia</li>
        <li>Akaun Meta yang anda sambungkan adalah akaun yang anda berhak gunakan</li>
        <li>Anda menjaga kerahsiaan kata laluan akaun Dyno Ads anda</li>
    </ul>
    <p class="mt-2 text-sm">
        Kami boleh menggantung akaun yang melanggar perkara di atas, atau yang digunakan untuk
        menipu, mengganggu, atau memintas sekatan Meta.
    </p>

    <h2 class="mt-7 text-base font-bold">Kami bergantung pada Meta</h2>
    <p class="mt-2 text-sm">
        Perkhidmatan ini bergantung pada API Meta. Jika Meta menukar peraturan, menolak iklan anda,
        menyekat akaun anda, atau API mereka tidak berfungsi, perkhidmatan ini akan terjejas sama.
        Itu di luar kawalan kami.
    </p>

    <h2 class="mt-7 text-base font-bold">Had tanggungan</h2>
    <p class="mt-2 text-sm">
        Setakat yang dibenarkan undang-undang, tanggungan kami kepada anda tidak melebihi jumlah
        yuran langganan yang anda bayar kepada kami dalam tempoh tiga bulan sebelumnya. Kami tidak
        bertanggungjawab ke atas kehilangan keuntungan, kehilangan peluang, atau kos iklan yang
        anda belanjakan sendiri.
    </p>
    <p class="mt-2 text-sm">
        Tiada apa dalam terma ini mengecualikan tanggungan yang tidak boleh dikecualikan di bawah
        undang-undang Malaysia.
    </p>

    <h2 class="mt-7 text-base font-bold">Menamatkan akaun</h2>
    <p class="mt-2 text-sm">
        Anda boleh berhenti bila-bila masa. Emel kami di <strong>{{ $emel }}</strong> untuk menutup
        akaun. Data anda dikendalikan mengikut
        <a href="{{ route('privasi') }}" class="underline">Notis Privasi</a> kami.
    </p>
    <p class="mt-2 text-sm">
        Iklan yang sedang berjalan di akaun Meta anda <strong>tidak</strong> berhenti sendiri
        apabila anda menutup akaun Dyno Ads. Jedakan atau padamkannya di Ads Manager.
    </p>

    <h2 class="mt-7 text-base font-bold">Perubahan</h2>
    <p class="mt-2 text-sm">
        Kami akan memberitahu anda sebelum sebarang perubahan penting pada terma ini berkuat kuasa.
    </p>

    <h2 class="mt-7 text-base font-bold">Undang-undang</h2>
    <p class="mt-2 text-sm">
        Terma ini tertakluk kepada undang-undang Malaysia.
    </p>

    <h2 class="mt-7 text-base font-bold">Hubungi kami</h2>
    <p class="mt-2 text-sm">
        {{ $syarikat['name'] ?: '[Nama syarikat belum ditetapkan]' }}
        @if ($syarikat['registration_no']) ({{ $syarikat['registration_no'] }}) @endif<br>
        {{ $emel }}
    </p>
</x-layouts.legal>
