<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));
        Paginator::defaultView('components.pagination');

        // Daftar proyek di sidebar.
        View::composer('components.layouts.app', function ($view) {
            $view->with('sidebarProjects', auth()->user()?->projects()->orderBy('name')->get() ?? collect());
        });
    }
}
