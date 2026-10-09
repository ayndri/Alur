<x-layouts.guest title="Halaman tidak ditemukan">
    <div class="panel p-6 sm:p-8">
        <p class="font-mono text-xs text-muted">404</p>
        <h1 class="mt-1 text-xl">Halaman tidak ditemukan</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-2">Mungkin tautannya salah ketik, atau kartu dan proyeknya sudah tidak ada.</p>
        <a href="{{ auth()->check() ? route('projects.index') : route('home') }}" class="btn-secondary mt-5">{{ auth()->check() ? 'Ke beranda' : 'Ke halaman depan' }}</a>
    </div>
</x-layouts.guest>
