@php
    $epic = $epic ?? null;
    $colors = ['blue' => 'Biru', 'green' => 'Hijau', 'violet' => 'Ungu', 'amber' => 'Oranye', 'red' => 'Merah', 'slate' => 'Abu'];
@endphp
<div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_10rem]">
    <div>
        <label for="epic-name" class="text-xs text-muted">Nama epic</label>
        <input id="epic-name" name="name" value="{{ old('name', $epic?->name) }}" required maxlength="80" class="input mt-1" placeholder="Mis. Layar TV ruang tunggu">
        @error('name')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="epic-color" class="text-xs text-muted">Warna</label>
        <select id="epic-color" name="color" class="input mt-1">
            @foreach ($colors as $value => $label)
                <option value="{{ $value }}" @selected(old('color', $epic?->color ?? 'blue') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="epic-target" class="text-xs text-muted">Target selesai <span class="text-muted">(opsional)</span></label>
        <input id="epic-target" name="target_on" type="date" value="{{ old('target_on', $epic?->target_on?->toDateString()) }}" class="input mt-1">
        @error('target_on')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-3">
        <label for="epic-desc" class="text-xs text-muted">Tujuan <span class="text-muted">(opsional)</span></label>
        <textarea id="epic-desc" name="description" rows="2" maxlength="2000" class="input mt-1" placeholder="Apa yang berubah untuk pengguna kalau epic ini selesai?">{{ old('description', $epic?->description) }}</textarea>
    </div>
</div>
