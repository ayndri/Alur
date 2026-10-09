@props(['user', 'size' => 'md'])
@php
    // Inisial di atas warna tetap per orang (dari id), supaya orang yang sama selalu sewarna di semua papan.
    // Pasangan warna diambil dari palet status yang kontrasnya sudah dicek (>= 5:1).
    $tones = [
        'bg-blue-tint text-blue', 'bg-green-tint text-green', 'bg-amber-tint text-amber',
        'bg-violet-tint text-violet', 'bg-red-tint text-red', 'bg-slate-tint text-slate',
    ];
    $tone = $user ? $tones[$user->id % count($tones)] : 'bg-canvas text-muted';
    $dim = ['sm' => 'h-6 w-6 text-[10px]', 'md' => 'h-7 w-7 text-[11px]', 'lg' => 'h-9 w-9 text-xs'][$size];
@endphp
<span {{ $attributes->merge(['class' => "inline-grid shrink-0 place-items-center rounded-full font-semibold ring-2 ring-paper {$dim} {$tone}"]) }}
      title="{{ $user?->name ?? 'Belum ada yang memegang' }}">
    @if ($user)
        {{ $user->initials() }}
    @else
        <x-icon name="user" :size="$size === 'sm' ? 12 : 14" />
    @endif
    <span class="sr-only">{{ $user?->name ?? 'Belum ada yang memegang' }}</span>
</span>
