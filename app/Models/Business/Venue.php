<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Price;


class Venue extends Model
{
    protected $table = 'venues';
    protected $fillable = [
        'venue_name',
        'venue_code',
        'capacity',
        'description',
        'status',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function rate()
    {
        return $this->hasOne(Price::class, 'venue_id')->where('price_type', 'RATE')->latest();
    }
}
