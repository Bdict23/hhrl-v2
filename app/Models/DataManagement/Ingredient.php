<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $table = 'recipes';
    protected $fillable = [
        'menu_id',
        'item_id',
        'qty',
        'cost', //added -ag
        'uom_id',
        'price_level_id',
    ];

    //added all function on -ag
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function unit_of_measurement()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function priceLevel()
    {
        return $this->belongsTo(Price::class, 'price_level_id');
    }
}

