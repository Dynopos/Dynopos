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
@else
    {{-- Variant posting sedia ada sebelum gambarnya disalin. --}}
    <div class="{{ $class }} flex items-center justify-center border border-dashed border-current/20 text-xs t-faint">
        Tiada pratonton
    </div>
@endif
