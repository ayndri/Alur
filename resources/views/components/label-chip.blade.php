@props(['label'])
@php
    $tone = [
        'slate' => 'bg-slate-tint text-slate', 'red' => 'bg-red-tint text-red', 'amber' => 'bg-amber-tint text-amber',
        'green' => 'bg-green-tint text-green', 'blue' => 'bg-blue-tint text-blue', 'violet' => 'bg-violet-tint text-violet',
    ][$label->color] ?? 'bg-slate-tint text-slate';
@endphp
<span {{ $attributes->merge(['class' => "badge {$tone}"]) }}>{{ $label->name }}</span>
