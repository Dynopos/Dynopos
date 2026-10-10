<x-layouts.legal title="Status pemadaman data">
    @if (! $record)
        <p class="mt-4">
            Kod pengesahan ini tidak dijumpai. Semak semula pautan yang anda terima,
            atau hubungi kami.
        </p>
    @else
        <div class="card mt-5">
            <p class="stat-label">Status</p>
            <p class="mt-1 text-lg font-bold">
                @switch($record->status)
                    @case('completed') Selesai — data Facebook anda telah dipadam @break
                    @case('no_match') Selesai — tiada data Facebook untuk akaun anda @break
                    @default Sedang diproses
                @endswitch
            </p>

            @if ($record->completed_at)
                <p class="hint mt-1">{{ $record->completed_at->timezone('Asia/Kuala_Lumpur')->format('d M Y, g:ia') }}</p>
            @endif

            <p class="mt-3 text-sm">{{ $record->summary }}</p>

            <p class="hint mt-3">
                Kod pengesahan: <span class="font-mono">{{ $record->confirmation_code }}</span>
            </p>
        </div>

        @if ($record->status === 'completed')
            <h2 class="mt-7 text-base font-bold">Apa yang masih ada</h2>
            <p class="mt-2 text-sm">
                Set iklan, caption dan angka prestasi anda <strong>tidak</strong> dipadam. Itu rekod
                perniagaan anda sendiri, bukan data yang kami ambil dari Facebook, dan ia mungkin
                anda perlukan untuk rujukan atau cukai.
            </p>
            <p class="mt-2 text-sm">
                Nak padam akaun Dyno Ads sepenuhnya termasuk semua itu? Hantar permintaan ke
                <strong>{{ config('dynoads.company.email') ?: 'alamat emel kami' }}</strong>.
                Kami akan melayannya dalam tempoh 21 hari seperti yang dikehendaki Akta 709.
            </p>
        @endif
    @endif
</x-layouts.legal>
