<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CropChatSession extends Model
{
    protected $fillable = ['user_id', 'title', 'field_context'];

    protected function casts(): array
    {
        return ['field_context' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CropChatMessage::class);
    }
}
