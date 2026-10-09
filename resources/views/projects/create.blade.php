<x-layouts.app title="Proyek baru" heading="Proyek baru">
    <div class="mx-auto w-full max-w-xl px-4 py-8 sm:px-6">
        <form method="POST" action="{{ route('projects.store') }}" class="space-y-5"
              x-data="{ name: @js(old('name', '')), key: @js(old('key', '')), touched: @js((bool) old('key')) }">
            @csrf
            <div>
                <label for="name" class="label">Nama proyek</label>
                <input id="name" name="name" x-model="name" required maxlength="80" class="input" autofocus
                       placeholder="Contoh: Aplikasi Antrean Klinik"
                       @input="if (!touched) key = name.split(/\s+/).filter(Boolean).map(w => w[0]).join('').replace(/[^A-Za-z]/g, '').slice(0, 4).toUpperCase()">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="key" class="label">Kode</label>
                <input id="key" name="key" x-model="key" @input="touched = true" required maxlength="5" class="input w-32 font-mono uppercase" placeholder="AAK">
                <p class="hint">2 sampai 5 huruf. Jadi awalan nomor kartu (<span class="font-mono" x-text="(key || 'AAK') + '-12'"></span>) dan alamat papan. Tidak bisa diubah nanti.</p>
                @error('key')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="description" class="label">Deskripsi <span class="font-normal text-muted">(opsional)</span></label>
                <textarea id="description" name="description" rows="3" maxlength="500" class="input" placeholder="Apa yang sedang dibangun tim ini?">{{ old('description') }}</textarea>
            </div>

            <div class="rounded-md bg-column px-4 py-3 text-[13px] text-ink-2">
                <p class="font-medium text-ink">Kolom bawaan</p>
                <ul class="mt-2 space-y-1.5">
                    @foreach (\App\Services\Board::DEFAULT_COLUMNS as [$columnName, $kind, $limit])
                        <li class="flex items-center gap-2">
                            <x-kind :kind="$kind" /> {{ $columnName }}
                            <span class="text-muted">{{ $limit ? "batas {$limit} kartu" : 'tanpa batas' }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-muted">Bisa diubah, ditambah, atau dihapus di Pengaturan.</p>
            </div>

            <div class="flex gap-2">
                <button class="btn-primary">Buat proyek</button>
                <a href="{{ route('projects.index') }}" class="btn-ghost">Batal</a>
            </div>
        </form>
    </div>
</x-layouts.app>
