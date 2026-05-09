<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;

class ProductionOrder extends Model
{
    protected $table = 'production_orders';
    protected  $fillable = [
        'reference',
        'branch_id',
        'status',
        'prepared_by',
        'notes',
        'created_at',
        'updated_at',
    ];
}
