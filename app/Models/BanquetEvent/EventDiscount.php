<?php

namespace App\Models\BanquetEvent;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Discount;

class EventDiscount extends Model
{
    protected $table = 'event_discounts';
    protected $fillable = [
        'branch_id',
        'discount_id',
        'event_id',
        'event_menu_id',
        'event_service_id',
        'event_venue_id',
        'type',
        'created_by',
        'amount',
        'created_at',
        'updated_at',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
    public function eventMenu()
    {
        return $this->belongsTo(EventMenu::class, 'event_menu_id');
    }
    public function eventService()
    {
        return $this->belongsTo(EventService::class, 'event_service_id');
    }
    public function eventVenue()
    {
        return $this->belongsTo(EventVenue::class, 'event_venue_id');
    }
}
