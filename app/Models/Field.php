<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'lat',
        'lon',
        'region_code',
        'area_ha',
        'crop_code',
        'price_per_ton',
    ];

    protected function casts(): array
    {
        return [
            'lat'           => 'float',
            'lon'           => 'float',
            'area_ha'       => 'float',
            'price_per_ton' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(FireAlert::class);
    }
}
