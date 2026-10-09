<x-layouts.app :title="'Aktivitas · '.$project->name" :project="$project">
    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6">
        @if ($activities->isEmpty())
            <p class="rounded-lg border border-dashed border-line-strong px-5 py-8 text-center text-[13px] text-muted">
                Belum ada aktivitas. Semua perubahan kartu, kolom, dan anggota akan tercatat di sini.
            </p>
        @else
            @php
                $byDay = $activities->getCollection()->groupBy(fn ($a) => $a->created_at->toDateString());
            @endphp
            <div class="space-y-8">
                @foreach ($byDay as $date => $items)
                    <section>
                        <h2 class="sticky top-0 z-10 bg-paper py-1.5 text-xs font-medium text-muted">
                            {{ \Illuminate\Support\Carbon::parse($date)->isToday() ? 'Hari ini' : (\Illuminate\Support\Carbon::parse($date)->isYesterday() ? 'Kemarin' : \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y')) }}
                        </h2>
                        <div class="mt-2 space-y-3">
                            @foreach ($items as $activity)
                                <x-activity-line :activity="$activity" :project="$project" with-card />
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="mt-8">{{ $activities->links() }}</div>
        @endif
    </div>
</x-layouts.app>
