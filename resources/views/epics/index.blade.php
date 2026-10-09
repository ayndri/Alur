<x-layouts.app :title="'Epic · '.$project->name" :project="$project">
    @php
        $canWork = in_array($role, ['owner', 'member'], true);
        $dot = ['slate' => 'bg-slate', 'red' => 'bg-red', 'amber' => 'bg-amber', 'green' => 'bg-green', 'blue' => 'bg-blue', 'violet' => 'bg-violet'];
    @endphp
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6" x-data="{ creating: {{ $errors->any() ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="max-w-2xl text-[13px] leading-relaxed text-ink-2">
                Epic mengelompokkan kartu yang melayani satu tujuan, supaya kelihatan seberapa jauh tujuan itu dan kapan kira-kira selesai.
                @if ($unassigned)
                    <a href="{{ route('projects.show', [$project, 'epic' => 'none']) }}" class="whitespace-nowrap text-ink underline">{{ $unassigned }} kartu belum masuk epic</a>.
                @endif
            </p>
            @if ($canWork)
                <button type="button" class="btn-primary" @click="creating = !creating; $nextTick(() => document.getElementById('epic-name')?.focus())" x-show="!creating">
                    <x-icon name="plus" /> Epic baru
                </button>
            @endif
        </div>

        @if ($canWork)
            <form method="POST" action="{{ route('epics.store', $project) }}" class="mt-4 rounded-lg bg-column p-4" x-show="creating" x-cloak @keydown.escape="creating = false">
                @csrf
                @include('epics._form')
                <div class="mt-3 flex gap-2">
                    <button class="btn-primary">Buat epic</button>
                    <button type="button" class="btn-ghost" @click="creating = false">Batal</button>
                </div>
            </form>
        @endif

        @if ($rows->isEmpty())
            <div class="mt-6 rounded-lg border border-dashed border-line-strong px-5 py-8 text-center text-[13px] text-ink-2" x-show="!creating">
                <p>Proyek ini belum punya epic.</p>
                <p class="mt-1 text-muted">Contoh epic: "Layar TV ruang tunggu" atau "Rilis ke Play Store". Setelah dibuat, pilih epicnya dari halaman kartu.</p>
            </div>
        @else
            <ul class="mt-6 space-y-3">
                @foreach ($rows as $row)
                    @php $epic = $row['epic']; @endphp
                    <li class="rounded-lg border border-line p-4 transition-colors hover:border-line-strong">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <a href="{{ route('epics.show', [$project, $epic]) }}" class="flex min-w-0 items-center gap-2 text-[14px] font-semibold text-ink no-underline hover:underline">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-[3px] {{ $dot[$epic->color] ?? 'bg-slate' }}" aria-hidden="true"></span>
                                <span class="truncate">{{ $epic->name }}</span>
                            </a>
                            <span class="text-xs text-muted tnum">{{ $row['done'] }} dari {{ $row['total'] }} kartu selesai</span>
                            @if ($epic->target_on)
                                <span class="ml-auto inline-flex items-center gap-1 text-xs text-ink-2"><x-icon name="calendar" :size="12" />Target {{ tanggal($epic->target_on) }}</span>
                            @endif
                        </div>
                        @if ($epic->description)
                            <p class="mt-1.5 line-clamp-2 text-[13px] text-ink-2">{{ $epic->description }}</p>
                        @endif
                        @if ($row['total'])
                            <x-epic-progress :row="$row" class="mt-3" />
                        @else
                            <p class="mt-3 text-xs text-muted">Belum ada kartu di epic ini.</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
