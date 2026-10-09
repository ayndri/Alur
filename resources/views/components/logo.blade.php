{{--
    Logo sementara: nama produk sebagai teks, dengan tiga lingkaran status (antre, dikerjakan, selesai)
    sebagai tanda. Belum ada logo resmi; ganti kalau sudah ada.
--}}
@props(['invert' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="flex items-center gap-0.5" aria-hidden="true">
        <x-kind kind="queue" :size="13" />
        <x-kind kind="active" :size="13" />
        <x-kind kind="done" :size="13" />
    </span>
    <span class="text-[15px] font-bold tracking-tight {{ $invert ? 'text-white' : 'text-ink' }}">alur</span>
</span>
