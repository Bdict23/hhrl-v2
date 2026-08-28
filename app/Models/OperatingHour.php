<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatingHour extends Model
{
    use HasFactory;
    protected $table = "pickle_operating_hours";
    protected $fillable = [
        'day_of_week',
        'opening_time',
        'closing_time',
        'is_closed',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public static function days(): array
    {
        return [
            'monday'    => 'Monday',
            'tuesday'   => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday'  => 'Thursday',
            'friday'    => 'Friday',
            'saturday'  => 'Saturday',
            'sunday'    => 'Sunday',
        ];
    }
}
