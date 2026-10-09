{{--
    Isi papan. Dikirim utuh bersama halaman, dan sendirian (?fragment=1) saat browser
    memperbarui papan karena ada perubahan dari orang lain.
--}}
@php $canWork = in_array($role, ['owner', 'member'], true); @endphp
<div id="board-columns" data-version="{{ $project->version }}" class="flex h-full min-h-0 gap-3 px-4 pb-4 sm:px-6">
    @foreach ($columns as $column)
        @php
            $count = $column->cards->count();
            $limit = $column->wip_limit;
            $full = $limit !== null && $count >= $limit;
            $over = $limit !== null && $count > $limit;
        @endphp
        <section class="group/col flex max-h-full w-[min(85vw,18rem)] shrink-0 snap-start flex-col rounded-lg bg-column"
                 data-column-id="{{ $column->id }}" data-column-name="{{ $column->name }}" data-limit="{{ $limit }}"
                 data-kind="{{ $column->kind }}" data-position="{{ $column->position }}"
                 @if ($full) data-full @endif
                 aria-labelledby="col-{{ $column->id }}">
            <header class="flex h-11 shrink-0 items-center gap-2 px-3 pt-1">
                <x-kind :kind="$column->kind" />
                <h2 id="col-{{ $column->id }}" class="truncate text-[13px] font-semibold">{{ $column->name }}</h2>
                @if ($limit === null)
                    <span class="text-xs font-medium text-muted tnum" title="{{ $count }} kartu, tanpa batas WIP">{{ $count }}</span>
                @else
                    <span class="badge tnum {{ $over ? 'bg-red-tint text-red' : ($full ? 'bg-amber-tint text-amber' : 'bg-paper text-ink-2 ring-1 ring-line') }}"
                          title="{{ $over ? "Melewati batas WIP: {$count} kartu, batasnya {$limit}." : ($full ? "Penuh: batas WIP {$limit} kartu." : "{$count} dari batas WIP {$limit} kartu.") }}">
                        {{ $count }}/{{ $limit }}
                        <span class="sr-only">{{ $full ? 'kolom penuh' : 'batas WIP' }}</span>
                    </span>
                @endif
                @if ($canWork && ! $full)
                    <button type="button" class="ml-auto grid h-7 w-7 place-items-center rounded-sm text-muted hover:bg-paper hover:text-ink max-sm:h-10 max-sm:w-10"
                            @click="openAdd({{ $column->id }})" title="Tambah kartu di {{ $column->name }}">
                        <x-icon name="plus" :size="15" /><span class="sr-only">Tambah kartu di {{ $column->name }}</span>
                    </button>
                @endif
            </header>

            {{-- Saat kartu diseret, kolom yang penuh menampilkan alasannya di sini. --}}
            @if ($full)
                <p class="full-note mx-3 mb-2 hidden items-center gap-1.5 rounded-md border border-dashed border-red/40 bg-red-tint px-2.5 py-2 text-xs text-red">
                    <x-icon name="blocked" :size="13" /> Penuh. Selesaikan satu kartu dulu.
                </p>
            @endif

            @if ($column->cards->isEmpty())
                <p class="empty-note px-4 pb-1 pt-2 text-center text-xs leading-relaxed text-muted">
                    @if ($column->kind === 'done')
                        Kartu yang selesai muncul di sini.
                    @elseif ($column->kind === 'active')
                        Belum ada yang dikerjakan.
                    @else
                        Kosong.
                    @endif
                </p>
            @endif

            <div class="card-list no-scrollbar flex min-h-16 flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2" data-column-id="{{ $column->id }}">
                @foreach ($column->cards as $card)
                    <x-card :card="$card" :project="$project" :cycle="$cycle" />
                @endforeach
            </div>

            @if ($canWork && ! $full)
                <form method="POST" action="{{ route('cards.store', $project) }}" class="px-2 pb-2"
                      x-show="adding === {{ $column->id }}" x-cloak @submit.prevent="submitAdd($event)">
                    @csrf
                    <input type="hidden" name="column_id" value="{{ $column->id }}">
                    <label for="add-{{ $column->id }}" class="sr-only">Judul kartu baru di {{ $column->name }}</label>
                    <textarea id="add-{{ $column->id }}" name="title" rows="2" maxlength="200" required
                              class="input resize-none" placeholder="Judul kartu, lalu Enter"
                              @keydown.enter.prevent="$el.form.requestSubmit()" @keydown.escape.stop="adding = null"></textarea>
                    <div class="mt-2 flex items-center gap-2">
                        <button class="btn-primary">Tambah</button>
                        <button type="button" class="btn-ghost" @click="adding = null">Batal</button>
                    </div>
                </form>
            @endif
        </section>
    @endforeach
</div>
