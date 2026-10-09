@props(['activity', 'project', 'withCard' => false])
@php
    $d = $activity->data ?? [];
    $fields = [
        'title' => 'judul', 'description' => 'deskripsi', 'priority' => 'prioritas',
        'assignee_id' => 'pemegang', 'due_on' => 'tenggat', 'labels' => 'label', 'epic_id' => 'epic',
    ];
    $card = $withCard && $activity->card
        ? '<a href="'.e(route('cards.show', [$project, $activity->card])).'" class="font-medium text-ink underline decoration-line-strong hover:decoration-ink">'
            .e($project->key.'-'.$activity->card->number.' '.\Illuminate\Support\Str::limit($activity->card->title, 50)).'</a>'
        : null;
    $on = $card ? ' '.$card : '';

    $text = match ($activity->type) {
        'created' => 'membuat'.($card ? $on : ' kartu ini').' di '.e($d['column'] ?? ''),
        'moved' => ! empty($d['rework'])
            ? '<span class="font-medium text-red">mengembalikan</span>'.($card ? $on : '').' dari '.e($d['from'] ?? '').' ke '.e($d['to'] ?? '')
                .(isset($d['reason']) ? ': '.e(lcfirst(\App\Models\CardMove::REASONS[$d['reason']] ?? $d['reason'])) : '')
                .(isset($d['note']) ? '. "'.e($d['note']).'"' : '')
            : 'memindahkan'.($card ? $on : '').' dari '.e($d['from'] ?? '').' ke <span class="font-medium text-ink">'.e($d['to'] ?? '').'</span>',
        'edited' => 'mengubah '.e(collect($d['fields'] ?? [])->map(fn ($f) => $fields[$f] ?? $f)->join(', ', ' dan ')).$on,
        'blocked' => 'menandai'.($card ? $on : '').' <span class="font-medium text-red">terblokir</span>: '.e($d['reason'] ?? ''),
        'unblocked' => 'melepas blokir'.$on.' setelah '.e(hari(($d['hours'] ?? 0) / 24)),
        'archived' => 'mengarsipkan'.($card ?: ' kartu ini'),
        'restored' => 'mengembalikan'.($card ?: ' kartu ini').' ke '.e($d['column'] ?? ''),
        'commented' => 'berkomentar'.($card ? ' di'.$on : ''),
        'wip_changed' => 'mengubah batas WIP '.e($d['column'] ?? '').' dari '.e($d['from'] ?? 'tanpa batas').' ke '.e($d['to'] ?? 'tanpa batas'),
        'column_added' => 'menambah kolom '.e($d['column'] ?? ''),
        'column_deleted' => 'menghapus kolom '.e($d['column'] ?? ''),
        'epic_created' => 'membuat epic '.e($d['epic'] ?? ''),
        'epic_deleted' => 'menghapus epic '.e($d['epic'] ?? '').' dan melepas '.e($d['cards'] ?? 0).' kartunya',
        'member_joined' => 'bergabung sebagai '.e(strtolower(\App\Models\ProjectMember::ROLE_LABELS[$d['role'] ?? 'member'] ?? '')),
        default => e($activity->type),
    };
@endphp
<div {{ $attributes->merge(['class' => 'flex items-start gap-2.5 text-[13px]']) }}>
    <x-avatar :user="$activity->user" size="sm" class="mt-px" />
    <p class="min-w-0 leading-relaxed text-ink-2">
        <span class="font-medium text-ink">{{ $activity->user?->name ?? 'Seseorang' }}</span>
        {!! $text !!}
        <time class="whitespace-nowrap text-xs text-muted" datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ tanggal($activity->created_at, true) }}">
            · {{ $activity->created_at->diffForHumans() }}
        </time>
    </p>
</div>
