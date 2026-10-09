<x-layouts.app :title="'Arsip · '.$project->name" :project="$project">
    @php
        $canWork = in_array(auth()->user()->roleIn($project), ['owner', 'member'], true);
    @endphp
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6">
        <p class="text-[13px] text-ink-2">Kartu arsip tidak tampil di papan, tetapi tetap ikut dihitung di metrik. Kartu yang diarsipkan dari kolom selesai tetap terhitung selesai.</p>

        @if ($cards->isEmpty())
            <p class="mt-6 rounded-lg border border-dashed border-line-strong px-5 py-8 text-center text-[13px] text-muted">
                Arsip kosong. Kartu yang sudah lama selesai bisa diarsipkan dari halaman kartunya supaya papan tetap ringkas.
            </p>
        @else
            <ul class="mt-4 divide-y divide-line rounded-lg border border-line">
                @foreach ($cards as $card)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <x-kind :kind="$card->column->kind" />
                        <a href="{{ route('cards.show', [$project, $card]) }}" class="min-w-0 flex-1 no-underline">
                            <span class="block truncate text-[13px] font-medium text-ink">{{ $card->title }}</span>
                            <span class="block text-xs text-muted">
                                <span class="font-mono">{{ $project->key }}-{{ $card->number }}</span> · dari {{ $card->column->name }} · diarsipkan {{ tanggal($card->archived_at) }}
                            </span>
                        </a>
                        @if ($canWork)
                            <form method="POST" action="{{ route('cards.restore', [$project, $card]) }}">
                                @csrf
                                <button class="btn-secondary">Kembalikan</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $cards->links() }}</div>
        @endif
    </div>
</x-layouts.app>
