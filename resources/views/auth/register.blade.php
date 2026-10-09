<x-layouts.guest title="Daftar">
    <div class="panel p-6 sm:p-8">
        <h1 class="text-xl">Buat akun Alur</h1>
        <p class="mt-1 text-[13px] text-muted">Setelah daftar, buat proyek sendiri atau buka tautan undangan dari timmu.</p>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="name" class="label">Nama</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="80" autocomplete="name" class="input" placeholder="Nama yang dilihat timmu">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="input" placeholder="email@contoh.com">
                @error('email')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="input">
                <p class="hint">Minimal 8 karakter.</p>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="label">Ulangi password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
            <button class="btn-primary w-full">Buat akun</button>
        </form>

        <p class="mt-5 text-center text-[13px] text-muted">
            Sudah punya akun? <a href="{{ route('login') }}" class="font-medium text-ink underline">Masuk</a>
        </p>
    </div>
</x-layouts.guest>
