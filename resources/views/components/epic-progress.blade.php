@props(['row', 'detailed' => false])
@php
    $total = max(1, $row['total']);
    $statuses = [
        'done' => ['Semua kartu selesai', 'bg-green-tint text-green', 'check'],
        'safe' => ['Sesuai target', 'bg-green-tint text-green', 'check'],
        'risky' => ['Berisiko lewat target', 'bg-amber-tint text-amber', 'hourglass'],
        'late' => ['Kemungkinan lewat target', 'bg-red-tint text-red', 'alert'],
    ];
    $status = $statuses[$row['target']] ?? null;
    $f = $row['forecast'];
    $remaining = $row['total'] - $row['done'];
@endphp
<div {{ $attributes }}>
    {{-- Bar bersegmen: urutannya sama dengan alur kartu, selesai paling kiri. Celah 2px memisahkan segmen. --}}
    <div class="flex h-2 gap-0.5 overflow-hidden rounded-full bg-canvas" role="img"
         aria-label="{{ $row['done'] }} selesai, {{ $row['active'] }} dikerjakan, {{ $row['queue'] }} antre, dari {{ $row['total'] }} kartu">
        @if ($row['done'])<span class="bg-green" style="width: {{ $row['done'] / $total * 100 }}%"></span>@endif
        @if ($row['active'])<span class="bg-amber" style="width: {{ $row['active'] / $total * 100 }}%"></span>@endif
        @if ($row['queue'])<span class="bg-line-strong" style="width: {{ $row['queue'] / $total * 100 }}%"></span>@endif
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-2 tnum">
        <span class="inline-flex items-center gap-1.5"><x-kind kind="done" :size="11" />{{ $row['done'] }} selesai</span>
        <span class="inline-flex items-center gap-1.5"><x-kind kind="active" :size="11" />{{ $row['active'] }} dikerjakan</span>
        <span class="inline-flex items-center gap-1.5"><x-kind kind="queue" :size="11" />{{ $row['queue'] }} antre</span>
        @if ($row['blocked'])
            <span class="inline-flex items-center gap-1 font-medium text-red"><x-icon name="blocked" :size="12" />{{ $row['blocked'] }} terblokir</span>
        @endif
        @if ($status)
            <span class="badge {{ $status[1] }} ml-auto"><x-icon :name="$status[2]" :size="12" />{{ $status[0] }}</span>
        @endif
    </div>
    @if ($detailed && $remaining > 0)
        <p class="mt-3 text-[13px] leading-relaxed text-ink-2">
            @if ($f)
                Kalau seluruh tim hanya mengerjakan epic ini, {{ $remaining }} kartu tersisa selesai dalam
                <span class="font-semibold text-ink">{{ $f['p50'] }} sampai {{ $f['p85'] }} hari</span>
                ({{ tanggal(now()->addDays($f['p50'])) }} sampai {{ tanggal(now()->addDays($f['p85'])) }}).
                @if ($row['epic']->target_on)
                    Targetnya {{ tanggal($row['epic']->target_on) }}.
                    @if ($row['target'] === 'late')
                        Skenario paling optimis ini pun sudah melewati target, jadi targetnya perlu digeser atau isinya dikurangi.
                    @elseif ($row['target'] === 'risky')
                        Tim perlu fokus penuh ke epic ini supaya target tercapai.
                    @endif
                @endif
            @else
                Belum bisa diperkirakan: tim belum menyelesaikan kartu dalam 6 minggu terakhir.
            @endif
        </p>
    @endif
</div>
