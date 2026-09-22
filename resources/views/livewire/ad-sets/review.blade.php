<div>
    <h1 class="text-[28px] font-extrabold leading-tight tracking-tight">Semak dulu</h1>
    <p class="mt-1.5 text-sm leading-relaxed t-muted">
        AI Nurin dah tulis caption. Ubah ikut suka anda — anda yang kenal pelanggan.
    </p>

    @if ($fallbackReason)
        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm leading-relaxed text-amber-800 dark:text-amber-200">
            <span class="font-semibold">AI tak menulis caption ni.</span>
            Ini ayat asas yang app susun dari input anda — sebab tu ia bunyi kosong.
            <span class="mt-1.5 block text-amber-700/80 dark:text-amber-200/70">{{ $fallbackReason }}</span>
        </div>
    @endif

    @if ($error)
        <div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-sm text-rose-700 dark:text-rose-200">{{ $error }}</div>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($adSet->variants as $variant)
            <div class="card overflow-hidden">
                <div class="relative">
                    <x-creative-media :variant="$variant" class="aspect-square w-full object-cover" />
                    <span class="absolute left-3 top-3 rounded-lg bg-black/65 px-2 py-1 text-[11px] font-bold backdrop-blur-sm">
                        Iklan #{{ $variant->position }}
                    </span>
                    @if ($variant->meta_campaign_id)
                        <span class="absolute right-3 top-3 rounded-lg bg-emerald-500/15 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 backdrop-blur-sm">
                            Sudah dibuat
                        </span>
                    @endif
                </div>
                <div class="p-4">
                    <textarea wire:model.blur="captions.{{ $variant->id }}" rows="5" class="field leading-relaxed"></textarea>

                    @if (! empty($risiko[$variant->id]))
                        <p class="mt-2 text-xs leading-relaxed text-amber-700 dark:text-amber-300">
                            Dakwaan <span class="font-semibold">{{ implode(', ', $risiko[$variant->id]) }}</span>
                            susah dibuktikan, dan Meta kadang menolaknya. Kalau ada harga, sebut harga —
                            angka lebih kuat daripada dakwaan.
                        </p>
                    @endif

                    <p class="hint">Butang iklan: Tekan WhatsApp untuk info lanjut</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-6 divide-hair p-4 text-sm">
        <div class="flex items-start justify-between gap-4 pb-2.5">
            <span class="t-muted">Kawasan</span>
            <span class="text-right font-semibold">{{ $adSet->regionLabel() }}</span>
        </div>
        <div class="flex justify-between py-2.5">
            <span class="t-muted">WhatsApp</span>
            <span class="font-semibold">{{ $adSet->phone }}</span>
        </div>
        <div class="flex justify-between py-2.5">
            <span class="t-muted">Bajet sehari</span>
            <span class="font-semibold">RM{{ intdiv($adSet->daily_budget_sen, 100) }} &times; {{ $adSet->variants->count() }} iklan</span>
        </div>
        <div class="flex items-center justify-between pt-2.5">
            <span class="t-muted">Jumlah sehari</span>
            <span class="bg-dyno-gradient bg-clip-text text-lg font-extrabold text-transparent">
                RM{{ intdiv($adSet->totalDailyBudgetSen(), 100) }}
            </span>
        </div>
    </div>

    <button type="button" wire:click="regenerate" class="btn-ghost mt-4"
            wire:loading.attr="disabled" wire:target="regenerate">
        <span wire:loading.remove wire:target="regenerate">Minta caption lain</span>
        <span wire:loading wire:target="regenerate">AI Nurin sedang tulis…</span>
    </button>

    <button type="button" wire:click="approve" class="btn-primary mt-3"
            wire:loading.attr="disabled" wire:target="approve">
        <span wire:loading.remove wire:target="approve">Sahkan &amp; buat iklan</span>
        <span wire:loading wire:target="approve">Sedang buat di Meta…</span>
    </button>

    <p class="mt-3 text-center text-xs leading-relaxed t-faint">
        Iklan dibuat dalam keadaan PAUSED.<br>Tiada duit dibelanjakan sampai anda tekan RUN.
    </p>
</div>
