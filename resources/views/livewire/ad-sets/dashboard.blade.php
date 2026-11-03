<div>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="page-title">Prestasi</h1>
            <p class="mt-1 truncate text-sm t-muted">{{ $adSet->offer }}</p>
        </div>
        <button type="button" wire:click="refreshMetrics" wire:loading.attr="disabled" wire:target="refreshMetrics"
                class="chip mt-1 shrink-0 text-xs">
            <span wire:loading.remove wire:target="refreshMetrics">Refresh</span>
            <span wire:loading wire:target="refreshMetrics">Menarik…</span>
        </button>
    </div>

    @if ($notice)
        <div class="mt-4 note note-ok text-sm">{{ $notice }}</div>
    @endif

    <div class="mt-5 grid grid-cols-3 gap-2.5">
        <div class="stat">
            <div class="stat-label">Belanja</div>
            <div class="stat-value">RM{{ number_format($totalSpendSen / 100, 0) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Lead</div>
            <div class="stat-value t-primary">{{ $totalLeads }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Kos/lead</div>
            <div class="stat-value">
                {{ $totalLeads > 0 ? 'RM'.number_format($totalSpendSen / $totalLeads / 100, 0) : '—' }}
            </div>
        </div>
    </div>

    @php $best = $variants->filter(fn ($v) => $v->leads() > 0)->sortBy(fn ($v) => $v->costPerLeadSen())->first(); @endphp

    <h2 class="stat-label mt-8">Setiap iklan</h2>
    <div class="mt-3 space-y-2.5">
        @foreach ($variants as $variant)
            @php $cpl = $variant->costPerLeadSen(); @endphp
            <div class="card flex gap-3 p-3" wire:key="variant-{{ $variant->id }}"
                 @style(['outline: 2px solid rgb(var(--primary)); outline-offset: -2px' => $best && $best->is($variant)])>
                <x-creative-media :variant="$variant"
                    class="h-16 w-16 shrink-0 rounded-2xl object-cover" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold">Iklan #{{ $variant->position }}</span>
                        @if ($best && $best->is($variant))
                            <span class="pill" style="background-color: rgb(var(--primary)); color: rgb(var(--on-primary))">TERBAIK</span>
                        @endif
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <div class="text-[10px] uppercase tracking-wide t-faint">Belanja</div>
                            <div class="mt-0.5 font-semibold">RM{{ number_format($variant->spendSen() / 100, 0) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wide t-faint">Lead</div>
                            <div class="mt-0.5 font-semibold">{{ $variant->leads() }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-wide t-faint">Kos/lead</div>
                            <div class="mt-0.5 font-semibold">{{ $cpl !== null ? 'RM'.number_format($cpl / 100, 0) : '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="stat-label mt-8">Apa app dah buat</h2>
    <ol class="mt-3 space-y-2">
        @forelse ($actions as $action)
            @php
                $tone = match ($action->result) {
                    'ok' => 'pill-ok',
                    'failed' => 'pill-err',
                    default => 'pill-idle',
                };
            @endphp
            <li class="card flex gap-3 p-3 text-xs">
                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $tone }}" style="background-color: currentColor"></span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold leading-relaxed">{{ $action->reason }}</p>
                    @if ($action->message)
                        <p class="mt-1 leading-relaxed t-muted">{{ $action->message }}</p>
                    @endif
                    <p class="mt-1 t-faint">{{ $action->created_at->timezone(config('app.timezone'))->format('d M, h:i A') }}</p>
                </div>
            </li>
        @empty
            <li class="card p-5 text-center text-xs t-faint">
                Belum ada tindakan direkod.
            </li>
        @endforelse
    </ol>

    <a href="{{ route('ad-sets.run', $adSet) }}" wire:navigate class="btn-ghost mt-6 block text-center">
        Kembali ke kawalan iklan
    </a>
</div>
