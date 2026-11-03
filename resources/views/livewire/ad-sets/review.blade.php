<div>
    <h1 class="page-title">Semak dulu</h1>
    <p class="page-lead">
        AI Nurin dah tulis caption. Ubah ikut suka anda — anda yang kenal pelanggan.
    </p>

    @if ($fallbackReason)
        <div class="mt-4 note note-warn text-sm">
            <span class="font-semibold">AI tak menulis caption ni.</span>
            Ini ayat asas yang app susun dari input anda — sebab tu ia bunyi kosong.
            <span class="note-sub">{{ $fallbackReason }}</span>
        </div>
    @endif

    @if ($error)
        <div class="mt-4 note note-err text-sm">{{ $error }}</div>
    @endif

    @if ($adSet->business)
        <a href="{{ route('businesses.confirm', ['business' => $adSet->business, 'set' => $adSet->id]) }}" wire:navigate
           class="card mt-5 flex items-center gap-3 p-4">
            <span class="min-w-0 flex-1">
                <span class="block text-xs t-muted">Caption ditulis untuk</span>
                <span class="block text-sm font-bold">{{ $adSet->business->name }}</span>
                <span class="block text-xs leading-relaxed t-muted">{{ $adSet->business->field() }}</span>
            </span>
            <span class="text-xs font-bold t-primary">Ubah &rsaquo;</span>
        </a>
    @else
        <div class="mt-4 note note-warn text-sm">
            <span class="font-semibold">Set iklan ni belum ada profil bisnes.</span>
            AI Nurin tak tulis caption tanpa tahu bisnes apa.
            @if ($confirmedBusinesses->isNotEmpty())
                Pilih bisnesnya:
                <span class="mt-2 flex flex-wrap gap-2">
                    @foreach ($confirmedBusinesses as $business)
                        <button type="button" wire:click="attachBusiness({{ $business->id }})" class="chip">{{ $business->name }}</button>
                    @endforeach
                </span>
            @else
                <a href="{{ route('ad-sets.create') }}" wire:navigate class="underline">Buat iklan baru</a> untuk tambah bisnes dulu.
            @endif
        </div>
    @endif

    <div class="mt-6 space-y-5">
        @foreach ($adSet->variants as $variant)
            <div class="card overflow-hidden">
                <div class="relative">
                    <x-creative-media :variant="$variant" class="aspect-square w-full object-cover" />
                    <span class="pill absolute left-3 top-3 bg-black/60 text-white backdrop-blur-sm">
                        Iklan #{{ $variant->position }}
                    </span>
                    @if ($variant->meta_campaign_id)
                        <span class="pill pill-ok absolute right-3 top-3">
                            Sudah dibuat
                        </span>
                    @endif
                </div>
                <div class="p-4">
                    <textarea wire:model.blur="captions.{{ $variant->id }}" rows="5" class="field leading-relaxed"></textarea>

                    @if (! empty($risiko[$variant->id]))
                        <p class="note note-warn mt-2 text-xs">
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
            <span class="text-lg font-extrabold tabular-nums t-primary">
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
