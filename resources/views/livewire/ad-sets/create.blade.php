@php($totalSteps = count(\App\Livewire\AdSets\Create::STEP_TITLES))
<div>
    {{-- Kemajuan: langkah yang dah lepas dan semasa diwarnakan. --}}
    <div class="steps" aria-hidden="true">
        @foreach (\App\Livewire\AdSets\Create::STEP_TITLES as $n => $stepTitle)
            <span @class(['on' => $n <= $step])></span>
        @endforeach
    </div>
    <p class="mt-3 text-[11px] font-bold uppercase tracking-[0.12em] t-primary">
        Langkah {{ $step }} daripada {{ $totalSteps }} &middot; {{ \App\Livewire\AdSets\Create::STEP_TITLES[$step] }}
    </p>

    <form wire:submit="{{ $step < $totalSteps ? 'next' : 'save' }}" class="mt-2">

        {{-- 1 · Media --}}
        @if ($step === 1)
            <h1 class="page-title">Pilih gambar atau video</h1>
            <p class="page-lead">Satu media jadi satu iklan. Kita bandingkan mana yang paling murah kos setiap lead.</p>

            <div class="mt-6 grid grid-cols-3 gap-2.5">
                @foreach ($media as $i => $file)
                    <div class="relative" wire:key="media-{{ $i }}">
                        @if ($this->isVideo($i))
                            <video src="{{ $file->temporaryUrl() }}" muted playsinline preload="metadata"
                                   class="aspect-square w-full rounded-2xl object-cover"></video>
                            <span class="pill absolute bottom-1.5 left-1.5 bg-black/60 text-white">&#9654; Video</span>
                        @else
                            <img src="{{ $file->temporaryUrl() }}" alt="" class="aspect-square w-full rounded-2xl object-cover">
                        @endif

                        <button type="button" wire:click="removeMedia({{ $i }})" aria-label="Buang media {{ $i + 1 }}"
                                class="absolute -right-1.5 -top-1.5 flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold shadow"
                                style="background-color: rgb(var(--sheet)); color: rgb(var(--text))">&times;</button>
                    </div>
                @endforeach

                @if ($this->creativeCount() < config('dynoads.creative.max_creatives'))
                    <label class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl text-center text-xs font-bold"
                           style="background-color: rgb(var(--primary-soft)); color: rgb(var(--primary-ink))">
                        <span wire:loading.remove wire:target="upload" class="text-2xl font-medium leading-none">+</span>
                        <span wire:loading.remove wire:target="upload">{{ $media ? 'Tambah lagi' : 'Tambah' }}</span>
                        <span wire:loading wire:target="upload">Memuat naik…</span>
                        <input type="file" wire:model="upload" multiple
                               accept="{{ \App\Support\Media::acceptAttribute() }}" class="hidden">
                    </label>
                @endif
            </div>

            <p class="hint">
                {{ $this->creativeCount() }} daripada {{ config('dynoads.creative.max_creatives') }}.
                Gambar dipotong persegi; video dihantar seadanya. Had {{ $this->uploadLimit() }} setiap fail.
            </p>
            @error('media') <p class="err">{{ $message }}</p> @enderror
            @error('upload.*') <p class="err">{{ $message }}</p> @enderror

            @if ($this->showsServerNotes() && $this->uploadLimitTooTight())
                <div class="note note-warn mt-3 text-xs">
                    <strong>Nota pemilik:</strong> pelayan hadkan fail kepada {{ $this->uploadLimit() }}. Gambar telefon selalunya lebih besar.
                    <span class="note-sub">Naikkan <code>upload_max_filesize</code> dan <code>post_max_size</code> dalam Forge &rarr; server &rarr; PHP.</span>
                </div>
            @elseif ($this->showsServerNotes() && $this->videoLimitTooTight())
                <div class="note note-warn mt-3 text-xs">
                    <strong>Nota pemilik:</strong> had {{ $this->uploadLimit() }} terlalu kecil untuk video telefon.
                    <span class="note-sub">Naikkan <code>upload_max_filesize</code> dan <code>post_max_size</code> dalam Forge &rarr; server &rarr; PHP.</span>
                </div>
            @endif

            <a href="{{ route('ad-sets.existing-posts') }}" wire:navigate class="card mt-6 flex items-center gap-3 p-4">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold">Guna posting yang dah ada</span>
                    <span class="block text-xs leading-relaxed t-muted">Pilih dari Page anda. Like dan komen terkumpul pada posting asal.</span>
                </span>
                <span class="t-primary text-lg font-bold" aria-hidden="true">&rsaquo;</span>
            </a>
        @endif

        {{-- 2 · Bisnes --}}
        @if ($step === 2)
            <h1 class="page-title">Bisnes apa yang nak diiklankan?</h1>
            <p class="page-lead">AI Amir faham bisnes anda dulu. Anda sahkan, baru AI Nurin tulis caption.</p>

            @if ($businesses->isNotEmpty())
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach ($businesses as $business)
                        <button type="button" wire:click="chooseBusiness({{ $business->id }})"
                                class="chip {{ $businessId === $business->id ? 'chip-on' : '' }}">
                            {{ $business->name }}
                        </button>
                    @endforeach
                    <button type="button" wire:click="chooseBusiness"
                            class="chip {{ $businessId === null ? 'chip-on' : '' }}">
                        + Bisnes lain
                    </button>
                </div>
            @endif

            @if ($businessId === null)
                <div class="mt-5 space-y-2.5">
                    <label class="label" for="businessName">Nama bisnes</label>
                    <input id="businessName" type="text" wire:model="businessName" class="field"
                           placeholder="Contoh: Ali Skylift Rental">
                    @error('businessName') <p class="err">{{ $message }}</p> @enderror

                    <label class="label pt-2" for="businessDescription">Apa bisnes ni buat?</label>
                    <textarea id="businessDescription" wire:model="businessDescription" rows="4" class="field"
                              placeholder="Contoh: sewa skylift dan lori kren untuk kerja tempat tinggi — pasang papan tanda, cat bangunan, potong pokok. Kawasan Kelantan."></textarea>
                    @error('businessDescription') <p class="err">{{ $message }}</p> @enderror
                </div>
            @else
                @php($chosen = $businesses->firstWhere('id', $businessId))
                @if ($chosen)
                    <div class="card mt-5 p-4">
                        <p class="text-sm font-bold">{{ $chosen->name }}</p>
                        <p class="mt-1 text-xs leading-relaxed t-muted">
                            {{ $chosen->hasConfirmedAnalysis() ? 'Bidang: '.$chosen->field() : 'Belum disahkan. Anda akan semak analisis bisnes ni dulu sebelum caption ditulis.' }}
                        </p>
                    </div>
                @endif
            @endif
            @error('businessId') <p class="err">{{ $message }}</p> @enderror
        @endif

        {{-- 3 · Mesej --}}
        @if ($step === 3)
            <h1 class="page-title">Apa yang iklan ni nak cakap?</h1>
            <p class="page-lead">Dua ayat pendek. AI Nurin guna ni untuk tulis caption setiap iklan.</p>

            <div class="mt-6 space-y-2.5">
                <label class="label" for="problem">Masalah pelanggan anda</label>
                <textarea id="problem" wire:model="problem" rows="2" class="field"
                          placeholder="Contoh: kira duit lambat waktu peak hour"></textarea>
                @error('problem') <p class="err">{{ $message }}</p> @enderror

                <label class="label pt-2" for="offer">Apa yang anda tawarkan</label>
                <textarea id="offer" wire:model="offer" rows="2" class="field"
                          placeholder="Contoh: sistem POS fullset, pasang di kedai"></textarea>
                @error('offer') <p class="err">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- 4 · Kawasan dan bajet --}}
        @if ($step === 4)
            <h1 class="page-title">Siapa nampak, dan berapa sehari?</h1>
            <p class="page-lead">Anda boleh ubah lepas ni. Tiada duit dibelanjakan sebelum anda tekan RUN.</p>

            <p class="label mt-6">Kawasan</p>
            <div class="mt-2.5 flex flex-wrap gap-2">
                <button type="button" wire:click="clearRegions" class="chip {{ $regionKeys === [] ? 'chip-on' : '' }}">
                    Seluruh Malaysia
                </button>
                @foreach ($regions as $region)
                    <button type="button" wire:click="toggleRegion('{{ $region['key'] }}')"
                            class="chip {{ in_array($region['key'], $regionKeys, true) ? 'chip-on' : '' }}">
                        {{ $region['name'] }}
                    </button>
                @endforeach
            </div>
            @if ($regions)
                <p class="hint">Boleh pilih beberapa negeri. Seluruh Malaysia tidak termasuk Sabah, Sarawak dan Labuan.</p>
            @else
                <p class="hint">Buat masa ni iklan pergi ke seluruh Malaysia.</p>
                @if ($this->showsServerNotes())
                    <div class="note note-warn mt-2 text-xs">
                        <strong>Nota pemilik:</strong> senarai negeri tak dapat ditarik dari Meta.
                        @if ($regionError) <span class="note-sub break-words">Sebab: {{ $regionError }}</span> @endif
                        <span class="note-sub">Semak META_ACCESS_TOKEN dalam .env.</span>
                    </div>
                @endif
            @endif

            <p class="label mt-6">Bajet sehari, setiap iklan</p>
            <div class="mt-2.5 grid grid-cols-4 gap-2">
                @foreach ([20, 30, 37, 50] as $preset)
                    <button type="button" wire:click="$set('budgetRm', {{ $preset }})"
                            class="chip text-center {{ $budgetRm === $preset ? 'chip-on' : '' }}">
                        RM{{ $preset }}
                    </button>
                @endforeach
            </div>
            <label class="sr-only" for="budgetRm">Bajet sehari dalam RM</label>
            <input id="budgetRm" type="number" wire:model.live="budgetRm" min="10" max="200" class="field mt-2.5">
            @error('budgetRm') <p class="err">{{ $message }}</p> @enderror

            <div class="card mt-3 flex items-center justify-between p-4">
                <span class="text-xs t-muted">{{ max($this->creativeCount(), 1) }} iklan &times; RM{{ $budgetRm }}</span>
                <span class="text-lg font-extrabold tabular-nums t-primary">
                    RM{{ $this->totalDailyRm() }}<span class="text-xs font-semibold t-faint"> /hari</span>
                </span>
            </div>

            <label class="label mt-6" for="phone">Lead masuk ke WhatsApp</label>
            <input id="phone" type="tel" inputmode="numeric" wire:model="phone" class="field mt-2.5" placeholder="60187922844">
            <p class="hint">Format 60XXXXXXXXX. Kena nombor yang disambung pada Page anda.</p>
            @error('phone') <p class="err">{{ $message }}</p> @enderror
        @endif

        {{-- Satu butang utama. Kembali sebagai pautan kecil. --}}
        <div class="mt-8 space-y-3">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="next,save,upload">
                @if ($step < $totalSteps)
                    <span wire:loading.remove wire:target="next">Seterusnya</span>
                    <span wire:loading wire:target="next">Menyemak…</span>
                @else
                    <span wire:loading.remove wire:target="save">Teruskan ke semakan</span>
                    <span wire:loading wire:target="save">Sedang siapkan…</span>
                @endif
            </button>

            <div class="flex items-center justify-between text-xs">
                @if ($step > 1)
                    <button type="button" wire:click="back" class="btn-link">&lsaquo; Kembali</button>
                @else
                    <span></span>
                @endif
                <span class="t-faint">Belum ada apa-apa dihantar ke Meta</span>
            </div>
        </div>
    </form>
</div>
