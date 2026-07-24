<?php

namespace App\Models\BanquetEvent;

use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\Price;


use Illuminate\Database\Eloquent\Model;

class EventMenu extends Model
{
    protected $table = 'event_menus';
    protected $fillable = [
        'event_id',
        'menu_id',
        'note',
        'qty',
        'price_id',
        'total_amount',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function price()
    {
        return $this->belongsTo(Price::class, 'price_id');
    }

    public function discounts()
    {
        return $this->hasMany(EventDiscount::class, 'event_menu_id');
    }
}
