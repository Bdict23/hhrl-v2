<?php

namespace App\Models\Restaurant;

use App\Models\DataManagement\Item;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\UnitConvertion;
use App\Models\DataManagement\UnitOfMeasure;
use Illuminate\Database\Eloquent\Model;

class ProductionOrderDetail extends Model
{
    protected $table = 'production_order_details';

    protected $fillable = [
        'production_order_id',
        'menu_id',
        'item_id',
        'qty',
        'status',
        'uom_id',
        'created_at',
        'updated_at',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function menu()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
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
