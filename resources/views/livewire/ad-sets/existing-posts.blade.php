<div>
    <h1 class="page-title">Guna posting sedia ada</h1>
    <p class="page-lead">
        Pilih {{ $this->minSelection() }}–{{ $this->maxSelection() }} posting yang anda dah ada.
        Setiap satu jadi satu iklan, dan kita bandingkan mana yang paling murah kosnya.
    </p>

    {{-- Amaran wajib: organik dan iklan bukan benda yang sama. Organik diedar
         kepada peminat sedia ada; iklan diedar kepada orang asing. --}}
    <div class="mt-4 note note-warn text-xs">
        Posting yang naik secara organik belum tentu murah kosnya sebagai iklan — sebab tu kita tetap split test.
    </div>

    @if ($error)
        <div class="mt-4 note note-err text-xs">
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
                <div class="mt-2 note note-warn text-xs">
                    Senarai posting tidak dapat ditarik dari Facebook. Cuba lagi sekejap.
                    @if (auth()->user()?->is_owner || \App\Support\Impersonation::active())
                        <span class="note-sub"><strong>Nota pemilik:</strong> {{ $loadError }}</span>
                        <span class="note-sub">Semak META_ACCESS_TOKEN dan META_PAGE_ID dalam .env.</span>
                    @endif
                </div>
            @elseif ($posts === [])
                <p class="hint mt-2">Tiada posting bergambar dijumpai di Page ni.</p>
            @else
                <div class="mt-3 grid grid-cols-2 gap-2.5">
                    @foreach ($posts as $post)
                        @php $on = $this->isSelected($post['id']); @endphp
                        <button type="button" wire:key="post-{{ $post['id'] }}"
                                wire:click="toggle('{{ $post['id'] }}')"
                                class="card overflow-hidden p-0 text-left transition active:scale-[0.99]"
                                @style(['outline: 3px solid rgb(var(--primary)); outline-offset: -3px' => $on])
                                aria-pressed="{{ $on ? 'true' : 'false' }}">
                            <div class="relative">
                                <img src="{{ $post['full_picture'] }}" alt=""
                                     class="aspect-square w-full object-cover">
                                @if ($on)
                                    <span class="absolute right-1.5 top-1.5 flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold" style="background-color: rgb(var(--primary)); color: rgb(var(--on-primary))">&check;</span>
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

            <div class="mt-2.5 flex flex-wrap gap-2">
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
                <div class="mt-2 note note-warn text-xs">
                    Senarai negeri tidak dapat ditarik dari Meta, jadi buat masa ni iklan pergi ke
                    seluruh Malaysia sahaja.
                    @if ($regionError && (auth()->user()?->is_owner || \App\Support\Impersonation::active()))
                        <span class="note-sub"><strong>Nota pemilik:</strong> {{ $regionError }}</span>
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
            <label class="sr-only" for="budgetRm">Bajet sehari dalam RM</label>
            <input id="budgetRm" type="number" wire:model.live="budgetRm" min="10" max="200" class="field mt-2.5">
            @error('budgetRm') <p class="err">{{ $message }}</p> @enderror

            @if (count($selected))
                <div class="card mt-3 flex items-center justify-between p-4">
                    <span class="text-xs t-muted">{{ count($selected) }} iklan &times; RM{{ $budgetRm }}</span>
                    <span class="text-lg font-extrabold tabular-nums t-primary">
                        RM{{ $this->totalDailyRm() }}<span class="text-xs font-semibold t-faint"> /hari</span>
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
