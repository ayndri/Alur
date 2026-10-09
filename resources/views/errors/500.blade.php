{{-- Sengaja tanpa layout dan tanpa auth(): halaman ini harus tetap tampil walau database sedang tidak bisa dihubungi. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terjadi kesalahan · Alur</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body class="grid min-h-dvh place-items-center px-4">
    <div class="panel w-full max-w-md p-6 sm:p-8">
        <p class="font-mono text-xs text-muted">500</p>
        <h1 class="mt-1 text-xl">Ada yang salah di server kami</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-ink-2">Kesalahannya sudah tercatat. Coba muat ulang halaman ini sebentar lagi.</p>
        <a href="/" class="btn-secondary mt-5">Ke halaman depan</a>
    </div>
</body>
</html>
