<x-layouts.app :title="$epic->name.' · '.$project->name" :project="$project">
    @php
        $canWork = in_array($role, ['owner', 'member'], true);
        $dot = ['slate' => 'bg-slate', 'red' => 'bg-red', 'amber' => 'bg-amber', 'green' => 'bg-green', 'blue' => 'bg-blue', 'violet' => 'bg-violet'];
        $firstQueue = $project->columns->firstWhere('kind', 'queue') ?? $project->columns->first();
    @endphp
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6" x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }} }">
        <p class="flex items-center gap-2 text-xs text-muted">
            <a href="{{ route('epics.index', $project) }}" class="inline-flex items-center gap-1 text-ink-2 no-underline hover:text-ink"><x-icon name="chevron-left" :size="14" /> Epic</a>
        </p>

        <div class="mt-3 flex flex-wrap items-start gap-3" x-show="!editing">
            <h2 class="flex min-w-0 items-center gap-2.5 text-xl">
                <span class="h-3 w-3 shrink-0 rounded-[3px] {{ $dot[$epic->color] ?? 'bg-slate' }}" aria-hidden="true"></span>
                {{ $epic->name }}
            </h2>
            <div class="ml-auto flex gap-2">
                <a href="{{ route('projects.show', [$project, 'epic' => $epic->id]) }}" class="btn-secondary"><x-icon name="board" :size="14" /> Lihat di papan</a>
                @if ($canWork)
                    <button type="button" class="btn-ghost" @click="editing = true">Ubah</button>
                @endif
            </div>
        </div>
        <div x-show="!editing">
            @if ($epic->target_on)
                <p class="mt-1 text-[13px] text-ink-2">Target selesai {{ tanggal($epic->target_on) }}</p>
            @endif
            @if ($epic->description)
                <p class="prose-card mt-3 max-w-2xl text-[13px] leading-relaxed text-ink-2">{{ $epic->description }}</p>
            @endif
        </div>

        @if ($canWork)
            <div x-show="editing" x-cloak class="mt-3 rounded-lg bg-column p-4">
                <form method="POST" action="{{ route('epics.update', [$project, $epic]) }}" @keydown.escape="editing = false">
                    @csrf @method('PUT')
                    @include('epics._form')
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button class="btn-primary">Simpan</button>
                        <button type="button" class="btn-ghost" @click="editing = false">Batal</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('epics.destroy', [$project, $epic]) }}" class="mt-4 border-t border-line pt-4"
                      onsubmit="return confirm('Hapus epic {{ addslashes($epic->name) }}? Kartunya tetap ada di papan, hanya dilepas dari epic.')">
                    @csrf @method('DELETE')
                    <button class="btn-danger"><x-icon name="trash" :size="14" /> Hapus epic</button>
                    <span class="ml-2 text-xs text-muted">Kartunya tidak ikut terhapus.</span>
                </form>
            </div>
        @endif

        <section class="mt-6 rounded-lg border border-line p-4" aria-label="Progres epic">
            @if ($row && $row['total'])
                <x-epic-progress :row="$row" detailed />
            @else
                <p class="text-[13px] text-muted">Belum ada kartu di epic ini. Tambahkan di bawah, atau pilih epic ini dari halaman kartu yang sudah ada.</p>
            @endif
        </section>

        <div class="mt-8 space-y-6">
            @foreach ($byColumn as ['column' => $column, 'cards' => $cards])
                @continue($cards->isEmpty())
                <section aria-labelledby="ec-{{ $column->id }}">
                    <h3 id="ec-{{ $column->id }}" class="flex items-center gap-2 text-[13px]">
                        <x-kind :kind="$column->kind" /> {{ $column->name }} <span class="font-normal text-muted tnum">{{ $cards->count() }}</span>
                    </h3>
                    <ul class="mt-2 divide-y divide-line rounded-lg border border-line">
                        @foreach ($cards as $card)
                            <li>
                                <a href="{{ route('cards.show', [$project, $card]) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2.5 no-underline hover:bg-column">
                                    <span class="font-mono text-[11px] text-muted">{{ $project->key }}-{{ $card->number }}</span>
                                    <span class="min-w-0 flex-1 truncate text-[13px] {{ $card->archived_at ? 'text-muted' : 'text-ink' }}">
                                        @if ($card->isBlocked())<x-icon name="blocked" :size="13" class="mr-1 inline text-red" /><span class="sr-only">Terblokir:</span>@endif
                                        {{ $card->title }}
                                        @if ($card->archived_at)<span class="text-xs">(arsip)</span>@endif
                                    </span>
                                    <x-priority :level="$card->priority" />
                                    <x-avatar :user="$card->assignee" size="sm" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        @if ($canWork && $firstQueue)
            <form method="POST" action="{{ route('cards.store', $project) }}" class="mt-6 flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="epic_id" value="{{ $epic->id }}">
                <div class="min-w-0 flex-1">
                    <label for="epic-card" class="text-xs text-muted">Kartu baru di epic ini</label>
                    <input id="epic-card" name="title" required maxlength="200" class="input mt-1" placeholder="Judul kartu">
                </div>
                <div>
                    <label for="epic-card-col" class="text-xs text-muted">Masuk ke</label>
                    <select id="epic-card-col" name="column_id" class="input mt-1">
                        @foreach ($project->columns as $column)
                            <option value="{{ $column->id }}" @selected($column->is($firstQueue))>{{ $column->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-primary">Tambah kartu</button>
            </form>
        @endif
    </div>
</x-layouts.app>
