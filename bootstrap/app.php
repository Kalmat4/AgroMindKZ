<?php

use App\Http\Controllers\TelegramMessageController;
use App\Http\Middleware\HandleInertiaRequests;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Эти ошибки НЕ отправляем
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Auth\Access\AuthorizationException::class,
            \Illuminate\Database\Eloquent\ModelNotFoundException::class,
            \Illuminate\Validation\ValidationException::class,
            \Illuminate\Session\TokenMismatchException::class,
            \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
            \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException::class,
        ]);

        $exceptions->report(function (Throwable $e) {

            $developersChatIDs = [
                'almat' => config('services.telegram.almat'),
                'beka' => config('services.telegram.beka')
            ];

            try {
                $url    = request()->fullUrl();
                $method = request()->method();
                $user   = Auth::check()
                    ? Auth::user()->name . " (ID: " . Auth::id() . ")"
                    : 'Гость';

                $message  = "🚨 <b>Ошибка на платформе</b>\n\n";
                $message .= "❗ <b>Тип:</b> <code>" . get_class($e) . "</code>\n";
                $message .= "💬 <b>Сообщение:</b> <code>{$e->getMessage()}</code>\n\n";
                $message .= "🌐 <b>URL:</b> <code>[{$method}] {$url}</code>\n";
                $message .= "👤 <b>Пользователь:</b> {$user}\n\n";
                $message .= "📂 <b>Файл:</b> <code>{$e->getFile()}</code>\n";
                $message .= "📍 <b>Строка:</b> <code>{$e->getLine()}</code>\n";
                $message .= "🕐 <b>Время:</b> " . Carbon::now()->format('d.m.Y H:i:s');

                // dd($developersChatIDs, $message);
                foreach ($developersChatIDs as $name => $chatID) {
                    TelegramMessageController::sendMessage($chatID, $message);
                }
            } catch (\Exception $telegramException) {
                Log::error('Telegram notify failed: ' . $telegramException->getMessage());
            }
        });
    })->create();
