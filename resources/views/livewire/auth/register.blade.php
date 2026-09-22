<div>
    <h1 class="text-2xl font-extrabold tracking-tight">Daftar</h1>
    <p class="hint mt-1">Percuma untuk mula. Tiada duit dibelanjakan sebelum anda tekan RUN.</p>

    <form wire:submit="daftar" class="card mt-5 space-y-4">
        <div>
            <label class="label" for="name">Nama</label>
            <input id="name" type="text" autocomplete="name" class="field"
                   wire:model="name" placeholder="Nama anda">
            @error('name') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="email">Emel</label>
            <input id="email" type="email" inputmode="email" autocomplete="email"
                   class="field" wire:model="email" placeholder="nama@contoh.com">
            @error('email') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="phone">Nombor WhatsApp</label>
            <input id="phone" type="tel" inputmode="tel" autocomplete="tel"
                   class="field" wire:model="phone" placeholder="012-345 6789">
            @error('phone') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="password">Kata laluan</label>
            <input id="password" type="password" autocomplete="new-password"
                   class="field" wire:model="password">
            <p class="hint mt-1">Sekurang-kurangnya 8 aksara.</p>
            @error('password') <p class="err">{{ $message }}</p> @enderror
        </div>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="daftar">Daftar</span>
            <span wire:loading wire:target="daftar">Sekejap…</span>
        </button>
    </form>

    <p class="mt-5 text-center text-sm t-muted">
        Dah ada akaun?
        <a href="{{ route('masuk') }}" wire:navigate class="font-semibold underline">Masuk</a>
    </p>
</div>
