<div>
    <h1 class="text-2xl font-extrabold tracking-tight">Masuk</h1>
    <p class="hint mt-1">Iklan anda, akaun anda sendiri.</p>

    <form wire:submit="masuk" class="card mt-5 space-y-4">
        <div>
            <label class="label" for="email">Emel</label>
            <input id="email" type="email" inputmode="email" autocomplete="email"
                   class="field" wire:model="email" placeholder="nama@contoh.com">
            @error('email') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="password">Kata laluan</label>
            <input id="password" type="password" autocomplete="current-password"
                   class="field" wire:model="password">
            @error('password') <p class="err">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2.5 text-sm t-muted">
            <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded">
            Ingat saya
        </label>

        <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="masuk">Masuk</span>
            <span wire:loading wire:target="masuk">Sekejap…</span>
        </button>
    </form>

    <p class="mt-5 text-center text-sm t-muted">
        Belum ada akaun?
        <a href="{{ route('daftar') }}" wire:navigate class="font-semibold underline">Daftar</a>
    </p>
</div>
