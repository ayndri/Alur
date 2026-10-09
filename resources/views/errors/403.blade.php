<x-layouts.guest title="Kamu tidak punya akses ke sini">
    <div class="panel p-6 sm:p-8">
        <p class="font-mono text-xs text-muted">403</p>
        <h1 class="mt-1 text-xl">Kamu tidak punya akses ke sini</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-2">Halaman ini hanya untuk anggota proyek dengan peran tertentu. Minta pemilik proyek mengundangmu atau mengubah peranmu.</p>
        <a href="{{ auth()->check() ? route('projects.index') : route('home') }}" class="btn-secondary mt-5">{{ auth()->check() ? 'Ke beranda' : 'Ke halaman depan' }}</a>
    </div>
</x-layouts.guest>
