@php
    $canWork = in_array($role, ['owner', 'member'], true);
    $age = $card->ageInDays();
    $workers = $project->members->filter(fn ($m) => $m->pivot->role !== 'viewer');
    $selectedLabels = old('labels', $card->labels->pluck('id')->all());
    $doneCount = $card->checklist->where('done', true)->count();
@endphp
<div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
    {{-- Kiri: isi kartu, checklist, diskusi. --}}
    <div class="min-w-0 space-y-8">
        <div>
            <p class="flex flex-wrap items-center gap-2 text-xs text-muted">
                <a href="{{ route('projects.show', $project) }}" class="inline-flex items-center gap-1 text-ink-2 no-underline hover:text-ink">
                    <x-icon name="chevron-left" :size="14" /> Papan
                </a>
                <span aria-hidden="true">/</span>
                <span class="font-mono">{{ $project->key }}-{{ $card->number }}</span>
                @if ($card->archived_at)
                    <span class="badge bg-slate-tint text-slate">Diarsipkan {{ tanggal($card->archived_at) }}</span>
                @endif
            </p>

            @if ($card->isBlocked())
                <div class="mt-4 flex flex-wrap items-start gap-3 rounded-md border border-red/25 bg-red-tint px-4 py-3 text-[13px] text-red">
                    <x-icon name="blocked" class="mt-px" />
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">Terblokir sejak {{ $card->blocked_at->diffForHumans() }}</p>
                        <p class="mt-0.5">{{ $card->blocked_reason }}</p>
                    </div>
                    @if ($canWork)
                        <form method="POST" action="{{ route('cards.unblock', [$project, $card]) }}">
                            @csrf @method('DELETE')
                            <button class="btn-secondary">Lepas blokir</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($canWork)
                <form id="card-form" method="POST" action="{{ route('cards.update', [$project, $card]) }}" class="mt-4 space-y-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="lock_version" value="{{ old('lock_version', $card->lock_version) }}">
                    <div>
                        <label for="title" class="sr-only">Judul</label>
                        <textarea id="title" name="title" rows="1" required maxlength="200"
                                  class="block w-full resize-none rounded-sm border border-transparent bg-transparent px-2 py-1 -mx-2 text-xl font-semibold leading-snug tracking-tight hover:border-line focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/20 field-sizing-content">{{ old('title', $card->title) }}</textarea>
                        @error('title')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="description" class="label">Deskripsi</label>
                        <textarea id="description" name="description" rows="5" maxlength="5000" class="input field-sizing-content min-h-28"
                                  placeholder="Apa yang harus terjadi supaya kartu ini dianggap selesai?">{{ old('description', $card->description) }}</textarea>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button class="btn-primary">Simpan perubahan</button>
                        <p class="text-xs text-muted">Epic, pemegang, prioritas, tenggat, dan label di kanan ikut tersimpan.</p>
                    </div>
                </form>
            @else
                <h2 class="mt-4 text-xl leading-snug">{{ $card->title }}</h2>
                <div class="prose-card mt-3 text-[13px] leading-relaxed text-ink-2">{{ $card->description ?: 'Tanpa deskripsi.' }}</div>
            @endif
        </div>

        @include('cards._journey')

        {{-- Checklist --}}
        <section aria-labelledby="checklist">
            <div class="flex items-baseline justify-between">
                <h2 id="checklist" class="text-[14px]">Checklist</h2>
                @if ($card->checklist->isNotEmpty())
                    <span class="text-xs text-muted tnum">{{ $doneCount }} dari {{ $card->checklist->count() }}</span>
                @endif
            </div>
            @if ($card->checklist->isNotEmpty())
                <div class="mt-2 h-1 overflow-hidden rounded-full bg-canvas" aria-hidden="true">
                    <div class="h-full rounded-full bg-green" style="width: {{ round($doneCount / $card->checklist->count() * 100) }}%"></div>
                </div>
            @endif
            <ul class="mt-3 space-y-1">
                @foreach ($card->checklist as $item)
                    <li class="group flex items-center gap-2.5 rounded-sm px-1 py-1 hover:bg-column">
                        @if ($canWork)
                            <form method="POST" action="{{ route('cards.checklist.toggle', [$project, $card, $item]) }}" class="contents">
                                @csrf @method('PATCH')
                                <button class="grid h-5 w-5 shrink-0 place-items-center rounded-[5px] border {{ $item->done ? 'border-green bg-green text-white' : 'border-line-strong bg-paper' }} max-sm:h-7 max-sm:w-7"
                                        aria-label="{{ $item->done ? 'Tandai belum' : 'Tandai selesai' }}: {{ $item->body }}">
                                    @if ($item->done)<x-icon name="check" :size="13" />@endif
                                </button>
                            </form>
                        @else
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-[5px] border {{ $item->done ? 'border-green bg-green text-white' : 'border-line-strong' }}">
                                @if ($item->done)<x-icon name="check" :size="13" />@endif
                            </span>
                        @endif
                        <span class="flex-1 text-[13px] {{ $item->done ? 'text-muted line-through' : '' }}">{{ $item->body }}</span>
                        @if ($canWork)
                            <form method="POST" action="{{ route('cards.checklist.destroy', [$project, $card, $item]) }}">
                                @csrf @method('DELETE')
                                <button class="grid h-7 w-7 place-items-center rounded-sm text-muted opacity-0 hover:bg-paper hover:text-red focus:opacity-100 group-hover:opacity-100 max-sm:opacity-100"
                                        aria-label="Hapus: {{ $item->body }}"><x-icon name="x" :size="14" /></button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($canWork)
                <form method="POST" action="{{ route('cards.checklist.store', [$project, $card]) }}" class="mt-2 flex gap-2">
                    @csrf
                    <label for="checklist-baru" class="sr-only">Butir checklist baru</label>
                    <input id="checklist-baru" name="body" required maxlength="200" class="input" placeholder="Tambah butir, mis. Uji di Android 10">
                    <button class="btn-secondary shrink-0">Tambah</button>
                </form>
            @elseif ($card->checklist->isEmpty())
                <p class="mt-2 text-[13px] text-muted">Tidak ada checklist.</p>
            @endif
        </section>

        {{-- Diskusi dan riwayat --}}
        <section aria-labelledby="diskusi">
            <h2 id="diskusi" class="text-[14px]">Diskusi</h2>
            <div class="mt-3 space-y-4">
                @forelse ($card->comments as $comment)
                    <div class="flex gap-2.5">
                        <x-avatar :user="$comment->user" size="sm" class="mt-0.5" />
                        <div class="min-w-0 flex-1 rounded-md border border-line px-3 py-2">
                            <p class="text-xs"><span class="font-semibold">{{ $comment->user->name }}</span>
                                <span class="text-muted" title="{{ tanggal($comment->created_at, true) }}">· {{ $comment->created_at->diffForHumans() }}</span></p>
                            <p class="prose-card mt-1 text-[13px] leading-relaxed">{{ $comment->body }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-[13px] text-muted">Belum ada komentar.</p>
                @endforelse
            </div>
            @if ($canWork)
                <form method="POST" action="{{ route('cards.comment', [$project, $card]) }}" class="mt-4">
                    @csrf
                    <label for="komentar" class="sr-only">Tulis komentar</label>
                    <textarea id="komentar" name="body" rows="2" required maxlength="3000" class="input" placeholder="Tulis komentar"></textarea>
                    <button class="btn-secondary mt-2">Kirim komentar</button>
                </form>
            @endif

            <details class="mt-8 group/riwayat">
                <summary class="flex cursor-pointer list-none items-center gap-1.5 text-[13px] font-medium text-ink-2 hover:text-ink [&::-webkit-details-marker]:hidden">
                    <x-icon name="chevron-down" :size="14" class="transition-transform group-open/riwayat:rotate-180" />
                    Riwayat kartu ({{ $card->activities->count() }})
                </summary>
                <div class="mt-3 space-y-3 border-l border-line pl-4">
                    @foreach ($card->activities as $activity)
                        <x-activity-line :activity="$activity" :project="$project" />
                    @endforeach
                </div>
            </details>
        </section>
    </div>

    {{-- Kanan: status dan atribut. --}}
    <aside class="space-y-6 lg:border-l lg:border-line lg:pl-6" aria-label="Atribut kartu">
        <section>
            <h2 class="text-xs font-medium text-muted">Status</h2>
            <p class="mt-2 flex items-center gap-2 text-[14px] font-semibold">
                <x-kind :kind="$card->column->kind" /> {{ $card->column->name }}
            </p>
            <p class="mt-1 text-xs text-muted">Di kolom ini sejak {{ $card->column_entered_at->diffForHumans() }}</p>

            @if ($canWork && ! $card->archived_at)
                {{-- Pengganti seret-lepas untuk keyboard dan layar sentuh kecil. Kalau tujuannya berarti revisi,
                     form ini menanyakan alasannya, sama seperti dialog di papan. --}}
                @php
                    $current = $card->column;
                    $reworkTargets = $project->columns->filter(fn ($c) => \App\Services\Board::isRework($current, $c))->pluck('id')->all();
                @endphp
                <form method="POST" action="{{ route('cards.move', [$project, $card]) }}" class="mt-3 space-y-2"
                      x-data="{ target: '{{ $card->column_id }}', rework: @js(array_map('strval', $reworkTargets)) }">
                    @csrf
                    <div class="flex gap-2">
                        <label for="pindah" class="sr-only">Pindahkan ke kolom</label>
                        <select id="pindah" name="column_id" class="input" x-model="target">
                            @foreach ($project->columns as $column)
                                <option value="{{ $column->id }}" @selected($column->id === $card->column_id)>{{ $column->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn-secondary shrink-0">Pindahkan</button>
                    </div>
                    <div x-show="rework.includes(target)" x-cloak class="space-y-2 rounded-md border border-red/20 bg-red-tint/50 p-2.5">
                        <label for="alasan" class="text-xs font-medium text-red">Ini dihitung revisi. Kenapa dikembalikan?</label>
                        <select id="alasan" name="reason" class="input" :disabled="!rework.includes(target)">
                            @foreach (\App\Models\CardMove::REASONS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <label for="catatan" class="sr-only">Catatan revisi</label>
                        <input id="catatan" name="note" maxlength="300" class="input" placeholder="Catatan singkat (opsional)" :disabled="!rework.includes(target)">
                    </div>
                </form>
            @endif
        </section>

        <section class="space-y-1.5 text-[13px]">
            <h2 class="text-xs font-medium text-muted">Waktu</h2>
            <p class="flex justify-between gap-3"><span class="text-ink-2">Dibuat</span><span class="tnum">{{ tanggal($card->created_at) }}</span></p>
            <p class="flex justify-between gap-3"><span class="text-ink-2">Mulai dikerjakan</span><span class="tnum">{{ tanggal($card->started_at) }}</span></p>
            <p class="flex justify-between gap-3"><span class="text-ink-2">Selesai</span><span class="tnum">{{ tanggal($card->completed_at) }}</span></p>
            @if ($age !== null)
                @php
                    $tone = ['late' => 'border-red/25 bg-red-tint text-red', 'watch' => 'border-amber/25 bg-amber-tint text-amber'][$ageStatus] ?? 'border-line bg-column text-ink-2';
                @endphp
                <div class="mt-3 rounded-md border px-3 py-2.5 text-xs leading-relaxed {{ $card->completed_at ? 'border-green/25 bg-green-tint text-green' : $tone }}">
                    @if ($card->completed_at)
                        Selesai dalam <span class="font-semibold">{{ hari($age) }}</span>.
                    @else
                        Sudah dikerjakan <span class="font-semibold">{{ hari($age) }}</span>.
                        @if ($ageStatus === 'late')
                            Lebih lama dari 85% kartu yang pernah selesai di proyek ini ({{ hari($cycle['p85']) }}). Perlu dibantu atau dipecah?
                        @elseif ($ageStatus === 'watch')
                            Sudah melewati separuh kartu yang selesai ({{ hari($cycle['p50']) }}); 85% selesai dalam {{ hari($cycle['p85']) }}.
                        @elseif ($ageStatus === 'fresh')
                            Separuh kartu di proyek ini selesai dalam {{ hari($cycle['p50']) }}.
                        @else
                            Perbandingan muncul setelah proyek punya minimal 5 kartu selesai.
                        @endif
                    @endif
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <label for="epic_id" class="text-xs font-medium text-muted">Epic</label>
                @if ($canWork)
                    <select id="epic_id" name="epic_id" form="card-form" class="input mt-1.5">
                        <option value="">Tanpa epic</option>
                        @foreach ($project->epics as $epic)
                            <option value="{{ $epic->id }}" @selected((string) old('epic_id', $card->epic_id) === (string) $epic->id)>{{ $epic->name }}</option>
                        @endforeach
                    </select>
                @elseif ($card->epic)
                    <p class="mt-1.5"><a href="{{ route('epics.show', [$project, $card->epic]) }}" class="no-underline"><x-epic-chip :epic="$card->epic" class="text-[13px]" /></a></p>
                @else
                    <p class="mt-1.5 text-[13px]">Tanpa epic</p>
                @endif
                @if ($canWork && $card->epic)
                    <a href="{{ route('epics.show', [$project, $card->epic]) }}" class="mt-1 inline-block text-xs text-ink-2 underline">Lihat epic {{ $card->epic->name }}</a>
                @endif
            </div>
            <div>
                <label for="assignee_id" class="text-xs font-medium text-muted">Pemegang</label>
                @if ($canWork)
                    <select id="assignee_id" name="assignee_id" form="card-form" class="input mt-1.5">
                        <option value="">Belum ada</option>
                        @foreach ($workers as $member)
                            <option value="{{ $member->id }}" @selected((string) old('assignee_id', $card->assignee_id) === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="mt-1.5 flex items-center gap-2 text-[13px]"><x-avatar :user="$card->assignee" size="sm" /> {{ $card->assignee?->name ?? 'Belum ada' }}</p>
                @endif
            </div>
            <div>
                <label for="priority" class="text-xs font-medium text-muted">Prioritas</label>
                @if ($canWork)
                    <select id="priority" name="priority" form="card-form" class="input mt-1.5">
                        @foreach (\App\Models\Card::PRIORITY_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected((int) old('priority', $card->priority) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="mt-1.5 text-[13px]">{{ \App\Models\Card::PRIORITY_LABELS[$card->priority] }}</p>
                @endif
            </div>
            <div>
                <label for="due_on" class="text-xs font-medium text-muted">Tenggat</label>
                @if ($canWork)
                    <input id="due_on" name="due_on" type="date" form="card-form" value="{{ old('due_on', $card->due_on?->toDateString()) }}" class="input mt-1.5">
                    @error('due_on')<p class="field-error">{{ $message }}</p>@enderror
                @else
                    <p class="mt-1.5 text-[13px]">{{ $card->due_on ? tanggal($card->due_on) : 'Tanpa tenggat' }}</p>
                @endif
            </div>
            <fieldset>
                <legend class="text-xs font-medium text-muted">Label</legend>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @forelse ($project->labels as $label)
                        @if ($canWork)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="labels[]" value="{{ $label->id }}" form="card-form" class="peer sr-only" @checked(in_array($label->id, $selectedLabels))>
                                <x-label-chip :label="$label" class="opacity-45 ring-1 ring-transparent peer-checked:opacity-100 peer-checked:ring-current peer-focus-visible:outline-2 peer-focus-visible:outline-accent" />
                            </label>
                        @elseif (in_array($label->id, $selectedLabels))
                            <x-label-chip :label="$label" />
                        @endif
                    @empty
                        <p class="text-[13px] text-muted">Proyek ini belum punya label.</p>
                    @endforelse
                </div>
            </fieldset>
        </section>

        @if ($canWork)
            <section class="space-y-2 border-t border-line pt-5">
                @if (! $card->isBlocked() && ! $card->archived_at)
                    <details>
                        <summary class="btn-secondary w-full cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <x-icon name="blocked" /> Tandai terblokir
                        </summary>
                        <form method="POST" action="{{ route('cards.block', [$project, $card]) }}" class="mt-2">
                            @csrf
                            <label for="reason" class="label">Apa yang ditunggu?</label>
                            <textarea id="reason" name="reason" rows="2" required maxlength="300" class="input" placeholder="Mis. menunggu akses API dari vendor"></textarea>
                            @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                            <button class="btn-primary mt-2 w-full">Tandai terblokir</button>
                        </form>
                    </details>
                @endif
                @if ($card->archived_at)
                    <form method="POST" action="{{ route('cards.restore', [$project, $card]) }}">
                        @csrf
                        <button class="btn-secondary w-full">Kembalikan ke papan</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('cards.archive', [$project, $card]) }}">
                        @csrf
                        <button class="btn-ghost w-full"><x-icon name="archive" /> Arsipkan</button>
                    </form>
                    <p class="text-xs leading-relaxed text-muted">Kartu arsip hilang dari papan tapi tetap dihitung di metrik dan bisa dikembalikan.</p>
                @endif
            </section>
        @endif

        <p class="text-xs text-muted">Dibuat oleh {{ $card->creator->name }}</p>
    </aside>
</div>
