<x-layouts.guest title="Undangan">
    <div class="panel p-6 sm:p-8">
        <p class="text-[13px] text-muted">{{ $invitation->creator->name }} mengundangmu ke</p>
        <h1 class="mt-1 text-xl">{{ $invitation->project->name }}</h1>
        <p class="mt-2 text-[13px] text-ink-2">
            sebagai <span class="font-semibold">{{ strtolower(\App\Models\ProjectMember::ROLE_LABELS[$invitation->role]) }}</span>:
            {{ $invitation->role === 'viewer' ? 'bisa melihat papan dan metriknya, tanpa mengubah apa pun.' : 'bisa membuat, memindah, dan mengomentari kartu.' }}
        </p>

        @auth
            @if ($alreadyMember)
                <p class="mt-5 text-[13px] text-ink-2">Kamu sudah anggota proyek ini.</p>
                <a href="{{ route('projects.show', $invitation->project) }}" class="btn-primary mt-3 w-full">Buka papan</a>
            @else
                <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}" class="mt-6">
                    @csrf
                    <button class="btn-primary w-full">Gabung sebagai {{ auth()->user()->name }}</button>
                </form>
            @endif
        @else
            <p class="mt-5 text-[13px] text-ink-2">Masuk atau buat akun dulu; setelah itu kamu kembali ke halaman ini untuk bergabung.</p>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                <a href="{{ route('register') }}" class="btn-primary">Buat akun</a>
                <a href="{{ route('login') }}" class="btn-secondary">Masuk</a>
            </div>
        @endauth
        <p class="mt-5 text-xs text-muted">Tautan berlaku sampai {{ tanggal($invitation->expires_at, true) }}.</p>
    </div>
</x-layouts.guest>
