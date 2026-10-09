<x-layouts.app :title="'Pengaturan · '.$project->name" :project="$project">
    @php
        $kinds = \App\Models\Column::KIND_LABELS;
        $roles = \App\Models\ProjectMember::ROLE_LABELS;
        // Beberapa form di halaman ini memakai field "name". Isian lama (old) hanya dikembalikan ke form
        // yang mengirimnya, supaya label yang gagal disimpan tidak muncul di kolom nama proyek.
        $from = old('_form');
        $labelColors = ['slate' => 'Abu', 'red' => 'Merah', 'amber' => 'Oranye', 'green' => 'Hijau', 'blue' => 'Biru', 'violet' => 'Ungu'];
    @endphp
    <div class="mx-auto w-full max-w-4xl space-y-12 px-4 py-6 sm:px-6">

        {{-- Kolom dan batas WIP: bagian terpenting, jadi paling atas. --}}
        <section aria-labelledby="kolom">
            <h2 id="kolom" class="text-[15px]">Kolom dan batas WIP</h2>
            <p class="mt-1 max-w-2xl text-[13px] leading-relaxed text-ink-2">
                Batas WIP adalah jumlah kartu maksimal di sebuah kolom. Kolom yang penuh menolak kartu baru sampai ada yang keluar.
                Menurunkan batas tidak mengusir kartu yang sudah ada. Jenis kolom menentukan kapan cycle time mulai dan berhenti dihitung:
                <span class="inline-flex items-center gap-1"><x-kind kind="queue" :size="12" /> antre</span> belum dihitung,
                <span class="inline-flex items-center gap-1"><x-kind kind="active" :size="12" /> dikerjakan</span> mulai dihitung,
                <span class="inline-flex items-center gap-1"><x-kind kind="wait" :size="12" /> menunggu</span> tetap dihitung tapi sebagai waktu tunggu (mis. menunggu merge),
                <span class="inline-flex items-center gap-1"><x-kind kind="done" :size="12" /> selesai</span> berhenti.
            </p>

            <ol class="mt-4 divide-y divide-line rounded-lg border border-line">
                @foreach ($project->columns as $i => $column)
                    <li class="px-4 py-3">
                        <form method="POST" action="{{ route('columns.update', [$project, $column]) }}" class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_6rem_auto]">
                            @csrf @method('PUT')
                            <div>
                                <label for="col-name-{{ $column->id }}" class="text-xs text-muted">Nama</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <x-kind :kind="$column->kind" />
                                    <input id="col-name-{{ $column->id }}" name="name" value="{{ $column->name }}" required maxlength="40" class="input">
                                </div>
                            </div>
                            <div>
                                <label for="col-kind-{{ $column->id }}" class="text-xs text-muted">Jenis</label>
                                <select id="col-kind-{{ $column->id }}" name="kind" class="input mt-1" @disabled($column->cards_count > 0)>
                                    @foreach ($kinds as $value => $label)
                                        <option value="{{ $value }}" @selected($column->kind === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @if ($column->cards_count > 0)
                                    <input type="hidden" name="kind" value="{{ $column->kind }}">
                                @endif
                            </div>
                            <div>
                                <label for="col-wip-{{ $column->id }}" class="text-xs text-muted">Batas WIP</label>
                                <input id="col-wip-{{ $column->id }}" name="wip_limit" type="number" min="1" max="99" value="{{ $column->wip_limit }}" class="input mt-1 tnum" placeholder="Tanpa">
                            </div>
                            <div class="flex gap-1">
                                <button class="btn-secondary">Simpan</button>
                            </div>
                        </form>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                            <span class="tnum">{{ $column->cards_count }} kartu di papan</span>
                            @if ($column->cards_count > 0)
                                <span>Jenis terkunci selama kolom berisi kartu.</span>
                            @endif
                            <span class="ml-auto flex gap-1">
                                @if ($i > 0 || $i < $project->columns->count() - 1)
                                    @php
                                        $ids = $project->columns->pluck('id')->all();
                                    @endphp
                                    @foreach ([[-1, 'chevron-left', 'Geser ke kiri'], [1, 'chevron-left', 'Geser ke kanan']] as [$step, $icon, $label])
                                        @php
                                            $target = $i + $step;
                                        @endphp
                                        @if ($target >= 0 && $target < count($ids))
                                            @php
                                                $order = $ids;
                                                [$order[$i], $order[$target]] = [$order[$target], $order[$i]];
                                            @endphp
                                            <form method="POST" action="{{ route('columns.reorder', $project) }}">
                                                @csrf @method('PUT')
                                                @foreach ($order as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
                                                <button class="btn-ghost btn-icon" title="{{ $label }}"><x-icon :name="$icon" :size="14" class="{{ $step > 0 ? 'rotate-180' : '' }}" /><span class="sr-only">{{ $label }}: {{ $column->name }}</span></button>
                                            </form>
                                        @endif
                                    @endforeach
                                @endif
                                <form method="POST" action="{{ route('columns.destroy', [$project, $column]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost btn-icon text-red hover:bg-red-tint hover:text-red" title="Hapus kolom" @disabled($column->cards_count > 0)>
                                        <x-icon name="trash" :size="14" /><span class="sr-only">Hapus kolom {{ $column->name }}</span>
                                    </button>
                                </form>
                            </span>
                        </div>
                    </li>
                @endforeach
            </ol>

            <form method="POST" action="{{ route('columns.store', $project) }}" class="mt-4 grid items-end gap-3 rounded-lg bg-column p-4 sm:grid-cols-[minmax(0,1fr)_9rem_6rem_auto]">
                @csrf
                <input type="hidden" name="_form" value="column">
                <div>
                    <label for="new-col-name" class="text-xs text-muted">Kolom baru</label>
                    <input id="new-col-name" name="name" value="{{ $from === 'column' ? old('name') : '' }}" required maxlength="40" class="input mt-1" placeholder="Mis. QA">
                </div>
                <div>
                    <label for="new-col-kind" class="text-xs text-muted">Jenis</label>
                    <select id="new-col-kind" name="kind" class="input mt-1">
                        @foreach ($kinds as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'active')>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="new-col-wip" class="text-xs text-muted">Batas WIP</label>
                    <input id="new-col-wip" name="wip_limit" type="number" min="1" max="99" class="input mt-1" placeholder="Tanpa">
                </div>
                <button class="btn-primary">Tambah kolom</button>
                <p class="text-xs text-muted sm:col-span-4">Kolom baru masuk tepat sebelum kolom selesai.</p>
                @if ($from === 'column')
                    @foreach (['name', 'kind', 'wip_limit'] as $field)
                        @error($field)<p class="field-error sm:col-span-4">{{ $message }}</p>@enderror
                    @endforeach
                @endif
            </form>
        </section>

        {{-- Anggota dan undangan --}}
        <section aria-labelledby="anggota">
            <h2 id="anggota" class="text-[15px]">Anggota</h2>
            <ul class="mt-4 divide-y divide-line rounded-lg border border-line">
                @foreach ($project->members as $member)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                        <x-avatar :user="$member" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[13px] font-medium">{{ $member->name }} @if ($member->id === auth()->id())<span class="text-muted">(kamu)</span>@endif</p>
                            <p class="truncate text-xs text-muted">{{ $member->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('members.update', [$project, $member]) }}" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <label for="role-{{ $member->id }}" class="sr-only">Peran {{ $member->name }}</label>
                            <select id="role-{{ $member->id }}" name="role" class="input w-32" onchange="this.form.requestSubmit()">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected($member->pivot->role === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <noscript><button class="btn-secondary">Simpan</button></noscript>
                        </form>
                        @if ($member->id !== auth()->id())
                            <form method="POST" action="{{ route('members.destroy', [$project, $member]) }}">
                                @csrf @method('DELETE')
                                <button class="btn-ghost text-red hover:bg-red-tint hover:text-red">Keluarkan</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">
                <h3 class="text-[14px]">Tautan undangan</h3>
                <p class="mt-1 text-[13px] text-ink-2">Siapa pun yang punya tautan bisa bergabung dengan peran itu selama 7 hari. Cabut kalau tautannya tersebar ke orang yang salah.</p>

                @if ($invitations->isNotEmpty())
                    <ul class="mt-3 space-y-2">
                        @foreach ($invitations as $invitation)
                            <li class="flex flex-wrap items-center gap-2 rounded-md border border-line px-3 py-2.5" x-data="{ copied: false }">
                                <span class="badge bg-slate-tint text-slate">{{ $roles[$invitation->role] }}</span>
                                <label for="inv-{{ $invitation->id }}" class="sr-only">Tautan undangan</label>
                                <input id="inv-{{ $invitation->id }}" readonly value="{{ route('invitations.show', $invitation->token) }}"
                                       class="min-w-0 flex-1 truncate bg-transparent font-mono text-xs text-ink-2 outline-none" @focus="$el.select()">
                                <button type="button" class="btn-secondary"
                                        @click="navigator.clipboard.writeText(@js(route('invitations.show', $invitation->token))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                                    <x-icon name="copy" :size="14" /><span x-text="copied ? 'Tersalin' : 'Salin'">Salin</span>
                                </button>
                                <form method="POST" action="{{ route('invitations.destroy', [$project, $invitation]) }}">
                                    @csrf @method('DELETE')
                                    <button class="btn-ghost text-red hover:bg-red-tint hover:text-red">Cabut</button>
                                </form>
                                <p class="w-full text-xs text-muted">Dibuat {{ $invitation->creator->name }}, berlaku sampai {{ tanggal($invitation->expires_at, true) }}</p>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('invitations.store', $project) }}" class="mt-3 flex flex-wrap items-center gap-2">
                    @csrf
                    <label for="inv-role" class="text-[13px] text-ink-2">Buat tautan untuk</label>
                    <select id="inv-role" name="role" class="input w-36">
                        <option value="member">Anggota</option>
                        <option value="viewer">Pengamat</option>
                    </select>
                    <button class="btn-primary"><x-icon name="link" :size="14" /> Buat tautan</button>
                </form>
            </div>
        </section>

        {{-- Label --}}
        <section aria-labelledby="label">
            <h2 id="label" class="text-[15px]">Label</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($project->labels as $label)
                    <span class="inline-flex items-center gap-1 rounded-md border border-line py-1 pl-1.5 pr-1">
                        <x-label-chip :label="$label" />
                        <form method="POST" action="{{ route('labels.destroy', [$project, $label]) }}">
                            @csrf @method('DELETE')
                            <button class="grid h-6 w-6 place-items-center rounded-sm text-muted hover:bg-red-tint hover:text-red max-sm:h-9 max-sm:w-9" title="Hapus label {{ $label->name }}">
                                <x-icon name="x" :size="13" /><span class="sr-only">Hapus label {{ $label->name }}</span>
                            </button>
                        </form>
                    </span>
                @empty
                    <p class="text-[13px] text-muted">Belum ada label.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('labels.store', $project) }}" class="mt-3 flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="_form" value="label">
                <div>
                    <label for="label-name" class="text-xs text-muted">Nama label</label>
                    <input id="label-name" name="name" value="{{ $from === 'label' ? old('name') : '' }}" required maxlength="30" class="input mt-1 w-48" placeholder="Mis. Bug">
                </div>
                <div>
                    <label for="label-color" class="text-xs text-muted">Warna</label>
                    <select id="label-color" name="color" class="input mt-1 w-32">
                        @foreach ($labelColors as $value => $name)
                            <option value="{{ $value }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn-secondary">Tambah label</button>
            </form>
            @if ($from === 'label')
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            @endif
        </section>

        {{-- Info proyek --}}
        <section aria-labelledby="info">
            <h2 id="info" class="text-[15px]">Proyek</h2>
            <form method="POST" action="{{ route('projects.update', $project) }}" class="mt-3 max-w-xl space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="_form" value="project">
                <div>
                    <label for="p-name" class="label">Nama</label>
                    <input id="p-name" name="name" value="{{ $from === 'project' ? old('name') : $project->name }}" required maxlength="80" class="input">
                    @if ($from === 'project')
                        @error('name')<p class="field-error">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label for="p-desc" class="label">Deskripsi</label>
                    <textarea id="p-desc" name="description" rows="3" maxlength="500" class="input">{{ $from === 'project' ? old('description') : $project->description }}</textarea>
                </div>
                <p class="text-xs text-muted">Kode proyek <span class="font-mono font-semibold text-ink-2">{{ $project->key }}</span> tidak bisa diubah karena sudah dipakai di nomor kartu dan tautan.</p>
                <button class="btn-primary">Simpan</button>
            </form>
        </section>
    </div>
</x-layouts.app>
