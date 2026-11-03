<div>
    <h1 class="page-title">Sambung akaun Meta</h1>
    <p class="page-lead">
        Iklan dibuat atas akaun iklan dan Page anda sendiri. Lead sampai terus ke WhatsApp anda.
    </p>

    @if ($connection)
        <div class="card mt-5">
            <p class="stat-label">Tersambung sekarang</p>
            <p class="mt-1 font-semibold">{{ $connection->label() }}</p>
            <p class="hint mt-1">Lead ke {{ \App\Support\Phone::pretty($connection->wa_phone) }}</p>
            @if ($connection->expiresSoon())
                <p class="err mt-2">Token ni akan luput {{ $connection->expires_at->diffForHumans() }}. Jana yang baharu sebelum iklan terhenti.</p>
            @endif
        </div>
    @endif

    @if ($berjaya)
        <div class="card mt-5">
            <p class="font-semibold">{{ $berjaya }}</p>
            @unless ($connection?->needsConsent())
                <a href="{{ route('ad-sets.create') }}" wire:navigate class="btn-primary mt-3 block text-center">
                    Buat iklan pertama
                </a>
            @endunless
        </div>
    @endif

    {{-- Pelanggan urus: persetujuan bertulis sebelum apa-apa iklan. Pemilik sahaja. --}}
    @if ($pemilik && $connection?->isManaged())
        <div class="card mt-5">
            @if ($connection->consented_at)
                <p class="stat-label">Persetujuan pelanggan</p>
                <p class="mt-1 font-semibold">✓ {{ $connection->consented_at->format('d M Y') }}</p>
                <p class="hint mt-1">{{ $connection->consent_note }}</p>
            @else
                <form wire:submit="rekodPersetujuan" class="space-y-3">
                    <p class="stat-label">Persetujuan pelanggan</p>
                    <p class="hint">
                        Iklan tak boleh dibuat selagi persetujuan bertulis belum direkod.
                        Bajet dicaj ke kad pelanggan sendiri.
                    </p>
                    <input type="text" class="field" wire:model="consentNote"
                           placeholder='Contoh: "Balas Setuju di WhatsApp 3 Nov"'>
                    @error('consentNote') <p class="err">{{ $message }}</p> @enderror
                    <button type="submit" class="btn-primary w-full">Rekod persetujuan</button>
                </form>
            @endif
        </div>
    @endif

    <form wire:submit="sambung" class="card mt-5 space-y-4">
        @if ($pemilik)
            <label class="flex items-start gap-3">
                <input type="checkbox" class="mt-1" wire:model.live="useOwnerToken">
                <span>
                    <span class="font-semibold">Guna token System User DynoPOS</span>
                    <span class="hint block">Untuk pelanggan yang dah beri Partner Access. Token kekal dalam .env, tak disalin.</span>
                </span>
            </label>
        @endif

        <div @if ($pemilik && $useOwnerToken) hidden @endif>
            <label class="label" for="token">Token akaun Meta</label>
            <textarea id="token" rows="3" class="field font-mono text-xs"
                      wire:model="token" placeholder="{{ $connection ? 'Isi hanya kalau nak tukar token' : 'Tampal token di sini' }}"></textarea>
            <p class="hint mt-1">Token disimpan berenkripsi. Ia tak pernah dipapar semula selepas disimpan.</p>
            @error('token') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="adAccountId">ID akaun iklan</label>
            <input id="adAccountId" type="text" class="field font-mono text-sm"
                   wire:model="adAccountId" placeholder="act_1234567890">
            @error('adAccountId') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="pageId">ID Page Facebook</label>
            <input id="pageId" type="text" inputmode="numeric" class="field font-mono text-sm"
                   wire:model="pageId" placeholder="1234567890">
            @error('pageId') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="waPhone">Nombor WhatsApp untuk terima lead</label>
            <input id="waPhone" type="tel" inputmode="tel" class="field"
                   wire:model="waPhone" placeholder="012-345 6789">
            <p class="hint mt-1">Kena nombor yang dah disambung ke Page ni di Meta.</p>
            @error('waPhone') <p class="err">{{ $message }}</p> @enderror
        </div>

        @if ($ralat)
            <p class="err">{{ $ralat }}</p>
        @endif

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="sambung">Sahkan dan simpan</span>
            <span wire:loading wire:target="sambung">Menyemak dengan Meta…</span>
        </button>

        <p class="hint text-center">
            Kami semak dengan Meta dulu sebelum simpan. Tiada iklan dibuat masa ni.
        </p>
    </form>

    {{-- Log keluar dipindah dari header ke sini, supaya header kekal ringkas. --}}
    <div class="mt-8 flex items-center justify-between gap-3 border-t pt-5" style="border-color: rgb(var(--line) / var(--line-alpha))">
        <span class="min-w-0 truncate text-sm t-muted">{{ auth()->user()->name }} &middot; {{ auth()->user()->email }}</span>
        <form method="POST" action="{{ route('keluar') }}" class="shrink-0">
            @csrf
            <button type="submit" class="btn-link">Log keluar</button>
        </form>
    </div>
</div>
