@props(['epic'])
@php
    // Daftar kelas ditulis utuh supaya terbaca oleh Tailwind saat build.
    $dot = ['slate' => 'bg-slate', 'red' => 'bg-red', 'amber' => 'bg-amber', 'green' => 'bg-green', 'blue' => 'bg-blue', 'violet' => 'bg-violet'][$epic->color] ?? 'bg-slate';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex min-w-0 items-center gap-1 text-[11px] font-medium text-ink-2']) }} title="Epic: {{ $epic->name }}">
    <span class="h-2 w-2 shrink-0 rounded-[2px] {{ $dot }}" aria-hidden="true"></span>
    <span class="truncate"><span class="sr-only">Epic: </span>{{ $epic->name }}</span>
</span>
