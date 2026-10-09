@if (session('success'))
    <div class="flex items-start gap-2.5 rounded-md border border-green/20 bg-green-tint px-3.5 py-2.5 text-[13px] text-green" role="status">
        <x-icon name="check" class="mt-px" />
        <span>{{ session('success') }}</span>
    </div>
@endif
@if (session('error'))
    <div class="flex items-start gap-2.5 rounded-md border border-red/20 bg-red-tint px-3.5 py-2.5 text-[13px] text-red" role="alert">
        <x-icon name="alert" class="mt-px" />
        <span>{{ session('error') }}</span>
    </div>
@endif
@if (isset($errors) && $errors->any() && ! session('error'))
    <div class="flex items-start gap-2.5 rounded-md border border-red/20 bg-red-tint px-3.5 py-2.5 text-[13px] text-red" role="alert">
        <x-icon name="alert" class="mt-px" />
        <span>Ada isian yang perlu diperbaiki. Lihat keterangan merah di bawah kolomnya.</span>
    </div>
@endif
