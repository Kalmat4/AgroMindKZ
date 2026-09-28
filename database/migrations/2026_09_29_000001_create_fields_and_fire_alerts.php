<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('lat', 9, 6);
            $table->decimal('lon', 9, 6);
            $table->decimal('area_ha', 10, 1);
            $table->string('crop_code', 20)->nullable();
            $table->unsignedInteger('price_per_ton')->nullable(); // тенге за тонну, вводит фермер
            $table->timestamps();
        });

        // Какие очаги уже отправлены по полю — чтобы не слать одну тревогу каждые 30 минут
        Schema::create('fire_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();
            $table->string('hotspot_key', 64);
            $table->string('threat_level', 16);
            $table->decimal('distance_km', 6, 1);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['field_id', 'hotspot_key']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('telegram_link_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });
        Schema::dropIfExists('fire_alerts');
        Schema::dropIfExists('fields');
    }
};
