<x-layouts.app title="Beranda" heading="Beranda">
    <x-slot:actions>
        <a href="{{ route('projects.create') }}" class="btn-primary"><x-icon name="plus" /> Proyek baru</a>
    </x-slot:actions>

    <div class="mx-auto w-full max-w-5xl space-y-10 px-4 py-6 sm:px-6">
        <section aria-labelledby="tugasku">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="tugasku" class="text-[15px]">Sedang kamu pegang</h2>
                <p class="text-xs text-muted tnum">{{ $myCards->count() }} kartu</p>
            </div>

            @if ($myCards->isEmpty())
                <div class="mt-3 rounded-lg border border-dashed border-line-strong px-5 py-6 text-[13px] text-ink-2">
                    Tidak ada kartu yang sedang kamu pegang. Buka papan proyek, lalu tarik kartu dari kolom antrean ke kolom
                    yang sedang dikerjakan; kartu itu akan muncul di sini.
                </div>
            @else
                <ul class="mt-3 divide-y divide-line rounded-lg border border-line">
                    @foreach ($myCards as $card)
                        <li>
                            <a href="{{ route('cards.show', [$card->project, $card]) }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3 no-underline hover:bg-column">
                                <x-kind :kind="$card->column->kind" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13px] font-medium text-ink">
                                        @if ($card->isBlocked())<x-icon name="blocked" :size="13" class="mr-1 inline text-red" /><span class="sr-only">Terblokir:</span>@endif
                                        {{ $card->title }}
                                    </span>
                                    <span class="block text-xs text-muted">
                                        <span class="font-mono">{{ $card->project->key }}-{{ $card->number }}</span> · {{ $card->project->name }} · {{ $card->column->name }}
                                    </span>
                                </span>
                                <x-priority :level="$card->priority" />
                                @if ($card->due_on)
                                    <span class="chip {{ $card->isOverdue() || $card->due_on->isToday() ? 'border-red/25 bg-red-tint text-red' : '' }}">
                                        <x-icon name="calendar" :size="12" />{{ $card->due_on->isToday() ? 'Hari ini' : $card->due_on->translatedFormat('j M') }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="daftar-proyek">
            <h2 id="daftar-proyek" class="text-[15px]">Proyek</h2>

            @if ($projects->isEmpty())
                <div class="mt-3 rounded-lg border border-dashed border-line-strong px-5 py-8 text-center">
                    <p class="text-[13px] text-ink-2">Kamu belum tergabung di proyek mana pun.</p>
                    <p class="mt-1 text-[13px] text-muted">Buat proyek sendiri, atau buka tautan undangan yang dikirim timmu.</p>
                    <a href="{{ route('projects.create') }}" class="btn-primary mt-4"><x-icon name="plus" /> Buat proyek pertama</a>
                </div>
            @else
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($projects as $project)
                        <a href="{{ route('projects.show', $project) }}" class="group block rounded-lg border border-line p-4 no-underline transition-colors hover:border-line-strong hover:bg-column">
                            <div class="flex items-start gap-3">
                                <span class="grid h-9 min-w-9 place-items-center rounded-md bg-canvas px-1.5 font-mono text-[11px] font-bold text-ink-2">{{ $project->key }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[14px] font-semibold text-ink">{{ $project->name }}</p>
                                    <p class="text-xs text-muted">{{ \App\Models\ProjectMember::ROLE_LABELS[$project->pivot->role] }}</p>
                                </div>
                            </div>
                            @if ($project->description)
                                <p class="mt-3 line-clamp-2 text-[13px] leading-relaxed text-ink-2">{{ $project->description }}</p>
                            @endif
                            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-2 tnum">
                                <span>{{ $project->open_count }} kartu belum selesai</span>
                                @if ($project->blocked_count)
                                    <span class="font-medium text-red">{{ $project->blocked_count }} terblokir</span>
                                @endif
                            </p>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
