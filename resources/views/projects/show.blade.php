@php($canWork = in_array($role, ['owner', 'member'], true))
<x-layouts.app :title="$project->name" :project="$project">
    <x-slot:actions>
        <div class="hidden -space-x-1 md:flex" aria-label="Anggota proyek">
            @foreach ($project->members->take(5) as $member)
                <x-avatar :user="$member" />
            @endforeach
            @if ($project->members->count() > 5)
                <span class="grid h-7 w-7 place-items-center rounded-full bg-canvas text-[11px] font-semibold text-ink-2 ring-2 ring-paper">+{{ $project->members->count() - 5 }}</span>
            @endif
        </div>
    </x-slot:actions>

    <div x-data="board(@js([
            'versionUrl' => route('projects.version', $project),
            'boardUrl' => route('projects.show', [$project, 'fragment' => 1]),
            'moveUrl' => url("/p/{$project->key}/__NUM__/pindah"),
            'canWork' => $canWork,
            'me' => auth()->id(),
            'firstColumn' => $columns->firstWhere('kind', 'queue')?->id ?? $columns->first()?->id,
        ]))" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">

        {{-- Bilah alat: tambah kartu, saring, dan ringkasan cycle time. --}}
        <div class="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-6">
            @if ($canWork)
                <button type="button" class="btn-primary" @click="openAdd(firstColumn)">
                    <x-icon name="plus" /> Kartu baru
                </button>
            @endif

            <div class="relative">
                <label for="saring" class="sr-only">Cari kartu</label>
                <x-icon name="search" :size="15" class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-muted" />
                <input id="saring" type="search" x-model.debounce.150ms="filter.q" class="input w-44 py-1.5 pl-8 sm:w-52" placeholder="Cari judul atau KLN-12">
            </div>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false">
                <button type="button" class="btn-secondary" @click="open = !open" :aria-expanded="open">
                    <x-icon name="filter" /> Saring
                    <span x-show="activeFilters() > 0" x-text="activeFilters()" class="rounded-full bg-ink px-1.5 text-[11px] text-white"></span>
                </button>
                <div x-show="open" x-cloak class="absolute left-0 z-20 mt-1.5 w-64 rounded-md border border-line bg-paper p-3 shadow-lg shadow-ink/10">
                    <label for="saring-orang" class="label">Dipegang oleh</label>
                    <select id="saring-orang" x-model="filter.assignee" class="input">
                        <option value="">Siapa saja</option>
                        <option value="me">Saya</option>
                        <option value="none">Belum ada yang memegang</option>
                        @foreach ($project->members as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                    @if ($project->epics->isNotEmpty())
                        <label for="saring-epic" class="label mt-3">Epic</label>
                        <select id="saring-epic" x-model="filter.epic" class="input">
                            <option value="">Semua epic</option>
                            <option value="none">Tanpa epic</option>
                            @foreach ($project->epics as $epic)
                                <option value="{{ $epic->id }}">{{ $epic->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if ($project->labels->isNotEmpty())
                        <p class="label mt-3">Label</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($project->labels as $label)
                                <label class="cursor-pointer">
                                    <input type="checkbox" value="{{ $label->id }}" x-model="filter.labels" class="peer sr-only">
                                    <x-label-chip :label="$label" class="ring-1 ring-transparent peer-checked:ring-ink peer-focus-visible:outline-2 peer-focus-visible:outline-accent" />
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <label class="mt-3 flex items-center gap-2 text-[13px]">
                        <input type="checkbox" x-model="filter.blocked" class="h-4 w-4 accent-ink"> Hanya yang terblokir
                    </label>
                    <button type="button" class="btn-ghost mt-2 w-full" @click="resetFilter()" x-show="activeFilters() > 0">Hapus saringan</button>
                </div>
            </div>

            <p class="text-xs text-muted" x-show="hidden > 0" x-cloak x-text="hidden + ' kartu disembunyikan saringan'"></p>

            @if ($cycle['count'] >= 5)
                <a href="{{ route('flow.show', $project) }}" class="ml-auto hidden items-center gap-1.5 rounded-sm px-2 py-1 text-xs text-ink-2 no-underline hover:bg-canvas md:flex"
                   title="Dihitung dari {{ $cycle['count'] }} kartu yang selesai dalam 90 hari terakhir">
                    <x-icon name="hourglass" :size="13" />
                    85% kartu selesai dalam <span class="font-semibold text-ink">{{ hari($cycle['p85']) }}</span>
                </a>
            @endif
        </div>

        <div id="board" class="relative min-h-0 flex-1 snap-x overflow-x-auto overflow-y-hidden max-lg:overflow-y-visible" :class="{ 'board-dragging': dragging }">
            @include('projects._board')
        </div>

        {{-- Ditanyakan saat kartu diseret mundur ke kolom kerja: alasannya jadi data "kenapa lama" di halaman Alur kerja. --}}
        <dialog x-ref="reworkDialog" class="m-auto w-[min(92vw,26rem)] rounded-lg border border-line bg-paper p-0 text-ink shadow-2xl backdrop:bg-ink/40"
                @cancel.prevent="answerRework(false)" aria-labelledby="rework-title">
            <form class="p-5" @submit.prevent="answerRework(true)">
                <h2 id="rework-title" class="flex items-center gap-2 text-[15px]"><x-icon name="rework" class="text-red" /> Kenapa dikembalikan?</h2>
                <p class="mt-1 text-[13px] text-ink-2" x-text="pendingRework ? pendingRework.label : ''"></p>
                <fieldset class="mt-4 space-y-1.5">
                    <legend class="sr-only">Alasan</legend>
                    @foreach (\App\Models\CardMove::REASONS as $key => $label)
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-md border border-line px-3 py-2 text-[13px] has-[:checked]:border-ink has-[:checked]:bg-column max-sm:py-3">
                            <input type="radio" name="rework_reason" value="{{ $key }}" x-model="reworkReason" class="accent-ink"> {{ $label }}
                        </label>
                    @endforeach
                </fieldset>
                <label for="rework-note" class="label mt-3">Catatan <span class="font-normal text-muted">(opsional)</span></label>
                <input id="rework-note" x-model="reworkNote" maxlength="300" class="input" placeholder="Mis. tombol batal tidak merespons di Android 10">
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="answerRework(false)">Batal</button>
                    <button class="btn-primary">Kembalikan kartu</button>
                </div>
            </form>
        </dialog>

        <div x-show="toast" x-cloak x-transition.opacity role="status" aria-live="polite"
             class="pointer-events-none fixed bottom-5 left-1/2 z-50 flex w-[min(92vw,28rem)] -translate-x-1/2 items-start gap-2 rounded-md bg-ink px-3.5 py-2.5 text-[13px] text-white shadow-lg">
            <x-icon name="info" class="mt-px" /><span x-text="toast"></span>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
        <script>
            // Papan: seret-lepas, saringan, dan pembaruan otomatis.
            // Server tetap penentu: batas WIP diperiksa ulang di sana, di dalam kunci. Pemeriksaan di sini
            // hanya supaya kolom penuh langsung terlihat menolak saat kartu masih diseret.
            function board(config) {
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';

                return {
                    ...config,
                    adding: null,
                    dragging: false,
                    toast: '',
                    hidden: 0,
                    filter: { q: '', assignee: '', epic: '', labels: [], blocked: false },
                    sortables: [],
                    toastTimer: null,
                    pendingRework: null,
                    reworkReason: 'bug',
                    reworkNote: '',

                    init() {
                        const epic = new URLSearchParams(location.search).get('epic');
                        if (epic) this.filter.epic = epic;
                        this.bind();
                        this.applyFilter();
                        this.$watch('filter', () => this.applyFilter(), { deep: true });
                        // Vercel tidak bisa menahan WebSocket, jadi browser bertanya "ada yang berubah?" tiap 5 detik.
                        // Jawabannya satu angka; papan baru diambil ulang kalau angkanya berbeda.
                        setInterval(() => this.poll(), 5000);
                        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
                    },

                    get version() {
                        return Number(document.getElementById('board-columns').dataset.version);
                    },

                    bind() {
                        this.sortables.forEach((s) => s.destroy());
                        this.sortables = [];
                        if (!this.canWork) return;

                        document.querySelectorAll('#board-columns .card-list').forEach((list) => {
                            this.sortables.push(Sortable.create(list, {
                                group: {
                                    name: 'board',
                                    put: (to, from) => to === from || !to.el.closest('section').hasAttribute('data-full'),
                                },
                                draggable: '.kcard',
                                animation: 150,
                                delay: 150,
                                delayOnTouchOnly: true,
                                forceFallback: true,
                                fallbackOnBody: true,
                                onStart: () => { this.dragging = true; },
                                onEnd: (e) => {
                                    this.dragging = false;
                                    if (e.from === e.to && e.oldIndex === e.newIndex) return;
                                    const next = e.item.nextElementSibling;
                                    const from = e.from.closest('section').dataset;
                                    const to = e.to.closest('section').dataset;
                                    const args = [e.item.dataset.id, e.to.dataset.columnId, next?.dataset.id ?? null, e.item];
                                    if (this.isRework(from, to)) {
                                        this.askRework(args, from.columnName, to.columnName);
                                    } else {
                                        this.move(...args);
                                    }
                                },
                            }));
                        });
                    },

                    // Aturan yang sama dengan Board::isRework() di server; server tetap yang menentukan.
                    isRework(from, to) {
                        return Number(to.position) < Number(from.position)
                            && ['active', 'wait', 'done'].includes(from.kind)
                            && ['active', 'wait'].includes(to.kind);
                    },

                    askRework(args, fromName, toName) {
                        this.pendingRework = { args, label: 'Dari ' + fromName + ' ke ' + toName + '. Ini dihitung sebagai revisi.' };
                        this.reworkReason = 'bug';
                        this.reworkNote = '';
                        this.$refs.reworkDialog.showModal();
                    },

                    async answerRework(confirmed) {
                        const pending = this.pendingRework;
                        this.$refs.reworkDialog.close();
                        this.pendingRework = null;
                        if (!pending) return;
                        if (confirmed) {
                            await this.move(...pending.args, { reason: this.reworkReason, note: this.reworkNote });
                        } else {
                            await this.refresh(); // kembalikan kartu ke posisi semula
                        }
                    },

                    async move(cardId, columnId, beforeId, el, extra = {}) {
                        const number = el.getAttribute('href').split('/').pop();
                        try {
                            const res = await fetch(this.moveUrl.replace('__NUM__', number), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: JSON.stringify({ column_id: Number(columnId), before_id: beforeId ? Number(beforeId) : null, ...extra }),
                            });
                            if (!res.ok) {
                                const body = await res.json().catch(() => ({}));
                                this.say(body.message || 'Kartu tidak bisa dipindah. Papan dimuat ulang.');
                            }
                        } catch {
                            this.say('Koneksi terputus. Kartu belum dipindah.');
                        }
                        // Ambil ulang papan: angka WIP, umur kartu, dan urutan dari server yang berlaku.
                        await this.refresh();
                    },

                    async poll() {
                        // Jangan ganti papan saat orang sedang menyeret atau sedang mengetik judul kartu baru.
                        if (document.hidden || this.dragging || this.typing() || this.pendingRework) return;
                        try {
                            const res = await fetch(this.versionUrl, { headers: { 'Accept': 'application/json' } });
                            if (res.ok && (await res.json()).version !== this.version) await this.refresh();
                        } catch { /* coba lagi di putaran berikutnya */ }
                    },

                    async refresh() {
                        const res = await fetch(this.boardUrl, { headers: { 'Accept': 'text/html' } });
                        if (!res.ok) return;
                        const html = await res.text();
                        const scroller = document.getElementById('board');
                        const left = scroller.scrollLeft;
                        const tops = [...document.querySelectorAll('#board-columns .card-list')].map((l) => l.scrollTop);
                        document.getElementById('board-columns').outerHTML = html;
                        scroller.scrollLeft = left;
                        document.querySelectorAll('#board-columns .card-list').forEach((l, i) => { l.scrollTop = tops[i] ?? 0; });
                        this.bind();
                        this.applyFilter();
                        if (this.adding !== null) document.getElementById('add-' + this.adding)?.focus();
                    },

                    typing() {
                        return this.adding !== null && !!document.getElementById('add-' + this.adding)?.value.trim();
                    },

                    openAdd(columnId) {
                        this.adding = columnId;
                        this.$nextTick(() => document.getElementById('add-' + columnId)?.focus());
                    },

                    async submitAdd(e) {
                        const form = e.target;
                        const res = await fetch(form.action, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: new FormData(form),
                        });
                        if (!res.ok) {
                            const body = await res.json().catch(() => ({}));
                            this.say(body.message || 'Kartu belum tersimpan.');
                        }
                        const columnId = this.adding;
                        this.adding = null;
                        await this.refresh();
                        // Tetap terbuka supaya bisa menambah beberapa kartu berturut-turut.
                        if (res.ok) this.openAdd(columnId);
                    },

                    applyFilter() {
                        const f = this.filter;
                        const q = f.q.trim().toLowerCase();
                        let hidden = 0;
                        document.querySelectorAll('#board-columns .kcard').forEach((card) => {
                            const d = card.dataset;
                            const labels = d.labels ? d.labels.split(',') : [];
                            const show = (!q || d.title.includes(q))
                                && (!f.assignee
                                    || (f.assignee === 'me' && d.assignee === String(this.me))
                                    || (f.assignee === 'none' && d.assignee === '')
                                    || d.assignee === f.assignee)
                                && (!f.epic || (f.epic === 'none' ? d.epic === '' : d.epic === f.epic))
                                && f.labels.every((id) => labels.includes(String(id)))
                                && (!f.blocked || d.blocked === '1');
                            card.classList.toggle('hidden', !show);
                            if (!show) hidden++;
                        });
                        this.hidden = hidden;
                    },

                    activeFilters() {
                        const f = this.filter;
                        return (f.assignee ? 1 : 0) + (f.epic ? 1 : 0) + f.labels.length + (f.blocked ? 1 : 0);
                    },

                    resetFilter() {
                        this.filter = { q: this.filter.q, assignee: '', epic: '', labels: [], blocked: false };
                    },

                    say(message) {
                        this.toast = message;
                        clearTimeout(this.toastTimer);
                        this.toastTimer = setTimeout(() => { this.toast = ''; }, 5000);
                    },
                };
            }
        </script>
    @endpush

    @push('head')
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endpush
</x-layouts.app>
