<?php

namespace App\Console\Commands;

use App\Models\Field;
use App\Services\FieldThreatService;
use App\Services\FireAlertNotifier;
use Illuminate\Console\Command;

class CheckFieldFires extends Command
{
    protected $signature = 'fires:check-fields';

    protected $description = 'Проверить термоточки у полей фермеров и отправить новые тревоги в Telegram';

    public function handle(FieldThreatService $threats, FireAlertNotifier $notifier): int
    {
        $fields = Field::with('user')
            ->whereHas('user', fn($q) => $q->whereNotNull('telegram_chat_id'))
            ->get();

        $sent = 0;
        foreach ($fields as $field) {
            $assessment = $threats->assess($field);
            if ($notifier->notify($field, $assessment, onlyNew: true)) {
                $sent++;
            }
        }

        $this->info("Полей: {$fields->count()}, отправлено тревог: {$sent}");

        return self::SUCCESS;
    }
}
