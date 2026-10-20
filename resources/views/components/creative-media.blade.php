@props(['variant', 'class' => ''])

{{--
    Gambar atau video — satu tempat yang tahu bezanya.

    Tanpa komponen ni, setiap skrin yang memapar creative perlu ingat untuk
    menyemak media_type, dan satu daripadanya akan terlepas: video akan jadi
    kotak gambar pecah tanpa sebarang petunjuk kenapa.

    Video dipaparkan dengan kawalan main supaya peniaga boleh sahkan dia pilih
    video yang betul sebelum membelanjakan duit.
--}}
@if ($variant->isVideo() && filled($variant->video_path))
    <video src="{{ Storage::disk('public')->url($variant->video_path) }}"
           @if (filled($variant->meta_thumbnail_url)) poster="{{ $variant->meta_thumbnail_url }}" @endif
           controls muted playsinline preload="metadata"
           class="{{ $class }} bg-black"></video>
@elseif (filled($variant->image_path))
    <img src="{{ Storage::disk('public')->url($variant->image_path) }}"
         alt="Iklan {{ $variant->position }}" class="{{ $class }}">
@elseif ($variant->isMediaPruned())
    {{--
        Media dibuang selepas tempoh simpanan tamat. Iklan di Meta tidak
        terjejas — Meta memegang salinannya sendiri. Katakan sebabnya; kotak
        kosong tanpa penjelasan akan jadi tiket support.
    --}}
    <div class="{{ $class }} flex flex-col items-center justify-center gap-1 border border-dashed border-current/20 px-2 text-center text-[10px] leading-tight t-faint">
        <span>Gambar dibuang</span>
        <span>selepas {{ config('dynoads.privacy.retention_days') }} hari</span>
    </div>
@else
    {{-- Variant posting sedia ada sebelum gambarnya disalin. --}}
    <div class="{{ $class }} flex items-center justify-center border border-dashed border-current/20 text-xs t-faint">
        Tiada pratonton
    </div>
@endif
