<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alur · papan kanban dengan batas WIP yang dijaga</title>
    <meta name="description" content="Papan kanban untuk tim kecil. Setiap kolom punya batas kartu yang benar-benar ditegakkan, dan riwayat perpindahan kartu diolah jadi cycle time, umur kartu, dan perkiraan kapan pekerjaan selesai.">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
</head>
{{--
    Arah landing (berbeda dari aplikasinya yang sengaja tenang):
    ENERGY 3 / RHYTHM 3 / MOTION 2. Pembuka dan penutup hitam penuh supaya produk (papan contoh) jadi
    satu-satunya bidang terang di layar pertama; bagian tengah bergantian putih dan abu dengan komposisi
    berbeda-beda. Judul memakai Bricolage Grotesque. Gerak hanya di dua tempat yang membuktikan sesuatu:
    papan contoh yang bisa diseret, dan balapan 10 kartu yang bisa diputar ulang.
--}}
<body class="bg-paper">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 btn-primary">Lompat ke konten</a>

    {{-- ================================================================ pembuka --}}
    <div class="bg-ink text-white">
        <header class="mx-auto flex max-w-6xl items-center gap-3 px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="no-underline"><x-logo invert /></a>
            <nav class="ml-auto flex items-center gap-1 sm:gap-2" aria-label="Akun">
                @if ($me)
                    <a href="{{ route('projects.index') }}" class="btn bg-white text-ink hover:bg-white/85">Kembali ke papan</a>
                @else
                <a href="{{ route('login') }}" class="btn text-white/80 hover:bg-white/10 hover:text-white">Masuk</a>
                @if ($hasDemo)
                    <form method="POST" action="{{ route('demo') }}">
                        @csrf
                        <button class="btn bg-white text-ink hover:bg-white/85">Coba papan demo</button>
                    </form>
                @else
                    <a href="{{ route('register') }}" class="btn bg-white text-ink hover:bg-white/85">Buat akun</a>
                @endif
                @endif
            </nav>
        </header>

        <section id="konten" class="mx-auto max-w-6xl px-4 pt-12 sm:px-6 sm:pt-20">
            <h1 class="max-w-4xl font-display text-[clamp(2.5rem,7vw,5.6rem)] font-extrabold leading-[0.95] tracking-[-0.03em] text-white">
                Papan kanban yang berani bilang
                <span class="mt-2 inline-flex translate-y-[-0.08em] items-center gap-[0.18em] rounded-[0.22em] bg-red-tint px-[0.28em] py-[0.04em] align-middle text-[0.62em] tracking-[-0.02em] text-red">
                    <x-icon name="blocked" class="h-[0.78em] w-[0.78em]" :size="48" /> penuh
                    <span class="font-sans text-[0.42em] font-semibold tracking-normal tnum">3/3</span>
                </span>
            </h1>
            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <p class="max-w-xl text-[16px] leading-relaxed text-white/75">
                    Setiap kolom punya batas berapa kartu boleh dikerjakan bersamaan. Kalau penuh, kartu baru ditolak
                    sampai ada yang selesai, walaupun dua orang menyeret di detik yang sama. Dari riwayat perpindahan kartu,
                    Alur menghitung kapan sisa pekerjaanmu kemungkinan beres.
                </p>
                <div class="flex flex-wrap gap-2">
                    @if ($me)
                        <a href="{{ route('projects.index') }}" class="btn bg-white px-5 text-ink hover:bg-white/85">Kembali ke papan</a>
                    @else
                        @if ($hasDemo)
                            <form method="POST" action="{{ route('demo') }}">
                                @csrf
                                <button class="btn bg-white px-5 text-ink hover:bg-white/85">Buka papan demo tim klinik</button>
                            </form>
                        @endif
                        <a href="{{ route('register') }}" class="btn border border-white/25 px-5 text-white hover:bg-white/10">Buat akun</a>
                    @endif
                </div>
            </div>
        </section>

        {{-- Jendela produk: menjorok keluar dari bidang hitam supaya jadi titik fokus layar pertama. --}}
        <div class="mx-auto max-w-6xl px-4 pt-14 sm:px-6">
            <div class="relative z-10 -mb-40 rounded-xl bg-canvas p-2 shadow-2xl shadow-black/40 ring-1 ring-white/10 sm:p-3" id="contoh">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-t-lg px-2 pb-2.5 pt-1.5">
                    <span class="flex gap-1.5" aria-hidden="true">
                        <x-kind kind="queue" :size="12" /><x-kind kind="active" :size="12" /><x-kind kind="done" :size="12" />
                    </span>
                    <p class="text-[13px] font-semibold text-ink">Coba seret kartu ke kolom Dikerjakan</p>
                    <p class="ml-auto text-xs text-muted">Papan contoh, tidak tersimpan</p>
                </div>
                <div class="grid gap-2.5 sm:grid-cols-3" data-demo-board>
                    @php
                        $demoColumns = [
                            ['queue', 'Siap dikerjakan', null, [['Halaman jadwal dokter', 'KLN-21', 2, 'Ambil antrean dari rumah'], ['Pengingat kontrol H‑1', 'KLN-24', 0, null]]],
                            ['active', 'Dikerjakan', 2, [['Login pakai OTP', 'KLN-18', 3, 'Ambil antrean dari rumah'], ['Layar TV ruang tunggu', 'KLN-19', 0, 'Layar TV ruang tunggu']]],
                            ['done', 'Selesai', null, [['Validasi NIK 16 digit', 'KLN-15', 0, null]]],
                        ];
                    @endphp
                    @foreach ($demoColumns as [$kind, $name, $limit, $cards])
                        <div class="flex flex-col rounded-lg bg-column ring-1 ring-line/60" data-demo-column data-name="{{ $name }}" data-limit="{{ $limit }}">
                            <div class="flex items-center gap-2 px-3 pb-2 pt-3">
                                <x-kind :kind="$kind" />
                                <span class="text-[13px] font-semibold text-ink">{{ $name }}</span>
                                <span class="badge tnum ml-auto bg-paper text-ink-2 ring-1 ring-line" data-count></span>
                            </div>
                            <p class="mx-2 mb-2 hidden items-center gap-1.5 rounded-md border border-dashed border-red/40 bg-red-tint px-2.5 py-2 text-xs text-red" data-full-note>
                                <x-icon name="blocked" :size="13" /> Penuh. Selesaikan satu kartu dulu.
                            </p>
                            <div class="flex min-h-28 flex-1 flex-col gap-2 px-2 pb-2" data-demo-list>
                                @foreach ($cards as [$title, $code, $priority, $epicName])
                                    <div class="kcard cursor-grab touch-none select-none">
                                        @if ($epicName)
                                            <span class="mb-1 inline-flex items-center gap-1 text-[11px] font-medium text-ink-2">
                                                <span class="h-2 w-2 rounded-[2px] {{ str_contains($epicName, 'TV') ? 'bg-violet' : 'bg-blue' }}" aria-hidden="true"></span>{{ $epicName }}
                                            </span>
                                        @endif
                                        <p class="text-[13px] font-medium leading-snug text-ink">{{ $title }}</p>
                                        <div class="mt-2 flex items-center gap-1.5">
                                            <x-priority :level="$priority" />
                                            <span class="font-mono text-[11px] text-muted">{{ $code }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="min-h-5 px-2 pb-1 pt-2.5 text-[13px] text-ink-2" role="status" aria-live="polite" data-demo-message>
                    Batas kolom Dikerjakan: 2 kartu. Coba seret satu lagi ke sana.
                </p>
            </div>
        </div>
    </div>

    <main>
        {{-- ================================================================ bukti --}}
        <section class="bg-paper pt-52">
            <div class="mx-auto grid max-w-6xl gap-12 px-4 pb-24 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
                <div class="lg:pt-6">
                    <p class="font-display text-[15px] font-bold text-red">Ditolak itu fitur</p>
                    <h2 class="mt-3 font-display text-[clamp(1.9rem,4vw,3rem)] font-bold leading-[1.05] tracking-[-0.025em]">
                        Sepuluh orang menyeret ke satu slot terakhir. Yang masuk tetap satu.
                    </h2>
                    <p class="mt-5 text-[15px] leading-relaxed text-ink-2">
                        Batas yang hanya dicek di browser mudah jebol: semua orang melihat satu slot kosong, semua menyeret.
                        Di Alur, setiap perubahan papan mengantre lewat satu kunci di database, jadi yang datang belakangan
                        selalu melihat kolom yang sudah terisi.
                    </p>
                    <p class="mt-3 text-[15px] leading-relaxed text-ink-2">
                        Diagram di samping adalah hasil tes otomatis yang menjalankan sepuluh proses PHP terpisah pada detik
                        yang sama, di Postgres sungguhan. Ganti ke "Kunci dicabut" untuk melihat yang terjadi tanpanya.
                    </p>
                </div>

                <div x-data="race()" x-init="play()" class="rounded-xl bg-canvas p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-md bg-paper p-0.5 ring-1 ring-line" role="group" aria-label="Kondisi tes">
                            <button type="button" class="rounded-[5px] px-3 py-1.5 text-[13px] font-medium max-sm:py-2.5" :class="locked ? 'bg-ink text-white' : 'text-ink-2 hover:text-ink'" :aria-pressed="locked" @click="locked = true; play()">Dengan kunci</button>
                            <button type="button" class="rounded-[5px] px-3 py-1.5 text-[13px] font-medium max-sm:py-2.5" :class="!locked ? 'bg-ink text-white' : 'text-ink-2 hover:text-ink'" :aria-pressed="!locked" @click="locked = false; play()">Kunci dicabut</button>
                        </div>
                        <button type="button" class="btn-ghost ml-auto" @click="play()">Putar ulang</button>
                    </div>

                    <div class="mt-4 grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-3 sm:gap-5">
                        <ol class="space-y-1.5" aria-label="Sepuluh permintaan serentak">
                            <template x-for="i in 10" :key="i">
                                <li class="flex h-8 items-center gap-2 rounded-md bg-paper px-2.5 text-xs ring-1 ring-line transition-all duration-300"
                                    :class="shown(i) ? (wins(i) ? 'translate-x-2 ring-green/40' : 'opacity-60') : ''">
                                    <span class="font-mono text-muted" x-text="'#' + i"></span>
                                    <span class="truncate text-ink-2">seret ke Dikerjakan</span>
                                    <span class="ml-auto shrink-0" x-show="shown(i)" x-cloak>
                                        <span x-show="wins(i)" class="badge bg-green-tint text-green">masuk</span>
                                        <span x-show="!wins(i)" class="badge bg-red-tint text-red">ditolak</span>
                                    </span>
                                </li>
                            </template>
                        </ol>

                        <div class="rounded-lg bg-column p-2 ring-1 transition-colors duration-300" :class="overflowing ? 'ring-red/50' : 'ring-line'">
                            <div class="flex items-center gap-2 px-1 pb-2 pt-1">
                                <x-kind kind="active" />
                                <span class="text-[13px] font-semibold">Dikerjakan</span>
                                <span class="badge tnum ml-auto" :class="overflowing ? 'bg-red-tint text-red' : (count >= 3 ? 'bg-amber-tint text-amber' : 'bg-paper text-ink-2 ring-1 ring-line')" x-text="count + '/3'"></span>
                            </div>
                            <div class="space-y-1.5">
                                <div class="rounded-md border border-line bg-paper px-2.5 py-1.5 text-xs">Kartu A <span class="text-muted">(sudah ada)</span></div>
                                <div class="rounded-md border border-line bg-paper px-2.5 py-1.5 text-xs">Kartu B <span class="text-muted">(sudah ada)</span></div>
                                <template x-for="i in entered" :key="'in' + i">
                                    <div class="rounded-md border px-2.5 py-1.5 text-xs" :class="i === 1 ? 'border-green/40 bg-green-tint text-green' : 'border-red/30 bg-red-tint text-red'"
                                         x-text="'Kartu dari #' + order[i - 1] + (i === 1 ? '' : ', melewati batas')"></div>
                                </template>
                                <div x-show="entered === 0" class="rounded-md border border-dashed border-line-strong px-2.5 py-1.5 text-xs text-muted">Slot terakhir</div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-[13px] text-ink-2" role="status" aria-live="polite" x-text="summary()"></p>
                </div>
            </div>
        </section>

        {{-- ================================================================ ke mana waktunya (data hidup) --}}
        @if ($live && $live['breakdown']['cards'] > 0)
            <section class="bg-canvas">
                <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                    <p class="font-display text-[15px] font-bold text-blue">Lama di mana?</p>
                    <h2 class="mt-3 max-w-3xl font-display text-[clamp(1.9rem,4vw,3rem)] font-bold leading-[1.05] tracking-[-0.025em]">
                        Bukan cuma "kartu ini lama". Lamanya di QA, di revisi, atau belum di-merge.
                    </h2>
                    <div class="mt-10 grid gap-10 lg:grid-cols-[1.4fr_1fr] lg:gap-14">
                        <div class="rounded-xl bg-paper p-5 ring-1 ring-line sm:p-6">
                            <p class="text-xs text-muted">Papan demo, {{ $live['breakdown']['cards'] }} kartu yang selesai dalam 90 hari terakhir</p>
                            <x-time-breakdown :breakdown="$live['breakdown']" :columns="$live['columns']" compact class="mt-4" />
                        </div>
                        <div class="space-y-4 text-[15px] leading-relaxed text-ink-2">
                            <p>Setiap perpindahan kartu dicatat dengan jamnya, jadi cycle time bisa dibelah per kolom.</p>
                            <p>Saat kartu dikembalikan, misalnya dari QA ke Dikerjakan, Alur menanyakan alasannya: bug, perbaikan dari review, atau kebutuhan berubah. Hari yang hilang karena revisi dihitung terpisah.</p>
                            <p>Kolom berjenis <span class="inline-flex items-center gap-1 font-medium text-ink"><x-kind kind="wait" :size="13" /> Menunggu</span>, seperti "Menunggu merge", menghitung waktu ketika pekerjaannya sudah beres tapi masih menunggu orang lain.</p>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ perkiraan (data hidup) --}}
        @if ($live && $live['forecast'] && $live['cycle']['count'] >= 5)
            @php
                $f = $live['forecast'];
                $dist = $f['distribution'];
                $minDay = (int) array_key_first($dist);
                $maxDay = (int) min(array_key_last($dist), $f['p95'] + 6);
                $peak = max($dist);
                $W = 1000; $H = 260; $pl = 8; $pr = 8; $pt = 36; $pb = 30;
                $span = max(1, $maxDay - $minDay + 1);
                $slot = ($W - $pl - $pr) / $span;
                $x = fn ($day) => $pl + ($day - $minDay) * $slot;
                $h = fn ($n) => ($n / $peak) * ($H - $pt - $pb);
            @endphp
            <section class="bg-paper">
                <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6">
                    <div class="grid gap-8 lg:grid-cols-[1fr_1.6fr] lg:items-end">
                        <div>
                            <p class="font-display text-[15px] font-bold text-amber">Kapan selesai?</p>
                            <h2 class="mt-3 font-display text-[clamp(1.9rem,4vw,3rem)] font-bold leading-[1.05] tracking-[-0.025em]">Jawabannya rentang, bukan satu tanggal.</h2>
                        </div>
                        <p class="text-[15px] leading-relaxed text-ink-2">
                            Papan demo masih punya {{ $live['remaining'] }} kartu yang belum selesai. Alur memutar 5.000 simulasi dari
                            kecepatan tim selama 6 minggu terakhir. Setiap batang di bawah adalah jumlah simulasi yang selesai di hari itu.
                            Angkanya dihitung ulang setiap kali halaman ini dibuka.
                        </p>
                    </div>

                    <div class="mt-10 rounded-xl bg-column p-4 ring-1 ring-line sm:p-6">
                        <p class="font-display text-[clamp(2.2rem,6vw,4.5rem)] font-extrabold leading-none tracking-[-0.03em] tnum">
                            {{ $f['p50'] }} <span class="text-[0.5em] font-bold text-muted">sampai</span> {{ $f['p85'] }}
                            <span class="text-[0.45em] font-bold tracking-[-0.01em] text-ink-2">hari lagi</span>
                        </p>
                        <p class="mt-2 text-[13px] text-ink-2">Separuh simulasi selesai dalam {{ $f['p50'] }} hari, 85% dalam {{ $f['p85'] }} hari.</p>
                        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="mt-4 h-auto w-full" role="img"
                             aria-label="Sebaran 5.000 simulasi: paling cepat {{ $minDay }} hari, separuhnya dalam {{ $f['p50'] }} hari, 85% dalam {{ $f['p85'] }} hari.">
                            @foreach ($dist as $day => $n)
                                @continue($day > $maxDay)
                                @php
                                    $bh = $h($n); $bx = $x($day) + 1; $bw = max(1, $slot - 2); $by = $H - $pb - $bh; $r = min(3, $bw / 2, $bh);
                                @endphp
                                <path d="M{{ $bx }},{{ $H - $pb }} V{{ $by + $r }} Q{{ $bx }},{{ $by }} {{ $bx + $r }},{{ $by }} H{{ $bx + $bw - $r }} Q{{ $bx + $bw }},{{ $by }} {{ $bx + $bw }},{{ $by + $r }} V{{ $H - $pb }} Z"
                                      fill="#2a78d6" @if ($day > $f['p85']) fill-opacity="0.35" @endif />
                            @endforeach
                            <line x1="{{ $pl }}" x2="{{ $W - $pr }}" y1="{{ $H - $pb }}" y2="{{ $H - $pb }}" stroke="var(--color-line-strong)" />
                            @foreach ([['p50', 'Separuh: '.$f['p50'].' hari', '4 4'], ['p85', '85%: '.$f['p85'].' hari', '']] as [$key, $text, $dash])
                                @php $mx = $x($f[$key]) + $slot / 2; @endphp
                                <line x1="{{ $mx }}" x2="{{ $mx }}" y1="{{ $pt - 18 }}" y2="{{ $H - $pb }}" stroke="var(--color-ink)" stroke-width="1.5" @if ($dash) stroke-dasharray="{{ $dash }}" @endif />
                                <text x="{{ $mx + 6 }}" y="{{ $pt - 8 }}" class="fill-ink text-[13px] font-semibold">{{ $text }}</text>
                            @endforeach
                            @for ($d = $minDay; $d <= $maxDay; $d++)
                                @if (($d - $minDay) % max(1, (int) ceil($span / 10)) === 0)
                                    <text x="{{ $x($d) + $slot / 2 }}" y="{{ $H - 10 }}" text-anchor="middle" class="fill-muted text-[12px] tnum">{{ $d }}</text>
                                @endif
                            @endfor
                        </svg>
                        <p class="mt-1 text-right text-xs text-muted">hari dari sekarang</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ epic (data hidup) --}}
        @if ($live && $live['epics']->isNotEmpty())
            <section class="bg-canvas">
                <div class="mx-auto grid max-w-6xl gap-10 px-4 py-24 sm:px-6 lg:grid-cols-[1.25fr_1fr] lg:gap-16">
                    <ul class="order-2 space-y-3 lg:order-1">
                        @foreach ($live['epics'] as $row)
                            @php
                                $dot = ['slate' => 'bg-slate', 'red' => 'bg-red', 'amber' => 'bg-amber', 'green' => 'bg-green', 'blue' => 'bg-blue', 'violet' => 'bg-violet'][$row['epic']->color] ?? 'bg-slate';
                            @endphp
                            <li class="rounded-xl bg-paper p-4 ring-1 ring-line">
                                <p class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="flex items-center gap-2 text-[14px] font-semibold"><span class="h-2.5 w-2.5 rounded-[3px] {{ $dot }}" aria-hidden="true"></span>{{ $row['epic']->name }}</span>
                                    <span class="text-xs text-muted tnum">{{ $row['done'] }} dari {{ $row['total'] }} kartu</span>
                                    @if ($row['epic']->target_on)
                                        <span class="ml-auto text-xs text-ink-2">Target {{ tanggal($row['epic']->target_on) }}</span>
                                    @endif
                                </p>
                                <x-epic-progress :row="$row" class="mt-3" />
                            </li>
                        @endforeach
                    </ul>
                    <div class="order-1 lg:order-2 lg:pt-4">
                        <p class="font-display text-[15px] font-bold text-green">Epic</p>
                        <h2 class="mt-3 font-display text-[clamp(1.9rem,4vw,3rem)] font-bold leading-[1.05] tracking-[-0.025em]">Target yang terlalu mepet ketahuan sebelum lewat.</h2>
                        <p class="mt-5 text-[15px] leading-relaxed text-ink-2">
                            Kelompokkan kartu ke dalam epic, beri tanggal target, dan Alur membandingkannya dengan perkiraan.
                            "Berisiko" artinya target hanya tercapai kalau seluruh tim fokus ke epic itu. Kalau skenario itu pun
                            lewat, targetnya perlu digeser atau isinya dikurangi.
                        </p>
                        <p class="mt-3 text-xs text-muted">Epic di samping diambil dari papan demo saat halaman ini dibuka.</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ peran --}}
        <section class="border-t border-line bg-paper">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-20 sm:px-6 lg:grid-cols-[1fr_2fr]">
                <div>
                    <h2 class="font-display text-[clamp(1.6rem,3vw,2.2rem)] font-bold leading-[1.1] tracking-[-0.02em]">Satu papan, tiga peran</h2>
                    <p class="mt-3 text-[15px] leading-relaxed text-ink-2">Undang orang lewat tautan yang berlaku 7 hari. Pemilik bisa mencabutnya kapan saja.</p>
                </div>
                <dl class="divide-y divide-line border-y border-line text-[15px]">
                    <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr]">
                        <dt class="font-semibold">Pemilik</dt>
                        <dd class="text-ink-2">Mengatur kolom dan batas WIP, label, anggota, dan tautan undangan.</dd>
                    </div>
                    <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr]">
                        <dt class="font-semibold">Anggota</dt>
                        <dd class="text-ink-2">Membuat epic dan kartu, memindah, memblokir, dan berkomentar. Kalau dua anggota mengedit kartu yang sama, simpanan kedua ditolak dan isiannya dikembalikan, tidak hilang diam-diam.</dd>
                    </div>
                    <div class="grid gap-1 py-4 sm:grid-cols-[9rem_1fr]">
                        <dt class="font-semibold">Pengamat</dt>
                        <dd class="text-ink-2">Melihat papan, epic, dan metriknya tanpa bisa mengubah apa pun. Cocok untuk klien atau atasan.</dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- ================================================================ penutup --}}
        <section class="bg-ink text-white">
            <div class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-20 sm:px-6 lg:flex-row lg:items-end lg:justify-between">
                <h2 class="max-w-2xl font-display text-[clamp(2rem,5vw,3.6rem)] font-extrabold leading-[1] tracking-[-0.03em] text-white">
                    Seret satu kartu ke kolom yang penuh. Lihat sendiri.
                </h2>
                <div class="flex flex-wrap gap-2">
                    @if ($me)
                        <a href="{{ route('projects.index') }}" class="btn bg-white px-5 text-ink hover:bg-white/85">Kembali ke papan</a>
                    @else
                        @if ($hasDemo)
                            <form method="POST" action="{{ route('demo') }}">
                                @csrf
                                <button class="btn bg-white px-5 text-ink hover:bg-white/85">Buka papan demo tim klinik</button>
                            </form>
                        @endif
                        <a href="{{ route('register') }}" class="btn border border-white/25 px-5 text-white hover:bg-white/10">Buat akun</a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-ink text-white/70">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 border-t border-white/10 px-4 py-6 text-[13px] sm:px-6">
            <p>Alur adalah proyek portofolio Dewi Nur Ayundari, dibuat dengan Laravel dan Postgres.</p>
            <a href="https://yunda-portfolio.my.id" class="text-white/85 underline hover:text-white">Portofolio</a>
            <a href="https://github.com/ayndri" class="text-white/85 underline hover:text-white">GitHub</a>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script>
        // Balapan: hasil tes RaceTest, diputar berurutan supaya kelihatan siapa yang menang.
        function race() {
            const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            return {
                locked: true,
                step: 0,
                order: [],
                timer: null,
                play() {
                    clearInterval(this.timer);
                    // Urutan kedatangan acak, seperti proses sungguhan; pemenangnya yang pertama tiba.
                    this.order = [...Array(10).keys()].map((i) => i + 1).sort(() => Math.random() - 0.5);
                    this.step = calm ? 10 : 0;
                    if (!calm) this.timer = setInterval(() => { if (++this.step >= 10) clearInterval(this.timer); }, 140);
                },
                shown(i) { return this.order.indexOf(i) < this.step; },
                wins(i) { return this.locked ? this.order[0] === i : true; },
                get entered() { return this.locked ? Math.min(this.step, 1) : this.step; },
                get count() { return 2 + this.entered; },
                get overflowing() { return this.count > 3; },
                summary() {
                    if (this.step < 10) return 'Sepuluh permintaan tiba hampir bersamaan...';
                    return this.locked
                        ? 'Dengan kunci: 1 masuk, 9 ditolak dengan pesan "kolom penuh". Isi kolom tetap 3/3.'
                        : 'Kunci dicabut: kesepuluhnya lolos pemeriksaan bersamaan. Isi kolom jadi 12/3.';
                },
            };
        }

        // Papan contoh: semuanya di browser, tidak ada yang dikirim ke server.
        (() => {
            const columns = [...document.querySelectorAll('[data-demo-column]')];
            const message = document.querySelector('[data-demo-message]');
            const board = document.querySelector('[data-demo-board]');

            const count = (col) => col.querySelectorAll('[data-demo-list] .kcard').length;
            const limit = (col) => Number(col.dataset.limit) || null;
            const isFull = (col) => limit(col) !== null && count(col) >= limit(col);

            const paint = () => columns.forEach((col) => {
                const badge = col.querySelector('[data-count]');
                const full = isFull(col);
                badge.textContent = limit(col) ? `${count(col)}/${limit(col)}` : count(col);
                badge.className = 'badge tnum ml-auto ' + (full ? 'bg-amber-tint text-amber' : 'bg-paper text-ink-2 ring-1 ring-line');
            });

            const showNotes = (on, from) => columns.forEach((col) => {
                const note = col.querySelector('[data-full-note]');
                const reject = on && isFull(col) && col !== from;
                note.classList.toggle('hidden', !reject);
                note.classList.toggle('flex', reject);
            });

            let from = null;
            columns.forEach((col) => Sortable.create(col.querySelector('[data-demo-list]'), {
                group: { name: 'demo', put: (to) => !isFull(to.el.closest('[data-demo-column]')) || to.el.closest('[data-demo-column]') === from },
                animation: 150,
                forceFallback: true,
                fallbackOnBody: true,
                onStart: () => { from = col; showNotes(true, col); board.classList.add('board-dragging'); },
                onEnd: (e) => {
                    showNotes(false);
                    board.classList.remove('board-dragging');
                    const target = e.to.closest('[data-demo-column]');
                    // Kolom penuh tidak pernah menerima kartunya, jadi cari kolom di bawah titik lepas.
                    const point = e.originalEvent?.changedTouches?.[0] ?? e.originalEvent;
                    const under = point && document.elementFromPoint(point.clientX, point.clientY)?.closest('[data-demo-column]');
                    if (target === from && under && under !== from && isFull(under)) {
                        message.textContent = `Ditolak: ${under.dataset.name} sudah penuh (${count(under)}/${limit(under)}). Pindahkan satu kartunya ke Selesai dulu.`;
                    } else if (target !== from) {
                        message.textContent = target.dataset.limit
                            ? `Masuk ke ${target.dataset.name}. Sekarang ${count(target)} dari ${limit(target)} slot terisi.`
                            : `Dipindah ke ${target.dataset.name}.` + (isFull(from) ? '' : ' Ada slot kosong lagi di kolom asalnya.');
                    }
                    from = null;
                    paint();
                },
            }));
            paint();
        })();
    </script>
</body>
</html>
