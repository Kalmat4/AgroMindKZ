<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ZoneController;
use App\Http\Controllers\N8nProxyController;
use App\Http\Controllers\CropChatController;
use App\Http\Controllers\SubsidyController;
use App\Http\Controllers\YieldController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\EcologyController;
use App\Http\Controllers\ForecastController;
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

    Route::post('/n8n/analyze', [N8nProxyController::class, 'analyze'])->name('n8n.analyze');

    Route::get('/crop/sessions',         [CropChatController::class, 'sessions'])->name('crop.sessions');
    Route::get('/crop/sessions/{id}',    [CropChatController::class, 'sessionMessages'])->name('crop.session.messages');
    Route::delete('/crop/sessions/{id}', [CropChatController::class, 'deleteSession'])->name('crop.session.delete');
    Route::post('/n8n/crop',             [CropChatController::class, 'chat'])->name('n8n.crop');

    Route::get('/subsidies', [SubsidyController::class,  'index'])->name('subsidies');
    Route::get('/yield',     [YieldController::class,   'index'])->name('yield');
    Route::get('/ecology',   [EcologyController::class, 'index'])->name('ecology');
});
