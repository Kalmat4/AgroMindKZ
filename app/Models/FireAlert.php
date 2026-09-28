<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FireAlert extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'field_id',
        'hotspot_key',
        'threat_level',
        'distance_km',
    ];
}
