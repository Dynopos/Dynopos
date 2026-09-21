<div>
    <h1 class="text-[28px] font-extrabold leading-tight tracking-tight">Guna posting sedia ada</h1>
    <p class="mt-1.5 text-sm leading-relaxed t-muted">
        Pilih {{ $this->minSelection() }}–{{ $this->maxSelection() }} posting yang anda dah ada.
        Setiap satu jadi satu iklan, dan kita bandingkan mana yang paling murah kosnya.
    </p>

    {{-- Amaran wajib: organik dan iklan bukan benda yang sama. Organik diedar
         kepada peminat sedia ada; iklan diedar kepada orang asing. --}}
    <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
        Posting yang naik secara organik belum tentu murah kosnya sebagai iklan — sebab tu kita tetap split test.
    </div>

    @if ($error)
        <div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs leading-relaxed text-rose-800 dark:text-rose-200/90">
            {{ $error }}
        </div>
    @endif

    <form wire:submit="launch" class="mt-7 space-y-7">

        {{-- Posting --}}
        <div>
            <div class="flex items-baseline justify-between">
                <span class="label">Posting anda</span>
                <span class="text-xs font-semibold t-faint">{{ count($selected) }} / {{ $this->maxSelection() }}</span>
            </div>

            @if ($loadError)
                <div class="mt-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs leading-relaxed text-amber-800 dark:text-amber-200/90">
                    Senarai posting tidak dapat ditarik dari Facebook.
                    <span class="mt-1.5 block break-words text-amber-700/80 dark:text-amber-200/60">Sebab: {{ $loadError }}</span>
                    <span class="mt-1.5 block text-amber-700/80 dark:text-amber-200/60">
                        Semak META_ACCESS_TOKEN dan META_PAGE_ID dalam .env.
                    </span>
                </div>
            @elseif ($posts === [])
                <p class="hint mt-2">Tiada posting bergambar dijumpai di Page ni.</p>
            @else
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    @foreach ($posts as $post)
                        @php $on = $this->isSelected($post['id']); @endphp
                        <button type="button" wire:key="post-{{ $post['id'] }}"
                                wire:click="toggle('{{ $post['id'] }}')"
                                class="card overflow-hidden p-0 text-left transition {{ $on ? 'ring-2 ring-offset-0' : '' }}"
                                @style(['outline: 2px solid rgb(var(--brand)); outline-offset: -2px' => $on])>
                            <div class="relative">
                                <img src="{{ $post['full_picture'] }}" alt=""
                                     class="aspect-square w-full object-cover">
                                @if ($on)
                                    <span class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full bg-dyno-gradient text-xs font-bold text-white">&check;</span>
                                @endif
                            </div>
                            <div class="space-y-1 p-2.5">
                                <p class="line-clamp-2 text-xs leading-relaxed">{{ $post['excerpt'] ?: 'Tiada teks' }}</p>
                                <p class="text-[11px] t-faint">
                                    {{ $post['reactions'] }} reaksi &middot; {{ $post['comments'] }} komen
                                </p>
                                <p class="text-[11px] t-faint">
                                    {{ \Illuminate\Support\Carbon::parse($post['created_time'])->diffForHumans() }}
                                </p>
                            </div>
                        </button>
                    @endforeach
                </div>

                @if ($nextCursor)
                    <button type="button" wire:click="loadMore" class="chip mt-3 w-full justify-center"
                            wire:loading.attr="disabled" wire:target="loadMore">
                        <span wire:loading.remove wire:target="loadMore">Tunjuk lagi</span>
                        <span wire:loading wire:target="loadMore">Sedang ambil…</span>
                    </button>
                @endif
            @endif

            @error('selected') <p class="err">{{ $message }}</p> @enderror
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

            @if (count($selected))
                <div class="card mt-3 flex items-center justify-between p-3.5">
                    <span class="text-xs t-muted">{{ count($selected) }} iklan &times; RM{{ $budgetRm }}</span>
                    <span class="text-base font-bold">
                        <span class="gradient-text">RM{{ $this->totalDailyRm() }}</span>
                        <span class="text-xs font-medium t-faint">/hari</span>
                    </span>
                </div>
            @endif
        </div>

        <div>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="launch">
                <span wire:loading.remove wire:target="launch">Buat iklan</span>
                <span wire:loading wire:target="launch">Sedang siapkan…</span>
            </button>
            <p class="hint mt-2 text-center">
                Semua iklan dibuat PAUSED. Tiada duit dibelanjakan sampai anda tekan RUN.
            </p>
        </div>
    </form>
</div>
