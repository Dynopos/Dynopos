<div>
    <h1 class="text-[28px] font-extrabold leading-tight tracking-tight">Buat iklan baru</h1>
    <p class="mt-1.5 text-sm leading-relaxed t-muted">
        Satu gambar = satu iklan. Kita bandingkan gambar mana yang paling murah kosnya.
    </p>

    <form wire:submit="save" class="mt-7 space-y-7">

        {{-- Gambar --}}
        <div>
            <div class="flex items-baseline justify-between">
                <span class="label">Gambar iklan</span>
                <span class="text-xs font-semibold t-faint">{{ $this->creativeCount() }} / {{ config('dynoads.creative.max_images') }}</span>
            </div>

            @php $posters = $this->posterJobs(); @endphp

            @if ($posters->isNotEmpty())
                <div class="mt-3 grid grid-cols-4 gap-2">
                    @foreach ($posters as $job)
                        <div class="relative">
                            <img src="{{ Storage::disk(config('dynoads.poster.disk'))->url($job->output_path) }}" alt=""
                                 class="aspect-square w-full rounded-xl border border-current/10 object-cover">
                            <span class="absolute left-1 top-1 rounded-md bg-dyno-gradient px-1.5 text-[10px] font-bold text-white">POSTER</span>
                            <button type="button" wire:click="removePoster({{ $job->id }})" aria-label="Buang poster"
                                    class="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full border border-current/20 text-xs font-bold"
                                    style="background-color: rgb(var(--surface-soft))">&times;</button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($images)
                <div class="mt-3 grid grid-cols-4 gap-2">
                    @foreach ($images as $i => $image)
                        <div>
                            <div class="relative">
                                <img src="{{ $image->temporaryUrl() }}" alt=""
                                     class="aspect-square w-full rounded-xl border border-current/10 object-cover">
                                <span class="absolute left-1 top-1 rounded-md bg-black/70 px-1.5 text-[10px] font-bold text-white">#{{ $i + 1 }}</span>
                                <button type="button" wire:click="removeImage({{ $i }})" aria-label="Buang gambar {{ $i + 1 }}"
                                        class="absolute -right-1.5 -top-1.5 flex h-6 w-6 items-center justify-center rounded-full border border-current/20 text-xs font-bold"
                                        style="background-color: rgb(var(--surface-soft))">&times;</button>
                            </div>
                            <button type="button" wire:click="makePoster({{ $i }})"
                                    class="mt-1.5 w-full rounded-lg border border-current/15 px-1 py-1 text-[10px] font-bold leading-tight t-muted">
                                Jadikan<br>poster
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->creativeCount() < config('dynoads.creative.max_images'))
                <label class="mt-3 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-current/20 bg-current/[0.03] px-4 py-5 text-sm font-semibold t-muted">
                    <span wire:loading.remove wire:target="upload">
                        {{ $images ? '+ Tambah gambar lagi' : '+ Pilih gambar' }}
                    </span>
                    <span wire:loading wire:target="upload" class="text-dyno-magenta dark:text-dyno-pink">Memuat naik…</span>
                    <input type="file" wire:model="upload" multiple accept="image/*" class="hidden">
                </label>
                <p class="hint">
                    Boleh pilih satu-satu. Setiap gambar ditambah, bukan menggantikan yang sebelumnya.
                    Semua dipotong jadi persegi 1080&times;1080. Had saiz {{ $this->uploadLimit() }} setiap satu.
                </p>

            @if ($this->uploadLimitTooTight())
                <div class="mt-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-relaxed text-amber-800 dark:text-amber-200">
                    Pelayan ni hanya benarkan gambar sehingga <span class="font-semibold">{{ $this->uploadLimit() }}</span>.
                    Gambar telefon selalunya lebih besar daripada tu, jadi muat naik akan gagal.
                    <span class="mt-1.5 block text-amber-700/80 dark:text-amber-200/70">
                        Pembetulan kekal: naikkan <code>upload_max_filesize</code> dan <code>post_max_size</code>
                        dalam Forge &rarr; server &rarr; PHP. Sementara tu, kecilkan gambar dulu.
                    </span>
                </div>
            @endif

                <a href="{{ route('posters.create') }}" wire:navigate
                   class="card mt-3 flex items-center gap-3 p-3.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-dyno-gradient text-lg">&#10022;</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold">Buat poster dulu</span>
                        <span class="block text-xs leading-relaxed t-muted">
                            Gambar produk &rarr; latar dibuang &rarr; teks besar. Lebih menonjol daripada gambar mentah.
                        </span>
                    </span>
                    <span class="t-faint">&rarr;</span>
                </a>

                <a href="{{ route('ad-sets.existing-posts') }}" wire:navigate
                   class="card mt-2 flex items-center gap-3 p-3.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-dyno-gradient text-lg">&#128241;</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold">Guna posting sedia ada</span>
                        <span class="block text-xs leading-relaxed t-muted">
                            Pilih posting yang dah ada di Page anda. Like dan komen terkumpul pada posting asal.
                        </span>
                    </span>
                    <span class="t-faint">&rarr;</span>
                </a>
            @else
                <p class="hint">Sudah cukup 4. Buang satu kalau nak tukar.</p>
            @endif

            @error('images') <p class="err">{{ $message }}</p> @enderror
            @error('images.*') <p class="err">{{ $message }}</p> @enderror
        </div>

        {{-- Masalah --}}
        <div>
            <label class="label" for="problem">Masalah pelanggan anda</label>
            <textarea id="problem" wire:model="problem" rows="2" class="field mt-2"
                      placeholder="Contoh: kira duit lambat waktu peak hour"></textarea>
            @error('problem') <p class="err">{{ $message }}</p> @enderror
        </div>

        {{-- Tawaran --}}
        <div>
            <label class="label" for="offer">Apa yang anda tawarkan</label>
            <textarea id="offer" wire:model="offer" rows="2" class="field mt-2"
                      placeholder="Contoh: sistem POS fullset, pasang di kedai"></textarea>
            @error('offer') <p class="err">{{ $message }}</p> @enderror
        </div>

        {{-- Telefon --}}
        <div>
            <label class="label" for="phone">Nombor WhatsApp</label>
            <input id="phone" type="tel" inputmode="numeric" wire:model="phone" class="field mt-2" placeholder="60187922844">
            <p class="hint">Format 60XXXXXXXXX, tiada tanda +.</p>
            @error('phone') <p class="err">{{ $message }}</p> @enderror
        </div>

        {{-- Kawasan --}}
        <div>
            <span class="label">Kawasan</span>

            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" wire:click="clearRegions"
                        class="chip {{ $regionKeys === [] ? 'chip-on' : '' }}">
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
                <p class="hint">
                    Tekan negeri untuk pilih — boleh pilih beberapa. Kalau tiada satu pun dipilih,
                    iklan pergi ke seluruh Malaysia (tidak termasuk Sabah, Sarawak dan Labuan).
                </p>
            @else
                <div class="mt-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                    Senarai negeri tidak dapat ditarik dari Meta, jadi buat masa ni iklan pergi ke
                    seluruh Malaysia sahaja.
                    @if ($regionError)
                        <span class="mt-1.5 block break-words text-amber-700/80 dark:text-amber-200/60">Sebab: {{ $regionError }}</span>
                    @endif
                    <span class="mt-1.5 block text-amber-700/80 dark:text-amber-200/60">Semak META_ACCESS_TOKEN dalam .env.</span>
                </div>
            @endif
        </div>

        {{-- Bajet --}}
        <div>
            <span class="label">Bajet sehari, setiap iklan</span>
            <div class="mt-2 grid grid-cols-4 gap-2">
                @foreach ([20, 30, 37, 50] as $preset)
                    <button type="button" wire:click="$set('budgetRm', {{ $preset }})"
                            class="chip text-center {{ $budgetRm === $preset ? 'chip-on' : '' }}">
                        RM{{ $preset }}
                    </button>
                @endforeach
            </div>
            <input type="number" wire:model.live="budgetRm" min="10" max="200" class="field mt-2">
            @error('budgetRm') <p class="err">{{ $message }}</p> @enderror

            @if ($this->creativeCount())
                <div class="card mt-3 flex items-center justify-between p-3.5">
                    <span class="text-xs t-muted">{{ $this->creativeCount() }} iklan &times; RM{{ $budgetRm }}</span>
                    <span class="text-base font-bold">
                        <span class="gradient-text">RM{{ $this->totalDailyRm() }}</span>
                        <span class="text-xs font-medium t-faint">/hari</span>
                    </span>
                </div>
            @endif
        </div>

        <div>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,upload">
                <span wire:loading.remove wire:target="save">Teruskan ke semakan</span>
                <span wire:loading wire:target="save">Sedang siapkan…</span>
            </button>
            <p class="mt-3 text-center text-xs t-faint">Belum ada apa-apa dihantar ke Meta.</p>
        </div>
    </form>
</div>
