<x-layouts.guest title="Undangan tidak berlaku">
    <div class="panel p-6 sm:p-8">
        <h1 class="text-xl">Tautan undangan ini sudah tidak berlaku</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-2">
            Tautannya mungkin sudah lewat 7 hari atau dicabut pemilik proyek. Minta tautan baru ke orang yang mengundangmu.
        </p>
        <a href="{{ auth()->check() ? route('projects.index') : route('home') }}" class="btn-secondary mt-5">
            {{ auth()->check() ? 'Ke beranda' : 'Ke halaman depan' }}
        </a>
    </div>
</x-layouts.guest>
