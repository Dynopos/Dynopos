<div>
    <h1 class="text-[28px] font-extrabold leading-tight tracking-tight">Lancarkan</h1>
    <p class="mt-1.5 text-sm leading-relaxed t-muted">
        Semua iklan sedang PAUSED. Tekan RUN bila anda dah sedia.
    </p>

    @if ($error)
        <div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-sm text-rose-700 dark:text-rose-200">{{ $error }}</div>
    @endif
    @if ($notice)
        <div class="mt-4 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-sm text-emerald-700 dark:text-emerald-200">{{ $notice }}</div>
    @endif

    <div class="card mt-5 p-4">
        <div class="flex items-center justify-between">
            <span class="text-xs t-muted">Belanja sehari kalau semua hidup</span>
            <span class="bg-dyno-gradient bg-clip-text text-xl font-extrabold text-transparent">
                RM{{ intdiv($adSet->totalDailyBudgetSen(), 100) }}
            </span>
        </div>
        <div class="mt-2.5 flex items-start justify-between gap-4 border-t border-current/10 pt-2.5">
            <span class="text-xs t-muted">Kawasan</span>
            <span class="text-right text-xs font-semibold">{{ $adSet->regionLabel() }}</span>
        </div>
    </div>

    <button type="button" wire:click="runAll"
            wire:confirm="Selepas ini duit mula dibelanjakan. Teruskan?"
            class="mt-4 w-full rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 px-4 py-5 text-lg font-extrabold text-ink-950 shadow-[0_6px_24px_-8px_rgba(16,185,129,0.6)] transition active:scale-[0.99] disabled:opacity-50"
            wire:loading.attr="disabled" wire:target="runAll">
        <span wire:loading.remove wire:target="runAll">RUN SEMUA</span>
        <span wire:loading wire:target="runAll">Sedang hidupkan…</span>
    </button>

    <div class="mt-6 space-y-3">
        @foreach ($adSet->variants as $variant)
            @php
                $badge = match ($variant->status) {
                    'active' => ['bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'HIDUP'],
                    'failed' => ['bg-rose-500/15 text-rose-700 dark:text-rose-300', 'GAGAL'],
                    default => ['bg-current/10 t-muted', 'PAUSED'],
                };
            @endphp
            <div class="card flex gap-3 p-3">
                <x-creative-media :variant="$variant"
                    class="h-[72px] w-[72px] shrink-0 rounded-xl border border-current/10 object-cover" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold">Iklan #{{ $variant->position }}</span>
                        <span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide {{ $badge[0] }}">{{ $badge[1] }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed t-muted">{{ $variant->caption }}</p>

                    @if ($variant->last_error)
                        <p class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ $variant->last_error }}</p>
                    @endif

                    @if ($variant->meta_ad_id)
                        <div class="mt-2.5">
                            @if ($variant->isLive())
                                <button type="button" wire:click="pauseVariant({{ $variant->id }})"
                                        class="rounded-lg border border-current/15 px-3 py-1.5 text-xs font-semibold">Pause</button>
                            @else
                                <button type="button" wire:click="runVariant({{ $variant->id }})"
                                        class="rounded-lg border border-emerald-500/30 bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Hidupkan</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <a href="{{ route('ad-sets.dashboard', $adSet) }}" wire:navigate class="btn-ghost mt-6 block text-center">
        Lihat prestasi
    </a>
</div>
