<?php

namespace App\Models\BanquetEvent;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Price;
use App\Models\Business\Service;
use Illuminate\Support\Facades\Auth;


class EventService extends Model
{
    protected $table = 'event_services';
    protected $fillable = [
        'event_id',
        'service_id',
        'price_id',
        'total_amount',
        'qty',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    // external service
    public function price()
    {
        return $this->belongsTo(Price::class, 'price_id');
    }

    // internal service
    public function cost()
    {
        return $this->hasOne(Price::class, 'service_id', 'service_id')
            ->where('branch_id', Auth::user()->branch_id)
            ->where('price_type', 'COST')->latest('created_at');
    }
}
