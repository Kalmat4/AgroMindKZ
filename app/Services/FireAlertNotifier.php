<?php

namespace App\Services;

use App\Http\Controllers\TelegramMessageController;
use App\Models\FireAlert;
use App\Models\Field;

class FireAlertNotifier
{
    public function __construct(private FieldThreatService $threats) {}

    /**
     * Отправить фермеру тревогу по полю. $onlyNew — пропустить уже отправленные очаги
     * (для расписания); ручная проверка шлёт текущее состояние всегда.
     */
    public function notify(Field $field, array $assessment, bool $onlyNew): bool
    {
        $chatId = $field->user->telegram_chat_id;
        if (! $chatId) {
            return false;
        }

        $threats = array_filter($assessment['threats'], fn($t) => FieldThreatService::levelRank($t['level']) >= FieldThreatService::levelRank('high'));
        if ($onlyNew) {
            $sent    = $field->alerts()->pluck('hotspot_key')->all();
            $threats = array_filter($threats, fn($t) => ! in_array($t['key'], $sent, true));
            if (! $threats) {
                return false;
            }
        }

        $response = TelegramMessageController::sendMessage($chatId, $this->text($field, $assessment, array_values($threats)));
        if (! ($response['ok'] ?? false)) {
            return false;
        }

        foreach ($threats as $t) {
            FireAlert::firstOrCreate(
                ['field_id' => $field->id, 'hotspot_key' => $t['key']],
                ['threat_level' => $t['level'], 'distance_km' => $t['distance_km']],
            );
        }

        return true;
    }

    private function text(Field $field, array $a, array $threats): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

        if (! $threats) {
            $lines = ["✅ <b>{$e($field->name)}</b>: угрозы нет"];
            $lines[] = $a['hotspots']
                ? "В радиусе {$a['radius_km']} км термоточек: {$a['hotspots']}, ближайшая в {$a['nearest']} км, ветер не на поле."
                : "В радиусе {$a['radius_km']} км термоточек за 48 ч нет.";
        } else {
            $top   = $threats[0];
            $icon  = $top['level'] === 'critical' ? '🚨' : '🔥';
            $lines = ["{$icon} <b>" . mb_strtoupper(FieldThreatService::levelLabel($top['level'])) . ": {$e($field->name)}</b>"];
            $lines[] = "Очаг в <b>{$top['distance_km']} км</b> к {$top['direction']} от поля (NASA FIRMS, {$top['detected_at']}).";
            if (($top['kind'] ?? null) === 'stubble') {
                $lines[] = '🌾 Похоже на пал стерни на соседней пашне — такие палы часто уходят в степь.';
            }
            if ($top['downwind']) {
                $lines[] = "💨 Ветер {$a['wind']['speed']} м/с дует <b>от очага на поле</b>"
                    . ($top['eta_hours'] !== null ? ", огонь может подойти примерно через <b>{$top['eta_hours']} ч</b> (грубая оценка)." : '.');
            }
            if (count($threats) > 1) {
                $lines[] = 'Ещё опасных очагов рядом: ' . (count($threats) - 1) . '.';
            }
            $d = $a['damage'];
            if ($d['tenge']) {
                $lines[] = "💰 Под угрозой урожай ~{$d['tons']} т ({$e($d['crop'])}) на <b>" . number_format($d['tenge'], 0, ',', ' ') . ' ₸</b>.';
            }
            $lines[] = '';
            $lines[] = '<b>Что делать сейчас:</b>';
            $lines[] = '1. Опахать границу поля с наветренной стороны (полоса 4–6 м).';
            $lines[] = '2. Подготовить технику и воду, предупредить соседей.';
            $lines[] = '3. При виде огня или дыма — звонить 112.';
        }

        $fd = $a['fire_danger'];
        if (($fd['index'] ?? null) !== null) {
            $lines[] = '';
            $lines[] = "Пожароопасность погоды: {$fd['label']} (индекс HDW {$fd['index']}).";
        }
        $lines[] = 'Карта: ' . rtrim(config('app.url'), '/') . '/dashboard';

        return implode("\n", $lines);
    }
}
