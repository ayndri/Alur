<x-layouts.guest title="Masuk">
    <div class="panel p-6 sm:p-8">
        <h1 class="text-xl">Masuk ke Alur</h1>
        <p class="mt-1 text-[13px] text-muted">Lanjutkan ke papan timmu.</p>

        <div class="mt-5 space-y-2"><x-flash /></div>

        <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="input">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>
            <label class="flex items-center gap-2 text-[13px] text-ink-2">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-ink"> Ingat saya di perangkat ini
            </label>
            <button class="btn-primary w-full">Masuk</button>
        </form>

        <p class="mt-5 text-center text-[13px] text-muted">
            Belum punya akun? <a href="{{ route('register') }}" class="font-medium text-ink underline">Daftar</a>
        </p>
    </div>

    @if (\App\Models\User::where('email', \App\Http\Controllers\LandingController::DEMO_EMAIL)->exists())
        <form method="POST" action="{{ route('demo') }}" class="mt-4 rounded-lg border border-dashed border-line-strong px-5 py-4 text-[13px]">
            @csrf
            <p class="text-ink-2">Sekadar melihat-lihat? Masuk ke papan demo tim klinik, berisi riwayat kerja 10 minggu.</p>
            <button class="btn-secondary mt-3 w-full">Buka papan demo</button>
        </form>
    @endif
</x-layouts.guest>
