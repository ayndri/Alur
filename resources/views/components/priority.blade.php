@props(['level'])
{{-- Bendera prioritas. Tanpa prioritas tidak menampilkan apa-apa: kartu biasa tidak perlu tanda. --}}
@php
    $styles = [1 => 'bg-slate-tint text-slate', 2 => 'bg-amber-tint text-amber', 3 => 'bg-red-tint text-red'];
    $label = \App\Models\Card::PRIORITY_LABELS[$level] ?? null;
@endphp
@if ($level > 0)
    <span {{ $attributes->merge(['class' => "inline-grid h-6 w-6 shrink-0 place-items-center rounded-sm {$styles[$level]}"]) }} title="Prioritas {{ strtolower($label) }}">
        <x-icon name="flag" :size="13" />
        <span class="sr-only">Prioritas {{ strtolower($label) }}</span>
    </span>
@endif
