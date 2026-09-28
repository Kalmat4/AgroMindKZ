<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\CropChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ZoneController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [AboutController::class, 'index'])->name('home');

Route::get('/about', fn() => Inertia::render('About'))->name('about');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::patch('/zone', [ZoneController::class, 'store'])->name('zone.store');
    Route::get('/zone/fires', [ZoneController::class, 'getFires'])->name('zone.fires');
    Route::post('/zone/hotspots', [ZoneController::class, 'hotspots'])->name('zone.hotspots');
    Route::post('/forecast/risks', [ForecastController::class, 'risks'])->name('forecast.risks');

    Route::get('/crop/sessions',         [CropChatController::class, 'sessions'])->name('crop.sessions');
    Route::get('/crop/sessions/{id}',    [CropChatController::class, 'sessionMessages'])->name('crop.session.messages');
    Route::delete('/crop/sessions/{id}', [CropChatController::class, 'deleteSession'])->name('crop.session.delete');
    Route::post('/n8n/crop',             [CropChatController::class, 'chat'])->middleware('throttle:20,1')->name('n8n.crop');
});
