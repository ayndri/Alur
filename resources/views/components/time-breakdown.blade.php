@props(['breakdown', 'columns', 'compact' => false])
@php
    // Warna mengikuti kolom (urutan kolom = slot palet), sama dengan diagram alir kumulatif.
    $palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
    $colorOf = $columns->values()->mapWithKeys(fn ($c, $i) => [$c->id => $palette[$i % count($palette)]]);
    $pct = fn ($v) => $v === null ? '-' : round($v * 100).'%';
    $rows = $breakdown['columns'];
    $inFlowOrder = $rows->sortBy(fn ($r) => $r['column']->position)->values();
    $top = $rows->first();
    $rw = $breakdown['rework'];
    $reasonLabels = \App\Models\CardMove::REASONS + ['none' => 'Tanpa alasan'];
    $topReason = array_key_first($rw['reasons']);
    $topFrom = array_key_first($rw['from']);
    $waitColumn = $rows->first(fn ($r) => $r['column']->kind === 'wait');
@endphp

<div {{ $attributes }}>
    @if ($breakdown['cards'] === 0)
        <p class="rounded-md border border-dashed border-line-strong px-4 py-6 text-center text-[13px] text-muted">
            Belum ada kartu yang selesai dalam rentang ini, jadi belum ada waktu yang bisa dibagi.
        </p>
    @else
        {{-- Temuan utama, ditulis sebagai kalimat supaya bisa langsung dibacakan di rapat. --}}
        <ul class="space-y-2.5 text-[14px] leading-relaxed">
            @if ($top)
                <li class="flex gap-2.5">
                    <x-kind :kind="$top['column']->kind" class="mt-1" />
                    <span><span class="font-semibold">{{ $top['column']->name }}</span> memakan {{ $pct($top['share']) }} cycle time,
                        median {{ hari($top['median']) }} per kartu.</span>
                </li>
            @endif
            @if ($rw['cards'] > 0)
                <li class="flex gap-2.5">
                    <x-icon name="rework" :size="15" class="mt-1 text-red" />
                    <span><span class="font-semibold">{{ $pct($rw['share']) }} kartu dikembalikan</span> minimal sekali.
                        Revisi menambah median {{ hari($rw['median_days']) }} per kartu yang direvisi.
                        @if ($topReason)
                            Paling sering: {{ lcfirst($reasonLabels[$topReason] ?? $topReason) }} ({{ $rw['reasons'][$topReason] }}×){{ $topFrom ? ', dikembalikan dari '.$topFrom : '' }}.
                        @endif
                    </span>
                </li>
            @else
                <li class="flex gap-2.5"><x-icon name="check" :size="15" class="mt-1 text-green" /><span>Tidak ada kartu yang dikembalikan untuk revisi.</span></li>
            @endif
            <li class="flex gap-2.5">
                <x-kind kind="wait" class="mt-1" />
                <span>Kartu <span class="font-semibold">menunggu {{ $pct($breakdown['wait_share']) }}</span> dari waktunya
                    @if ($waitColumn)(di antaranya {{ $waitColumn['column']->name }} {{ $pct($waitColumn['share']) }}@if ($breakdown['blocked_share'] > 0.005), terblokir {{ $pct($breakdown['blocked_share']) }}@endif)@elseif ($breakdown['blocked_share'] > 0.005)(di antaranya terblokir {{ $pct($breakdown['blocked_share']) }})@endif.
                    Sisanya {{ $pct($breakdown['work_share']) }} di kolom kerja.</span>
            </li>
        </ul>

        {{-- 100%: urutan sesuai alur kartu, celah 2px antarsegmen. Label di bawah bar dengan warna teks biasa,
             karena teks di atas beberapa warna palet tidak mencapai kontras 4,5:1. --}}
        <div class="mt-5 flex h-8 gap-0.5 overflow-hidden rounded-md" role="img"
             aria-label="Pembagian cycle time: {{ $inFlowOrder->map(fn ($r) => $r['column']->name.' '.$pct($r['share']))->join(', ') }}">
            @foreach ($inFlowOrder as $row)
                <div style="width: {{ $row['share'] * 100 }}%; background: {{ $colorOf[$row['column']->id] ?? '#686874' }}" title="{{ $row['column']->name }}: {{ $pct($row['share']) }}"></div>
            @endforeach
        </div>
        <ul class="mt-2.5 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-ink-2" aria-hidden="true">
            @foreach ($inFlowOrder as $row)
                <li class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $colorOf[$row['column']->id] ?? '#686874' }}"></span>
                    {{ $row['column']->name }} <span class="font-semibold text-ink tnum">{{ $pct($row['share']) }}</span>
                </li>
            @endforeach
        </ul>

        @unless ($compact)
            <div class="mt-4 overflow-x-auto rounded-lg border border-line">
                <table class="w-full min-w-[34rem] text-left text-[13px]">
                    <thead class="bg-column text-xs text-ink-2">
                        <tr>
                            <th scope="col" class="px-3 py-2 font-medium">Kolom</th>
                            <th scope="col" class="px-3 py-2 font-medium">Bagian dari cycle time</th>
                            <th scope="col" class="px-3 py-2 font-medium">Median per kartu</th>
                            <th scope="col" class="px-3 py-2 font-medium">Dikembalikan dari sini</th>
                        </tr>
                    </thead>
                    <tbody class="tnum">
                        @foreach ($inFlowOrder as $row)
                            <tr class="border-t border-line">
                                <th scope="row" class="px-3 py-2 font-medium">
                                    <span class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-[3px]" style="background: {{ $colorOf[$row['column']->id] ?? '#686874' }}" aria-hidden="true"></span>
                                        <x-kind :kind="$row['column']->kind" :size="12" />
                                        {{ $row['column']->name }}
                                    </span>
                                </th>
                                <td class="px-3 py-2">{{ $pct($row['share']) }}</td>
                                <td class="px-3 py-2">{{ hari($row['median']) }}</td>
                                <td class="px-3 py-2">
                                    @if ($n = $rw['from'][$row['column']->name] ?? 0)
                                        <span class="font-medium text-red">{{ $n }}×</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rw['reasons'])
                @php $maxReason = max($rw['reasons']); @endphp
                <div class="mt-6">
                    <h3 class="text-[13px] font-semibold">Alasan kartu dikembalikan</h3>
                    <dl class="mt-2 space-y-2">
                        @foreach ($rw['reasons'] as $key => $n)
                            <div class="grid grid-cols-[11rem_1fr_2.5rem] items-center gap-3 text-[13px] max-sm:grid-cols-[1fr_2.5rem]">
                                <dt class="truncate text-ink-2">{{ $reasonLabels[$key] ?? $key }}</dt>
                                <dd class="h-2.5 rounded-full bg-canvas max-sm:hidden" aria-hidden="true"><div class="h-full rounded-full bg-ink-2" style="width: {{ $n / $maxReason * 100 }}%"></div></dd>
                                <dd class="text-right font-semibold tnum">{{ $n }}×</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            <p class="mt-5 text-xs leading-relaxed text-muted">
                Dihitung dari {{ $breakdown['cards'] }} kartu yang selesai, hanya rentang sejak mulai dikerjakan sampai selesai.
                Waktu di kolom kerja termasuk kartu yang antre di dalam kolom itu (mis. menunggu penguji di QA). Untuk memisahkannya,
                tambahkan kolom berjenis Menunggu sebelumnya, misalnya "Siap QA".
            </p>
        @endunless
    @endif
</div>
