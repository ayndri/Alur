<x-layouts.app :title="'Alur kerja · '.$project->name" :project="$project">
@php
    // Palet kategori tervalidasi (dataviz: urutan tetap, lolos CVD untuk pasangan bersebelahan).
    // Warna mengikuti kolom, bukan peringkat: kolom ke-n selalu slot ke-n.
    $palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
    $columns = $cfd['columns']->values();
    $colorOf = $columns->mapWithKeys(fn ($c, $i) => [$c->id => $palette[$i % count($palette)]]);

    $W = 1080; $H = 280; $pl = 34; $pr = 140; $pt = 12; $pb = 26;
    $plotW = $W - $pl - $pr; $plotH = $H - $pt - $pb;

    $niceMax = function (float $max): array {
        if ($max <= 0) { return [4, 1]; }
        $raw = $max / 4;
        $mag = 10 ** floor(log10($raw));
        $step = collect([1, 2, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s >= $raw);
        $step = max(1, $step);

        return [ceil($max / $step) * $step, $step];
    };

    // ---- Cumulative flow: kolom paling kanan (selesai) di dasar, backlog di puncak.
    $series = $cfd['days'];
    $n = count($series);
    $stackOrder = $columns->reverse()->values();
    $totals = array_map(fn ($d) => array_sum($d['counts']), $series);
    [$cfdMax, $cfdStep] = $niceMax(max($totals ?: [0]));
    $cx = fn ($i) => $pl + ($n > 1 ? $i * $plotW / ($n - 1) : 0);
    $cy = fn ($v) => $pt + $plotH - ($v / $cfdMax) * $plotH;
    $bands = [];
    $base = array_fill(0, $n, 0);
    foreach ($stackOrder as $column) {
        $top = [];
        foreach ($series as $i => $d) { $top[$i] = $base[$i] + ($d['counts'][$column->id] ?? 0); }
        $up = collect(range(0, $n - 1))->map(fn ($i) => round($cx($i), 1).','.round($cy($top[$i]), 1))->join(' L');
        $down = collect(range($n - 1, 0))->map(fn ($i) => round($cx($i), 1).','.round($cy($base[$i]), 1))->join(' L');
        $last = $series[$n - 1]['counts'][$column->id] ?? 0;
        $bands[] = [
            'column' => $column,
            'path' => "M{$up} L{$down} Z",
            'labelY' => $cy(($base[$n - 1] + $top[$n - 1]) / 2),
            'height' => $cy($base[$n - 1]) - $cy($top[$n - 1]),
            'last' => $last,
        ];
        $base = $top;
    }
    $dayTicks = collect(range(0, $n - 1))->filter(fn ($i) => $series[$i]['date']->isMonday())->values();

    // ---- Cycle time: satu titik per kartu selesai.
    $ctMaxValue = max($cycleTimes->max('days') ?? 0, $summary['p85'] ?? 0);
    [$ctMax, $ctStep] = $niceMax($ctMaxValue);
    $ctW = 540; $ctPr = 96; $ctPlotW = $ctW - $pl - $ctPr;
    $rangeStart = $series[0]['date']->copy()->startOfDay();
    $rangeSecs = max(1, $rangeStart->diffInSeconds(now()));
    $sx = fn (\Illuminate\Support\Carbon $t) => $pl + min(1, max(0, $rangeStart->diffInSeconds($t) / $rangeSecs)) * $ctPlotW;
    $sy = fn ($v) => $pt + $plotH - ($v / $ctMax) * $plotH;

    // ---- Throughput per minggu.
    $weeks = count($throughput);
    [$tpMax, $tpStep] = $niceMax(max(array_column($throughput, 'count') ?: [0]));
    $tpW = 540; $tpPr = 16;
    $slot = ($tpW - $pl - $tpPr) / max(1, $weeks);
    $barW = min(36, $slot * 0.62);
    $ty = fn ($v) => $pt + $plotH - ($v / $tpMax) * $plotH;
    $recent = array_slice(array_column($throughput, 'count'), -5, 4);
    $avgWeekly = count($recent) ? array_sum($recent) / count($recent) : 0;


    // ---- Umur kartu.
    $ageScale = max(1, $aging->max('days') ?? 1, $summary['p85'] ?? 0) * 1.08;
    $statusMeta = [
        'late' => ['Lewat P85', 'bg-red-tint text-red', 'alert'],
        'watch' => ['Lewat P50', 'bg-amber-tint text-amber', 'hourglass'],
        'fresh' => ['Wajar', 'bg-green-tint text-green', 'check'],
        'unknown' => ['Belum bisa dinilai', 'bg-slate-tint text-slate', 'info'],
    ];
    $enough = $summary['count'] >= 5;
@endphp

<div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6" x-data>
    {{-- Rentang waktu: satu baris di atas semua grafik. --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-[13px] text-ink-2">Dihitung dari kartu dan perpindahan {{ $days }} hari terakhir.</p>
        <nav class="inline-flex rounded-md bg-canvas p-0.5" aria-label="Rentang waktu">
            @foreach (\App\Http\Controllers\FlowController::RANGES as $range)
                <a href="{{ route('flow.show', [$project, 'hari' => $range]) }}"
                   class="rounded-[5px] px-3 py-1.5 text-[13px] font-medium no-underline max-sm:py-2.5 {{ $range === $days ? 'bg-paper text-ink shadow-[0_1px_2px_rgba(23,23,26,0.08)]' : 'text-ink-2 hover:text-ink' }}"
                   @if ($range === $days) aria-current="true" @endif>{{ $range }} hari</a>
            @endforeach
        </nav>
    </div>

    @if (! $enough)
        <div class="mt-6 rounded-lg border border-dashed border-line-strong px-5 py-6 text-[13px] text-ink-2">
            Baru {{ $summary['count'] }} kartu yang selesai dengan waktu mulai yang tercatat dalam {{ $days }} hari terakhir.
            Cycle time, penilaian umur kartu, dan perkiraan butuh minimal 5 supaya angkanya berarti.
            Grafik di bawah tetap tampil dari data yang ada.
        </div>
    @endif

    {{-- Angka utama --}}
    <dl class="mt-6 grid gap-px overflow-hidden rounded-lg border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
        <div class="bg-paper p-4">
            <dt class="text-xs text-ink-2">85% kartu selesai dalam</dt>
            <dd class="mt-1 text-2xl font-semibold tnum">{{ $enough ? hari($summary['p85']) : '-' }}</dd>
            <dd class="mt-0.5 text-xs text-muted">dari {{ $summary['count'] }} kartu selesai</dd>
        </div>
        <div class="bg-paper p-4">
            <dt class="text-xs text-ink-2">Separuhnya selesai dalam</dt>
            <dd class="mt-1 text-2xl font-semibold tnum">{{ $enough ? hari($summary['p50']) : '-' }}</dd>
            <dd class="mt-0.5 text-xs text-muted">median cycle time</dd>
        </div>
        <div class="bg-paper p-4">
            <dt class="text-xs text-ink-2">Selesai per minggu</dt>
            <dd class="mt-1 text-2xl font-semibold tnum">{{ str_replace('.', ',', (string) round($avgWeekly, 1)) }}</dd>
            <dd class="mt-0.5 text-xs text-muted">rata-rata 4 minggu penuh terakhir</dd>
        </div>
        <div class="bg-paper p-4">
            <dt class="text-xs text-ink-2">Sedang dikerjakan</dt>
            <dd class="mt-1 text-2xl font-semibold tnum">{{ $aging->count() }}</dd>
            <dd class="mt-0.5 text-xs {{ $aging->where('status', 'late')->count() ? 'text-red' : 'text-muted' }}">
                {{ $aging->where('status', 'late')->count() }} lewat P85
            </dd>
        </div>
    </dl>

    {{-- Perkiraan --}}
    <section class="mt-8 rounded-lg bg-column px-5 py-5" aria-labelledby="perkiraan">
        <h2 id="perkiraan" class="text-[14px]">Kapan {{ $remaining }} kartu yang belum selesai beres?</h2>
        @if ($forecast)
            <p class="mt-2 text-[15px] leading-relaxed">
                Kalau tim bekerja seperti 6 minggu terakhir, separuh simulasi selesai dalam
                <span class="font-semibold">{{ $forecast['p50'] }} hari</span>
                (sekitar {{ tanggal(now()->addDays($forecast['p50'])) }}), 85% dalam
                <span class="font-semibold">{{ $forecast['p85'] }} hari</span> ({{ tanggal(now()->addDays($forecast['p85'])) }}),
                dan 95% dalam {{ $forecast['p95'] }} hari.
            </p>
            <p class="mt-2 text-xs leading-relaxed text-muted">
                5.000 simulasi Monte Carlo: setiap simulasi mengambil throughput harian acak dari {{ $forecast['samples'] }} kartu yang
                selesai dalam 42 hari terakhir, sampai jumlahnya mencapai {{ $remaining }}. Kartu baru yang masuk nanti belum ikut dihitung.
            </p>
        @elseif ($remaining === 0)
            <p class="mt-2 text-[13px] text-ink-2">Tidak ada kartu yang belum selesai.</p>
        @else
            <p class="mt-2 text-[13px] text-ink-2">Belum bisa diperkirakan: tidak ada kartu yang selesai dalam 42 hari terakhir, jadi belum ada kecepatan tim untuk dijadikan dasar.</p>
        @endif
    </section>

    {{-- Pembeda Alur: bukan hanya "lama", tapi lama di mana dan kenapa. --}}
    <section class="mt-10" aria-labelledby="waktu">
        <h2 id="waktu" class="text-[14px]">Ke mana waktu kartu habis</h2>
        <p class="mt-1 max-w-3xl text-[13px] text-ink-2">Cycle time kartu yang selesai, dibagi per kolom. Revisi dan masa menunggu dihitung terpisah, supaya kelihatan apakah yang lambat itu pekerjaannya, QA-nya, atau antreannya.</p>
        <x-time-breakdown :breakdown="$breakdown" :columns="$project->columns" class="mt-4" />
    </section>

    {{-- Cumulative flow --}}
    <section class="mt-10" aria-labelledby="cfd">
        <h2 id="cfd" class="text-[14px]">Isi tiap kolom per hari</h2>
        <p class="mt-1 max-w-3xl text-[13px] text-ink-2">
            Pita yang melebar berarti kartu menumpuk di kolom itu. Pita Selesai yang naik stabil berarti kartu terus keluar dari papan.
        </p>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-2" aria-hidden="true">
            @foreach ($columns as $column)
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $colorOf[$column->id] }}"></span>{{ $column->name }}</span>
            @endforeach
        </div>
        <div class="relative mt-2" data-viz>
            <svg viewBox="0 0 {{ $W }} {{ $H }}" class="h-auto w-full" role="img" aria-label="Diagram alir kumulatif {{ $days }} hari. Rincian per hari ada di tabel di bawahnya.">
                @for ($v = 0; $v <= $cfdMax; $v += $cfdStep)
                    <line x1="{{ $pl }}" x2="{{ $pl + $plotW }}" y1="{{ $cy($v) }}" y2="{{ $cy($v) }}" stroke="var(--color-line)" stroke-width="1" />
                    <text x="{{ $pl - 6 }}" y="{{ $cy($v) + 4 }}" text-anchor="end" class="fill-muted text-[10px] tnum">{{ $v }}</text>
                @endfor
                @foreach ($bands as $band)
                    <path d="{{ $band['path'] }}" fill="{{ $colorOf[$band['column']->id] }}" stroke="#fff" stroke-width="1.5" stroke-linejoin="round" />
                    @if ($band['height'] >= 11)
                        <text x="{{ $pl + $plotW + 6 }}" y="{{ $band['labelY'] + 4 }}" class="fill-ink-2 text-[10px]">{{ \Illuminate\Support\Str::limit($band['column']->name, 18) }} <tspan class="fill-ink font-semibold tnum">{{ $band['last'] }}</tspan></text>
                    @endif
                @endforeach
                @foreach ($dayTicks as $i)
                    <text x="{{ $cx($i) }}" y="{{ $H - 8 }}" text-anchor="middle" class="fill-muted text-[10px]">{{ $series[$i]['date']->translatedFormat('j M') }}</text>
                @endforeach
                <line data-crosshair x1="0" x2="0" y1="{{ $pt }}" y2="{{ $pt + $plotH }}" stroke="var(--color-ink)" stroke-width="1" opacity="0" />
                @foreach ($series as $i => $d)
                    @php
                        $tip = $d['date']->translatedFormat('l, j M').'|'.$columns->map(fn ($c) => $c->name.': '.($d['counts'][$c->id] ?? 0))->join('|');
                    @endphp
                    <rect x="{{ $cx($i) - $plotW / max(1, $n - 1) / 2 }}" y="{{ $pt }}" width="{{ $plotW / max(1, $n - 1) }}" height="{{ $plotH }}" fill="transparent"
                          data-tip="{{ $tip }}" data-x="{{ $cx($i) }}" />
                @endforeach
            </svg>
        </div>
        <details class="mt-2 text-[13px]">
            <summary class="cursor-pointer text-ink-2 hover:text-ink">Lihat sebagai tabel</summary>
            <div class="mt-2 max-h-72 overflow-auto rounded-md border border-line">
                <table class="w-full text-left text-xs tnum">
                    <thead class="sticky top-0 bg-column"><tr>
                        <th scope="col" class="px-3 py-2 font-medium">Tanggal</th>
                        @foreach ($columns as $column)<th scope="col" class="px-3 py-2 font-medium">{{ $column->name }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                        @foreach (array_reverse($series) as $d)
                            <tr class="border-t border-line">
                                <th scope="row" class="px-3 py-1.5 font-normal">{{ tanggal($d['date']) }}</th>
                                @foreach ($columns as $column)<td class="px-3 py-1.5">{{ $d['counts'][$column->id] ?? 0 }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    </section>

    <div class="mt-10 grid gap-10 lg:grid-cols-2">
        {{-- Cycle time --}}
        <section aria-labelledby="ct">
            <h2 id="ct" class="text-[14px]">Berapa hari tiap kartu dikerjakan</h2>
            <p class="mt-1 text-[13px] text-ink-2">Satu titik per kartu selesai. Sumbu tegak: hari dari mulai dikerjakan sampai selesai.</p>
            @if ($cycleTimes->isEmpty())
                <p class="mt-4 rounded-md border border-dashed border-line-strong px-4 py-6 text-center text-[13px] text-muted">Belum ada kartu yang selesai dalam rentang ini.</p>
            @else
                <div class="relative mt-3" data-viz>
                    <svg viewBox="0 0 {{ $ctW }} {{ $H }}" class="h-auto w-full" role="img" aria-label="Sebaran cycle time {{ $cycleTimes->count() }} kartu. P50 {{ hari($summary['p50']) }}, P85 {{ hari($summary['p85']) }}.">
                        @for ($v = 0; $v <= $ctMax; $v += $ctStep)
                            <line x1="{{ $pl }}" x2="{{ $pl + $ctPlotW }}" y1="{{ $sy($v) }}" y2="{{ $sy($v) }}" stroke="var(--color-line)" stroke-width="1" />
                            <text x="{{ $pl - 6 }}" y="{{ $sy($v) + 4 }}" text-anchor="end" class="fill-muted text-[10px] tnum">{{ $v }}</text>
                        @endfor
                        @foreach ([['p50', 'P50', '4 4'], ['p85', 'P85', '']] as [$key, $name, $dash])
                            @if ($summary[$key] !== null)
                                <line x1="{{ $pl }}" x2="{{ $pl + $ctPlotW }}" y1="{{ $sy($summary[$key]) }}" y2="{{ $sy($summary[$key]) }}" stroke="var(--color-ink-2)" stroke-width="1.5" @if ($dash) stroke-dasharray="{{ $dash }}" @endif />
                                <text x="{{ $pl + $ctPlotW + 6 }}" y="{{ $sy($summary[$key]) + 4 }}" class="fill-ink-2 text-[10px]">{{ $name }} <tspan class="fill-ink font-semibold">{{ hari($summary[$key]) }}</tspan></text>
                            @endif
                        @endforeach
                        @foreach ($cycleTimes as $row)
                            @php
                                $x = $sx($row['card']->completed_at); $y = $sy($row['days']);
                                $tip = $project->key.'-'.$row['card']->number.' '.\Illuminate\Support\Str::limit($row['card']->title, 40).'|'.hari($row['days']).', selesai '.tanggal($row['card']->completed_at);
                            @endphp
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="4.5" fill="#2a78d6" stroke="#fff" stroke-width="2" />
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="10" fill="transparent" data-tip="{{ $tip }}" />
                        @endforeach
                    </svg>
                </div>
            @endif
        </section>

        {{-- Throughput --}}
        <section aria-labelledby="tp">
            <h2 id="tp" class="text-[14px]">Kartu selesai per minggu</h2>
            <p class="mt-1 text-[13px] text-ink-2">Minggu terakhir masih berjalan, jadi batangnya belum penuh.</p>
            <div class="relative mt-3" data-viz>
                <svg viewBox="0 0 {{ $tpW }} {{ $H }}" class="h-auto w-full" role="img"
                     aria-label="Throughput mingguan: {{ collect($throughput)->map(fn ($w) => $w['start']->translatedFormat('j M').' '.$w['count'])->join(', ') }}">
                    @for ($v = 0; $v <= $tpMax; $v += $tpStep)
                        <line x1="{{ $pl }}" x2="{{ $tpW - $tpPr }}" y1="{{ $ty($v) }}" y2="{{ $ty($v) }}" stroke="var(--color-line)" stroke-width="1" />
                        <text x="{{ $pl - 6 }}" y="{{ $ty($v) + 4 }}" text-anchor="end" class="fill-muted text-[10px] tnum">{{ $v }}</text>
                    @endfor
                    @foreach ($throughput as $i => $week)
                        @php
                            $x = $pl + $i * $slot + ($slot - $barW) / 2;
                            $y = $ty($week['count']);
                            $h = $pt + $plotH - $y;
                            $r = min(4, $h, $barW / 2);
                            $current = $i === $weeks - 1;
                        @endphp
                        @if ($week['count'] > 0)
                            <path d="M{{ $x }},{{ $pt + $plotH }} V{{ $y + $r }} Q{{ $x }},{{ $y }} {{ $x + $r }},{{ $y }} H{{ $x + $barW - $r }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + $r }} V{{ $pt + $plotH }} Z"
                                  fill="#2a78d6" @if ($current) fill-opacity="0.45" @endif />
                        @endif
                        @if ($i % max(1, (int) ceil($weeks / 7)) === 0 || $current)
                            <text x="{{ $x + $barW / 2 }}" y="{{ $H - 8 }}" text-anchor="middle" class="fill-muted text-[10px]">{{ $week['start']->translatedFormat('j M') }}</text>
                        @endif
                        <rect x="{{ $pl + $i * $slot }}" y="{{ $pt }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent"
                              data-tip="Minggu {{ $week['start']->translatedFormat('j M') }}{{ $current ? ' (berjalan)' : '' }}|{{ $week['count'] }} kartu selesai" />
                    @endforeach
                </svg>
            </div>
        </section>
    </div>

    <div class="mt-10">
        {{-- Umur kartu yang sedang dikerjakan --}}
        <section aria-labelledby="aging">
            <h2 id="aging" class="text-[14px]">Kartu yang sedang dikerjakan, dari yang paling tua</h2>
            <p class="mt-1 text-[13px] text-ink-2">
                Garis putus-putus P50, garis tegas P85. Kartu yang melewati P85 sudah lebih lama dari 85% kartu yang pernah selesai di sini.
            </p>
            @if ($aging->isEmpty())
                <p class="mt-4 rounded-md border border-dashed border-line-strong px-4 py-6 text-center text-[13px] text-muted">Tidak ada kartu yang sedang dikerjakan.</p>
            @else
                <ul class="mt-4 divide-y divide-line border-y border-line">
                    @foreach ($aging as $row)
                        @php [$statusLabel, $statusTone, $statusIcon] = $statusMeta[$row['status']]; @endphp
                        <li class="grid gap-x-3 gap-y-1.5 py-2.5 sm:grid-cols-[minmax(0,1fr)_8rem_9rem] sm:items-center">
                            <a href="{{ route('cards.show', [$project, $row['card']]) }}" class="min-w-0 no-underline">
                                <span class="block truncate text-[13px] font-medium text-ink">{{ $row['card']->title }}</span>
                                <span class="block truncate text-xs text-muted"><span class="font-mono">{{ $project->key }}-{{ $row['card']->number }}</span> · {{ $row['card']->column->name }} · {{ $row['card']->assignee?->name ?? 'belum ada pemegang' }}</span>
                            </a>
                            <div class="relative h-2.5 rounded-full bg-canvas" aria-hidden="true">
                                <div class="h-full rounded-full {{ $row['status'] === 'late' ? 'bg-red' : ($row['status'] === 'watch' ? 'bg-amber' : 'bg-ink-2') }}" style="width: {{ min(100, $row['days'] / $ageScale * 100) }}%"></div>
                                @if ($enough)
                                    <span class="absolute -top-1 h-[18px] border-l border-dashed border-ink" style="left: {{ $summary['p50'] / $ageScale * 100 }}%"></span>
                                    <span class="absolute -top-1 h-[18px] border-l-2 border-ink" style="left: {{ $summary['p85'] / $ageScale * 100 }}%"></span>
                                @endif
                            </div>
                            <p class="flex items-center justify-between gap-2 sm:justify-end">
                                <span class="whitespace-nowrap text-xs tnum text-ink-2">{{ hari($row['days']) }}</span>
                                <span class="badge {{ $statusTone }}"><x-icon :name="$statusIcon" :size="12" />{{ $statusLabel }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>

{{-- Tooltip bersama untuk semua grafik. --}}
<div id="viz-tip" class="pointer-events-none fixed z-50 hidden max-w-64 rounded-md bg-ink px-3 py-2 text-xs leading-relaxed text-white shadow-lg" role="tooltip"></div>

@push('scripts')
    <script>
        (() => {
            const tip = document.getElementById('viz-tip');
            document.querySelectorAll('[data-viz]').forEach((viz) => {
                const cross = viz.querySelector('[data-crosshair]');
                viz.addEventListener('pointermove', (e) => {
                    const target = e.target.closest('[data-tip]');
                    if (!target) { tip.classList.add('hidden'); if (cross) cross.setAttribute('opacity', 0); return; }
                    const [head, ...rows] = target.dataset.tip.split('|');
                    tip.innerHTML = '';
                    const strong = document.createElement('span');
                    strong.className = 'font-semibold';
                    strong.textContent = head;
                    tip.appendChild(strong);
                    rows.forEach((r) => { tip.appendChild(document.createElement('br')); tip.appendChild(document.createTextNode(r)); });
                    tip.classList.remove('hidden');
                    const x = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8);
                    const y = Math.min(e.clientY + 14, window.innerHeight - tip.offsetHeight - 8);
                    tip.style.left = x + 'px';
                    tip.style.top = y + 'px';
                    if (cross && target.dataset.x) { cross.setAttribute('x1', target.dataset.x); cross.setAttribute('x2', target.dataset.x); cross.setAttribute('opacity', 0.35); }
                });
                viz.addEventListener('pointerleave', () => { tip.classList.add('hidden'); if (cross) cross.setAttribute('opacity', 0); });
            });
        })();
    </script>
@endpush
</x-layouts.app>
