<div>
    <h1 class="page-title">Pelanggan</h1>
    <p class="page-lead">
        Peniaga yang iklannya anda urus. Bajet dicaj terus ke kad mereka oleh Meta —
        anda tak pegang kad atau password Facebook mereka.
    </p>

    @unless ($showForm)
        <button type="button" wire:click="$set('showForm', true)" class="btn-primary mt-5 w-full">
            + Tambah pelanggan
        </button>
    @else
        <form wire:submit="tambah" class="card mt-5 space-y-4">
            <div>
                <label class="label" for="name">Nama kedai</label>
                <input id="name" type="text" class="field" wire:model="name" placeholder="Salon Kak Mah">
                @error('name') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="email">Emel pelanggan</label>
                <input id="email" type="email" class="field" wire:model="email" placeholder="kakmah@contoh.com">
                @error('email') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="phone">Telefon pelanggan</label>
                <input id="phone" type="tel" inputmode="tel" class="field" wire:model="phone" placeholder="012-345 6789">
                @error('phone') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="$set('showForm', false)" class="btn-ghost flex-1">Batal</button>
                <button type="submit" class="btn-primary flex-1">Simpan</button>
            </div>
        </form>
    @endunless

    <div class="mt-5 space-y-3">
        @forelse ($this->clients as $client)
            @php($conn = $client->fbConnection)
            <div class="card" wire:key="pelanggan-{{ $client->id }}">
                <p class="font-semibold">{{ $client->name }}</p>
                <p class="hint">{{ $conn ? $conn->label() : 'Belum sambung akaun Meta' }}</p>
                <p class="mt-1 text-sm">
                    @if (! $conn)
                        <span class="t-faint">Langkah 1: sambung Page & akaun iklan</span>
                    @elseif ($conn->needsConsent())
                        <span class="err">Tunggu persetujuan bertulis</span>
                    @else
                        <span>✓ Sedia untuk iklan</span>
                    @endif
                </p>
                <button type="button" wire:click="urus({{ $client->id }})" class="btn-ghost mt-3 w-full">
                    Urus {{ $client->name }} →
                </button>
            </div>
        @empty
            <p class="hint py-8 text-center">Belum ada pelanggan.</p>
        @endforelse
    </div>
</div>
