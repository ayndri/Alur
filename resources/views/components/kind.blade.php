@props(['kind', 'size' => 14])
{{--
    Motif Alur: lingkaran status. Antre = lingkaran putus-putus (belum dimulai), dikerjakan = setengah
    terisi amber, menunggu = lingkaran biru dengan tanda jeda, selesai = penuh hijau dengan centang. Dipakai di kepala kolom, detail kartu, dan legenda metrik.
--}}
@php
    $labels = ['queue' => 'Antre', 'active' => 'Dikerjakan', 'wait' => 'Menunggu', 'done' => 'Selesai'];
@endphp
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 16 16" role="img" aria-label="{{ $labels[$kind] ?? $kind }}">
    @if ($kind === 'done')
        <circle cx="8" cy="8" r="7" fill="var(--color-green)"/>
        <path d="m5 8.2 2 2 4-4.2" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
    @elseif ($kind === 'wait')
        <circle cx="8" cy="8" r="6.25" fill="none" stroke="var(--color-blue)" stroke-width="1.5"/>
        <path d="M6.6 5.6v4.8M9.4 5.6v4.8" stroke="var(--color-blue)" stroke-width="1.6" stroke-linecap="round"/>
    @elseif ($kind === 'active')
        <circle cx="8" cy="8" r="6.25" fill="none" stroke="var(--color-amber)" stroke-width="1.5"/>
        <path d="M8 3.5a4.5 4.5 0 0 1 0 9z" fill="var(--color-amber)"/>
    @else
        <circle cx="8" cy="8" r="6.25" fill="none" stroke="var(--color-muted)" stroke-width="1.5" stroke-dasharray="2.2 2"/>
    @endif
</svg>
