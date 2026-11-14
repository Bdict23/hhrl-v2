<?php

namespace App\Models\Restaurant;

use App\Models\DataManagement\Recipe;
use Illuminate\Database\Eloquent\Model;

class ProductionOrderRecipe extends Model
{
    protected $table = 'production_order_menus';

    protected $fillable = [
        'branch_id',
        'production_order_id',
        'menu_id',
        'qty',
        'created_at',
        'updated_at',
    ];

    public function menu()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
