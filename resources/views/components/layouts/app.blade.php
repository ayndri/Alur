@props(['title' => null, 'project' => null, 'heading' => null])
@php
    $user = auth()->user();
    $isDemo = $user->email === \App\Http\Controllers\LandingController::DEMO_EMAIL;
    $role = $project ? $user->roleIn($project) : null;
    $tabs = $project ? array_filter([
        ['projects.show', 'Papan', 'board', 'projects.show|cards.*'],
        ['epics.index', 'Epic', 'epic', 'epics.*'],
        ['flow.show', 'Alur kerja', 'chart', 'flow.show'],
        ['projects.activity', 'Aktivitas', 'activity', 'projects.activity'],
        ['projects.archived', 'Arsip', 'archive', 'projects.archived'],
        $role === 'owner' ? ['projects.settings', 'Pengaturan', 'settings', 'projects.settings'] : null,
    ]) : [];
@endphp
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 btn-primary">Lompat ke konten</a>

    <div class="lg:grid lg:h-dvh lg:grid-cols-[15rem_minmax(0,1fr)]">
        {{-- Sidebar. Di layar kecil jadi laci. --}}
        <div x-show="nav" x-cloak class="fixed inset-0 z-30 bg-ink/30 lg:hidden" @click="nav = false"></div>
        <aside class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col bg-canvas px-3 pb-3 transition-transform duration-200 max-lg:shadow-xl lg:static lg:translate-x-0"
               :class="nav ? 'translate-x-0' : 'max-lg:-translate-x-full'">
            <div class="flex h-14 shrink-0 items-center justify-between px-1">
                <a href="{{ route('projects.index') }}" class="flex items-center gap-2 no-underline">
                    <x-logo />
                </a>
                <button type="button" class="btn-ghost btn-icon lg:hidden" @click="nav = false">
                    <x-icon name="x" /><span class="sr-only">Tutup menu</span>
                </button>
            </div>

            <nav class="space-y-0.5" aria-label="Menu utama">
                <a href="{{ route('projects.index') }}" class="{{ request()->routeIs('projects.index') ? 'nav-link-active' : 'nav-link' }}"
                   @if (request()->routeIs('projects.index')) aria-current="page" @endif>
                    <x-icon name="home" /> Beranda
                </a>
            </nav>

            <div class="mt-6 flex items-center justify-between px-2.5 pb-1.5">
                <p class="text-xs font-medium text-muted">Proyek</p>
                <a href="{{ route('projects.create') }}" class="-mr-1 grid h-6 w-6 place-items-center rounded-sm text-muted hover:bg-paper hover:text-ink max-lg:h-9 max-lg:w-9" title="Proyek baru">
                    <x-icon name="plus" :size="14" /><span class="sr-only">Proyek baru</span>
                </a>
            </div>
            <nav class="no-scrollbar -mx-1 min-h-0 flex-1 space-y-0.5 overflow-y-auto px-1" aria-label="Proyek">
                @forelse ($sidebarProjects as $item)
                    @php $active = $project?->is($item); @endphp
                    <a href="{{ route('projects.show', $item) }}" class="{{ $active ? 'nav-link-active' : 'nav-link' }}" @if ($active) aria-current="page" @endif>
                        <span class="grid h-5 min-w-5 place-items-center rounded-[4px] bg-ink/[0.07] px-1 font-mono text-[9px] font-bold text-ink-2">{{ $item->key }}</span>
                        <span class="truncate">{{ $item->name }}</span>
                    </a>
                @empty
                    <p class="px-2.5 text-xs leading-relaxed text-muted">Belum ada proyek. Buat satu, atau minta tautan undangan dari timmu.</p>
                @endforelse
            </nav>

            <a href="{{ route('home') }}" class="nav-link mt-3 text-muted">
                <x-icon name="chevron-left" /> Halaman depan Alur
            </a>

            <div class="mt-2 border-t border-line pt-3">
                <div class="flex items-center gap-2.5 px-1.5">
                    <x-avatar :user="$user" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[13px] font-medium">{{ $user->name }}</p>
                        <p class="truncate text-xs text-muted">{{ $user->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-ghost btn-icon" title="Keluar"><x-icon name="logout" /><span class="sr-only">Keluar</span></button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex min-w-0 flex-col lg:h-dvh lg:py-2 lg:pr-2">
            <div class="flex min-h-dvh flex-1 flex-col overflow-hidden bg-paper lg:min-h-0 lg:rounded-lg lg:border lg:border-line">
                @if ($isDemo)
                    {{-- Ungu = "kamu di sini": pengunjung tahu sedang di akun demo bersama, dan jalan keluarnya. --}}
                    <div class="flex shrink-0 flex-col gap-1 border-b border-accent/15 bg-accent-tint px-4 py-2 text-[13px] text-accent sm:flex-row sm:items-center sm:gap-3 sm:px-6">
                        <p class="flex min-w-0 flex-1 items-start gap-2">
                            <x-icon name="info" :size="15" class="mt-0.5" />
                            <span><span class="font-semibold">Mode demo.</span> Kamu masuk sebagai {{ $user->name }}, pemilik papan tim klinik. Akun ini dipakai bersama, jadi perubahanmu bisa terlihat pengunjung lain.</span>
                        </p>
                        <div class="-ml-2.5 flex shrink-0 items-center gap-1 pl-6 sm:ml-0 sm:pl-0">
                            <a href="{{ route('home') }}" class="btn px-2.5 font-semibold text-accent hover:bg-accent/10">Halaman depan</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="btn px-2.5 font-semibold text-accent hover:bg-accent/10">Keluar dari demo</button>
                            </form>
                        </div>
                    </div>
                @endif
                <header class="shrink-0 border-b border-line px-4 sm:px-6">
                    <div class="flex min-h-14 items-center gap-3 pt-2">
                        <button type="button" class="btn-ghost btn-icon -ml-2 lg:hidden" @click="nav = true">
                            <x-icon name="menu" /><span class="sr-only">Buka menu</span>
                        </button>
                        <h1 class="min-w-0 truncate text-[15px]">{{ $heading ?? $project?->name ?? $title }}</h1>
                        @if ($project)
                            <span class="hidden rounded-sm bg-canvas px-1.5 py-0.5 font-mono text-[11px] font-semibold text-ink-2 sm:inline">{{ $project->key }}</span>
                        @endif
                        <div class="ml-auto flex shrink-0 items-center gap-2">{{ $actions ?? '' }}</div>
                    </div>
                    @if ($tabs)
                        <nav class="no-scrollbar -mx-1 flex gap-5 overflow-x-auto px-1" aria-label="Halaman proyek">
                            @foreach ($tabs as [$route, $label, $icon, $pattern])
                                @php $active = request()->routeIs(...explode('|', $pattern)); @endphp
                                <a href="{{ route($route, $project) }}" class="{{ $active ? 'tab-active' : 'tab' }} shrink-0" @if ($active) aria-current="page" @endif>
                                    <x-icon :name="$icon" :size="15" class="{{ $active ? 'text-accent' : '' }}" /> {{ $label }}
                                </a>
                            @endforeach
                        </nav>
                    @else
                        <div class="h-2"></div>
                    @endif
                </header>

                <main id="konten" class="relative flex min-h-0 flex-1 flex-col overflow-auto">
                    @if (session('success') || session('error') || (isset($errors) && $errors->any()))
                        <div class="space-y-2 px-4 pt-4 sm:px-6"><x-flash /></div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
