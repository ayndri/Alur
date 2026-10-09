<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\EpicController;
use App\Http\Controllers\FlowController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'show'])->name('home');

Route::middleware('guest')->group(function () {
    Route::post('/demo', [LandingController::class, 'demo'])->middleware('throttle:20,1')->name('demo');
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

// Tautan undangan bisa dibuka sebelum masuk; menerimanya butuh akun.
Route::get('/gabung/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware('auth')->group(function () {
    Route::get('/beranda', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/proyek-baru', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/proyek', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/gabung/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

    Route::prefix('/p/{project}')->scopeBindings()->group(function () {
        // Semua anggota, termasuk pengamat.
        Route::middleware('can:view,project')->group(function () {
            Route::get('/', [ProjectController::class, 'show'])->name('projects.show');
            Route::get('/versi', [ProjectController::class, 'version'])->name('projects.version');
            Route::get('/alur', [FlowController::class, 'show'])->name('flow.show');
            Route::get('/aktivitas', [ProjectController::class, 'activity'])->name('projects.activity');
            Route::get('/arsip', [ProjectController::class, 'archived'])->name('projects.archived');
            Route::get('/epic', [EpicController::class, 'index'])->name('epics.index');
            Route::get('/epic/{epic}', [EpicController::class, 'show'])->withoutScopedBindings()->name('epics.show');
            Route::post('/keluar', [ProjectController::class, 'leave'])->name('projects.leave');
            Route::get('/{card:number}', [CardController::class, 'show'])->whereNumber('card')->name('cards.show');
        });

        // Anggota yang bisa mengerjakan kartu.
        Route::middleware('can:work,project')->group(function () {
            Route::post('/kartu', [CardController::class, 'store'])->name('cards.store');
            Route::post('/epic', [EpicController::class, 'store'])->name('epics.store');
            Route::put('/epic/{epic}', [EpicController::class, 'update'])->withoutScopedBindings()->name('epics.update');
            Route::delete('/epic/{epic}', [EpicController::class, 'destroy'])->withoutScopedBindings()->name('epics.destroy');
            Route::put('/{card:number}', [CardController::class, 'update'])->whereNumber('card')->name('cards.update');
            Route::post('/{card:number}/pindah', [CardController::class, 'move'])->name('cards.move');
            Route::post('/{card:number}/blokir', [CardController::class, 'block'])->name('cards.block');
            Route::delete('/{card:number}/blokir', [CardController::class, 'unblock'])->name('cards.unblock');
            Route::post('/{card:number}/arsipkan', [CardController::class, 'archive'])->name('cards.archive');
            Route::post('/{card:number}/pulihkan', [CardController::class, 'restore'])->name('cards.restore');
            Route::post('/{card:number}/komentar', [CardController::class, 'comment'])->name('cards.comment');
            Route::post('/{card:number}/checklist', [CardController::class, 'addChecklistItem'])->name('cards.checklist.store');
            Route::patch('/{card:number}/checklist/{item}', [CardController::class, 'toggleChecklistItem'])->withoutScopedBindings()->name('cards.checklist.toggle');
            Route::delete('/{card:number}/checklist/{item}', [CardController::class, 'removeChecklistItem'])->withoutScopedBindings()->name('cards.checklist.destroy');
        });

        // Pemilik proyek.
        Route::middleware('can:manage,project')->group(function () {
            Route::get('/pengaturan', [ProjectController::class, 'settings'])->name('projects.settings');
            Route::put('/pengaturan', [ProjectController::class, 'update'])->name('projects.update');
            Route::post('/kolom', [ColumnController::class, 'store'])->name('columns.store');
            Route::put('/kolom/urutan', [ColumnController::class, 'reorder'])->name('columns.reorder');
            Route::put('/kolom/{column}', [ColumnController::class, 'update'])->withoutScopedBindings()->name('columns.update');
            Route::delete('/kolom/{column}', [ColumnController::class, 'destroy'])->withoutScopedBindings()->name('columns.destroy');
            Route::post('/label', [LabelController::class, 'store'])->name('labels.store');
            Route::delete('/label/{label}', [LabelController::class, 'destroy'])->withoutScopedBindings()->name('labels.destroy');
            Route::put('/anggota/{user}', [MemberController::class, 'update'])->withoutScopedBindings()->name('members.update');
            Route::delete('/anggota/{user}', [MemberController::class, 'destroy'])->withoutScopedBindings()->name('members.destroy');
            Route::post('/undangan', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('/undangan/{invitation}', [InvitationController::class, 'destroy'])->withoutScopedBindings()->name('invitations.destroy');
        });
    });
});
