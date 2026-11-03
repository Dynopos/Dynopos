<div>
    <h1 class="page-title">Lancarkan</h1>
    <p class="page-lead">
        Semua iklan sedang PAUSED. Tekan RUN bila anda dah sedia.
    </p>

    @if ($error)
        <div class="mt-4 note note-err text-sm">{{ $error }}</div>
    @endif
    @if ($notice)
        <div class="mt-4 note note-ok text-sm">{{ $notice }}</div>
    @endif

    <div class="card mt-5 p-4">
        <div class="flex items-center justify-between">
            <span class="text-xs t-muted">Belanja sehari kalau semua hidup</span>
            <span class="text-xl font-extrabold tabular-nums t-primary">
                RM{{ intdiv($adSet->totalDailyBudgetSen(), 100) }}
            </span>
        </div>
        <div class="mt-3 flex items-start justify-between gap-4 border-t pt-3" style="border-color: rgb(var(--line) / var(--line-alpha))">
            <span class="text-xs t-muted">Kawasan</span>
            <span class="text-right text-xs font-semibold">{{ $adSet->regionLabel() }}</span>
        </div>
    </div>

    <button type="button" wire:click="runAll"
            wire:confirm="Selepas ini duit mula dibelanjakan. Teruskan?"
            class="btn-primary mt-4 py-5 text-lg font-extrabold tracking-wide"
            wire:loading.attr="disabled" wire:target="runAll">
        <span wire:loading.remove wire:target="runAll">RUN SEMUA</span>
        <span wire:loading wire:target="runAll">Sedang hidupkan…</span>
    </button>

    <div class="mt-6 space-y-3">
        @foreach ($adSet->variants as $variant)
            @php
                $badge = match ($variant->status) {
                    'active' => ['pill-ok', 'HIDUP'],
                    'failed' => ['pill-err', 'GAGAL'],
                    default => ['pill-idle', 'PAUSED'],
                };
            @endphp
            <div class="card flex gap-3 p-3" wire:key="variant-{{ $variant->id }}">
                <x-creative-media :variant="$variant"
                    class="h-[72px] w-[72px] shrink-0 rounded-2xl object-cover" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold">Iklan #{{ $variant->position }}</span>
                        <span class="pill {{ $badge[0] }} tracking-wide">{{ $badge[1] }}</span>
                    </div>
                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed t-muted">{{ $variant->caption }}</p>

                    @if ($variant->last_error)
                        <p class="err">{{ $variant->last_error }}</p>
                    @endif

                    @if ($variant->meta_ad_id)
                        <div class="mt-2.5">
                            @if ($variant->isLive())
                                <button type="button" wire:click="pauseVariant({{ $variant->id }})"
                                        class="chip px-3 py-1.5 text-xs">Pause</button>
                            @else
                                <button type="button" wire:click="runVariant({{ $variant->id }})"
                                        class="chip chip-on px-3 py-1.5 text-xs">Hidupkan</button>
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
