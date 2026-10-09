@props(['card', 'project', 'cycle'])
@php
    use App\Services\FlowMetrics;

    $age = $card->completed_at ? null : $card->ageInDays();
    $ageStatus = $age === null ? null : FlowMetrics::ageStatus($age, $cycle);
    $ageTone = ['late' => 'border-red/25 bg-red-tint text-red', 'watch' => 'border-amber/25 bg-amber-tint text-amber'][$ageStatus] ?? '';
    $ageNote = 'Dikerjakan sejak '.tanggal($card->started_at).' ('.hari($age).').'
        .($cycle['p85'] !== null && $cycle['count'] >= 5 ? ' 85% kartu proyek ini selesai dalam '.hari($cycle['p85']).'.' : '');

    $due = $card->due_on;
    $dueToday = $due && ! $card->completed_at && $due->isToday();
    $overdue = $card->isOverdue();
    $checklistTotal = $card->checklist_count ?? 0;
@endphp
<a href="{{ route('cards.show', [$project, $card]) }}"
   {{ $attributes->merge(['class' => 'kcard'.($card->isBlocked() ? ' border-t-2 border-t-red' : '')]) }}
   draggable="false"
   data-id="{{ $card->id }}"
   data-title="{{ \Illuminate\Support\Str::lower($card->title.' '.$project->key.'-'.$card->number) }}"
   data-assignee="{{ $card->assignee_id }}"
   data-labels="{{ $card->labels->pluck('id')->join(',') }}"
   data-epic="{{ $card->epic_id }}"
   data-blocked="{{ $card->isBlocked() ? 1 : 0 }}">
    @if ($card->epic)
        <x-epic-chip :epic="$card->epic" class="mb-1 max-w-full" />
    @endif
    <p class="text-[13px] font-medium leading-snug text-ink">{{ $card->title }}</p>
    <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted">
        <span class="font-mono text-[11px]">{{ $project->key }}-{{ $card->number }}</span>
        @foreach ($card->labels as $label)
            <x-label-chip :label="$label" class="py-0 text-[11px]" />
        @endforeach
    </p>

    @if ($card->isBlocked())
        <p class="mt-2 flex items-start gap-1.5 text-xs text-red">
            <x-icon name="blocked" :size="13" class="mt-px" />
            <span class="line-clamp-2"><span class="font-semibold">Terblokir:</span> {{ $card->blocked_reason }}</span>
        </p>
    @endif

    <div class="mt-2.5 flex items-center gap-1">
        <x-priority :level="$card->priority" />
        @if ($due && ! $card->completed_at)
            <span class="chip {{ $overdue || $dueToday ? 'border-red/25 bg-red-tint text-red' : '' }}" title="Tenggat {{ tanggal($due) }}">
                <x-icon name="calendar" :size="12" />
                {{ $dueToday ? 'Hari ini' : $due->translatedFormat('j M') }}
                @if ($overdue)<span class="sr-only">(lewat tenggat)</span>@endif
            </span>
        @endif
        @if ($age !== null)
            <span class="chip {{ $ageTone }}" title="{{ $ageNote }}">
                <x-icon name="hourglass" :size="12" />{{ $age < 1 ? 'baru' : floor($age).' hari' }}
                <span class="sr-only">{{ $ageNote }}</span>
            </span>
        @endif
        @if ($card->rework_count ?? 0)
            <span class="chip border-red/25 bg-red-tint text-red" title="Dikembalikan {{ $card->rework_count }} kali untuk revisi">
                <x-icon name="rework" :size="12" />{{ $card->rework_count }}
                <span class="sr-only">kali dikembalikan untuk revisi</span>
            </span>
        @endif
        @if ($checklistTotal)
            <span class="chip" title="Checklist {{ $card->checklist_done_count }} dari {{ $checklistTotal }}">
                <x-icon name="checklist" :size="12" />{{ $card->checklist_done_count }}/{{ $checklistTotal }}
            </span>
        @endif
        @if ($card->comments_count)
            <span class="chip" title="{{ $card->comments_count }} komentar">
                <x-icon name="comment" :size="12" />{{ $card->comments_count }}
            </span>
        @endif
        <x-avatar :user="$card->assignee" size="sm" class="ml-auto" />
    </div>
</a>
