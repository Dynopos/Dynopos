<div>
    <h1 class="text-[28px] font-extrabold leading-tight tracking-tight">Betul ke ni bisnes anda?</h1>
    <p class="mt-1.5 text-sm leading-relaxed t-muted">
        AI Amir baca apa yang anda tulis tentang <span class="font-semibold">{{ $business->name }}</span>.
        Sahkan dulu — AI Nurin hanya tulis caption ikut apa yang anda sahkan di sini.
    </p>

    @if ($error)
        <div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-sm text-rose-700 dark:text-rose-200">{{ $error }}</div>
    @endif

    @if (($analysis['source'] ?? null) === 'manual')
        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm leading-relaxed text-amber-800 dark:text-amber-200">
            <span class="font-semibold">AI tak dapat menganalisis.</span>
            Medan di bawah diisi dari tulisan anda sendiri. Semak dan betulkan sebelum sahkan.
            @if (filled($analysis['reason'] ?? null))
                <span class="mt-1.5 block text-amber-700/80 dark:text-amber-200/70">{{ $analysis['reason'] }}</span>
            @endif
        </div>
    @elseif (($analysis['confidence'] ?? null) === 'rendah')
        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm leading-relaxed text-amber-800 dark:text-amber-200">
            <span class="font-semibold">AI tak berapa pasti.</span>
            {{ $analysis['question'] ?: 'Terangkan bisnes anda dengan lebih jelas di bawah.' }}
        </div>
    @endif

    <form wire:submit="confirm" class="mt-6 space-y-5">
        <div>
            <label class="label" for="field">Bidang</label>
            <textarea id="field" wire:model="field" rows="2" class="field mt-2 leading-relaxed"></textarea>
            @error('field') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="label" for="summary">Apa bisnes ni buat</label>
            <textarea id="summary" wire:model="summary" rows="3" class="field mt-2 leading-relaxed"></textarea>
        </div>

        @foreach (['offers' => 'Produk / servis', 'customers' => 'Pelanggan', 'angles' => 'Sudut jualan'] as $key => $label)
            @if (! empty($analysis[$key]))
                <div>
                    <span class="label">{{ $label }}</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($analysis[$key] as $item)
                            <span class="chip">{{ $item }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        <div>
            <label class="label" for="avoid">Jangan sebut langsung</label>
            <input id="avoid" type="text" wire:model="avoid" class="field mt-2" placeholder="Contoh: oksigen, tangki gas, lif bangunan">
            <p class="hint">Pisahkan dengan koma. Caption yang sebut perkataan ni ditolak oleh app, bukan sekadar diminta.</p>
        </div>

        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="confirm">
            <span wire:loading.remove wire:target="confirm">Betul, sahkan</span>
            <span wire:loading wire:target="confirm">Menyimpan…</span>
        </button>
    </form>

    <div class="card mt-6 p-4">
        <label class="label" for="correction">AI silap? Betulkan di sini</label>
        <textarea id="correction" wire:model="correction" rows="2" class="field mt-2"
                  placeholder="Contoh: kami sewa skylift, bukan jual gas oksigen."></textarea>
        @error('correction') <p class="err">{{ $message }}</p> @enderror

        @if ($left > 0)
            <button type="button" wire:click="reanalyse" class="btn-ghost mt-3"
                    wire:loading.attr="disabled" wire:target="reanalyse">
                <span wire:loading.remove wire:target="reanalyse">Analisis semula</span>
                <span wire:loading wire:target="reanalyse">AI Amir sedang baca…</span>
            </button>
            <p class="hint">Boleh analisis semula {{ $left }} kali lagi untuk bisnes ni.</p>
        @else
            <p class="hint">Had analisis semula sudah habis. Betulkan medan di atas sendiri, kemudian sahkan.</p>
        @endif
    </div>
</div>
