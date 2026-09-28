<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Лок на 25 минут, а не на сутки по умолчанию: упавший прогон не должен остановить тревоги
Schedule::command('fires:check-fields')->everyThirtyMinutes()->withoutOverlapping(25);

Artisan::command('telegram:set-webhook', function () {
    $token  = config('services.telegram.token');
    $secret = config('services.telegram.webhook_secret');
    if (! $token || ! $secret) {
        $this->error('Нужны TG_TOKEN и TG_WEBHOOK_SECRET');
        return 1;
    }
    $url = rtrim(config('app.url'), '/') . '/telegram/webhook';
    $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/setWebhook", [
        'url'             => $url,
        'secret_token'    => $secret,
        'allowed_updates' => ['message'],
    ]);
    $this->line($url . ' → ' . $response->body());
})->purpose('Направить входящие сообщения Telegram-бота на сайт');
