@php
    // Bar hanya untuk rentang sejak mulai dikerjakan; waktu antre sebelum itu ada di daftar.
    $palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
    $colorOf = $project->columns->values()->mapWithKeys(fn ($c, $i) => [$c->id => $palette[$i % count($palette)]]);
    $working = $card->started_at
        ? $journey->filter(fn ($s) => $s['to']->gt($card->started_at) && $s['column'] && ! $s['column']->isDone())
            ->map(fn ($s) => array_merge($s, ['days' => $s['from']->max($card->started_at)->diffInSeconds($s['to']) / 86400]))
            ->values()
        : collect();
    $span = max(1e-6, $working->sum('days'));
    $reworks = $journey->where('rework', true)->count();
    $reasons = \App\Models\CardMove::REASONS;
@endphp
<section aria-labelledby="perjalanan">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="perjalanan" class="text-[14px]">Perjalanan kartu</h2>
        @if ($reworks)
            <span class="badge bg-red-tint text-red"><x-icon name="rework" :size="12" /> Dikembalikan {{ $reworks }}×</span>
        @endif
    </div>

    @if ($working->isNotEmpty())
        <div class="mt-3 flex h-3 gap-0.5 overflow-hidden rounded-full" role="img"
             aria-label="Sejak mulai dikerjakan: {{ $working->map(fn ($s) => ($s['column']?->name ?? 'kolom terhapus').' '.hari($s['days']))->join(', ') }}">
            @foreach ($working as $stay)
                <span style="width: {{ $stay['days'] / $span * 100 }}%; background: {{ $colorOf[$stay['column']?->id] ?? '#686874' }}"
                      class="{{ $stay['rework'] ? 'opacity-70 [background-image:repeating-linear-gradient(135deg,transparent_0_4px,rgba(255,255,255,.55)_4px_6px)]' : '' }}"
                      title="{{ $stay['column']?->name }}: {{ hari($stay['days']) }}{{ $stay['rework'] ? ' (revisi)' : '' }}"></span>
            @endforeach
        </div>
        <p class="mt-1.5 text-xs text-muted">Sejak mulai dikerjakan. Segmen bergaris adalah putaran revisi.</p>
    @endif

    <ol class="mt-3 space-y-0 border-l border-line">
        @foreach ($journey as $stay)
            <li class="relative pb-3 pl-5 last:pb-0">
                <span class="absolute -left-[7px] top-0.5 rounded-full bg-paper">
                    @if ($stay['column'])
                        <x-kind :kind="$stay['column']->kind" :size="13" />
                    @endif
                </span>
                <p class="flex flex-wrap items-baseline gap-x-2 text-[13px]">
                    <span class="font-medium">{{ $stay['column']?->name ?? 'Kolom yang sudah dihapus' }}</span>
                    <span class="tnum text-ink-2">{{ $stay['column']?->isDone() ? tanggal($stay['from'], true) : hari($stay['days']) }}</span>
                    @if ($stay['ongoing'])
                        <span class="text-xs text-muted">sampai sekarang</span>
                    @endif
                </p>
                @if ($stay['rework'])
                    <p class="mt-1 flex items-start gap-1.5 text-xs text-red">
                        <x-icon name="rework" :size="12" class="mt-0.5" />
                        <span>Dikembalikan{{ $stay['by'] ? ' oleh '.$stay['by'] : '' }}{{ $stay['reason'] ? ': '.lcfirst($reasons[$stay['reason']] ?? $stay['reason']) : '' }}{{ $stay['note'] ? '. "'.$stay['note'].'"' : '' }}</span>
                    </p>
                @endif
            </li>
        @endforeach
    </ol>
</section>
