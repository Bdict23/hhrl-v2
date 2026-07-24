<?php

namespace App\Models\BanquetEvent;

use App\Models\DataManagement\Price;
use App\Models\Business\Venue;

use Illuminate\Database\Eloquent\Model;

class EventVenue extends Model
{
    protected $table = 'event_venues';
    protected $fillable = [
        'event_id',
        'venue_id',
        'qty',
        'price_id',
        'total_amount',
        'start_date',
        'start_time',
        'end_date',
        'end_time',
    ];


    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    public function price()
    {
        return $this->belongsTo(Price::class, 'price_id');
    }
}
