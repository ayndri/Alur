@props(['title' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}Alur</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
</head>
<body class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-md flex-col px-4 py-6">
        <a href="{{ route('home') }}" class="self-start no-underline"><x-logo /></a>
        <main id="konten" class="my-auto py-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
