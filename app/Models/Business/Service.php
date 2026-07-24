<?php

namespace App\Models\Business;

use App\Models\DataManagement\Price;
use App\Models\DataManagement\Category;
use Illuminate\Support\Facades\Auth;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    // table name
    protected $table = 'services';
    // fillable attributes
    protected $fillable = [
        'service_name',
        'service_type',
        'service_code',
        'service_description',
        'status',
        'branch_id',
        'created_by',
        'category_id',
        'has_multiplier',
        'updated_by',
        'isFree',
    ];


    public function rate()
    {
        return $this->hasOne(Price::class, 'service_id', 'id')
            ->where('price_type', 'RATE')
            ->where('branch_id', Auth::user()->branch_id)->latest('created_at');
    }
    // public function costPrice()
    // {
    //     return $this->hasOne(PriceLevel::class, 'service_id', 'id')
    //         ->where('price_type', 'COST')
    //         ->where('branch_id', auth()->user()->branch_id)->latest('created_at');
    // }

    // Define the relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }
}
