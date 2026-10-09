<?php

use App\Exceptions\FlowException;
use App\Exceptions\StaleCard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Di Vercel, HTTPS berhenti di proxy; tanpa ini url() dan redirect jadi http://
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('projects.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Aturan papan yang dilanggar bukan error. Drag-and-drop menerima JSON 422 dan
        // mengembalikan kartunya; form kembali ke halaman sebelumnya dengan pesannya.
        $exceptions->render(function (FlowException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            // Isian form yang ditolak karena kartu sudah berubah tetap dikembalikan, supaya tidak hilang.
            return $e instanceof StaleCard
                ? back()->withInput()->with('error', $e->getMessage())
                : back()->with('error', $e->getMessage());
        });
        $exceptions->dontReport(FlowException::class);
    })->create();
