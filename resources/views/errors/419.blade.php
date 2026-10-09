<x-layouts.guest title="Sesi halaman ini sudah habis">
    <div class="panel p-6 sm:p-8">
        <p class="font-mono text-xs text-muted">419</p>
        <h1 class="mt-1 text-xl">Sesi halaman ini sudah habis</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-2">Halaman terlalu lama dibiarkan terbuka, jadi isian tadi tidak dikirim demi keamanan. Kembali, muat ulang halamannya, lalu coba lagi.</p>
        <a href="{{ auth()->check() ? route('projects.index') : route('home') }}" class="btn-secondary mt-5">{{ auth()->check() ? 'Ke beranda' : 'Ke halaman depan' }}</a>
    </div>
</x-layouts.guest>
