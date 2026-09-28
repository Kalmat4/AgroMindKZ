<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        if (! $secret || ! hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            abort(403);
        }

        $chatId = $request->input('message.chat.id');
        $text   = trim((string) $request->input('message.text', ''));
        if (! $chatId || $text === '') {
            return response()->json(['ok' => true]);
        }

        if (preg_match('~^/start\s+([A-Za-z0-9]{32})$~', $text, $m)) {
            $user = User::where('telegram_link_token', $m[1])->first();
            if ($user) {
                // Один чат — один пользователь
                User::where('telegram_chat_id', $chatId)->where('id', '!=', $user->id)->update(['telegram_chat_id' => null]);
                $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_link_token' => null])->save();
                TelegramMessageController::sendMessage($chatId, "✅ Готово, {$this->e($user->name)}! Сюда будут приходить тревоги, если рядом с вашими полями появится огонь.\n\nПоля отмечаются на карте: " . rtrim(config('app.url'), '/') . '/dashboard');
            } else {
                TelegramMessageController::sendMessage($chatId, 'Ссылка устарела. Нажмите «Подключить Telegram» на сайте ещё раз.');
            }
        } elseif (str_starts_with($text, '/start')) {
            TelegramMessageController::sendMessage($chatId, "🔥 AgroMind KZ — тревоги о пожарах рядом с вашими полями.\nЧтобы подключиться, нажмите «Подключить Telegram» на сайте " . rtrim(config('app.url'), '/'));
        }

        return response()->json(['ok' => true]);
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES);
    }
}
