<div>
    <h1 class="text-2xl font-extrabold tracking-tight">Sambung akaun Meta</h1>
    <p class="hint mt-1">
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
            <a href="{{ route('ad-sets.create') }}" wire:navigate class="btn-primary mt-3 block text-center">
                Buat iklan pertama
            </a>
        </div>
    @endif

    <form wire:submit="sambung" class="card mt-5 space-y-4">
        <div>
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
</div>
