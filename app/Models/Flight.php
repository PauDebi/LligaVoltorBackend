<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flight extends Model
{
    protected $fillable = [
        'igc_file',
        'user_id',
        'max_altitude',
        'distance',
        'points',
        'takeoff_time',
        'landing_time',
        'glider-type',
        'category',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
