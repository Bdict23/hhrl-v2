<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class UnitConvertion extends Model
{
    protected $table = 'unit_conversions';
    protected $fillable = [
        'item_id', //added -ag
        'from_uom_id',
        'to_uom_id',
        'conversion_factor',
        'created_by',
        'updated_by',
    ];

    //added all function on -ag
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function fromUnit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'from_uom_id');
    }

    public function toUnit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'to_uom_id');
    }
}

