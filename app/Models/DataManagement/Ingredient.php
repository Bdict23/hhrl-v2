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

    public function uom()
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

    public function conversionFactor(): float
    {
        if (! $this->item || ! $this->uom_id) {
            return 1.0;
        }

        $fromUomId = $this->uom_id;
        $toUomId = $this->item->uom_id;

        if ($fromUomId == $toUomId) {
            return 1.0;
        }

        $conv = UnitConvertion::where(function ($q) {
            $q->where('item_id', $this->item_id)->orWhereNull('item_id');
        })
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->orderByRaw('item_id IS NULL ASC')
            ->first();

        return $conv ? (float) $conv->conversion_factor : 1.0;
    }
}
