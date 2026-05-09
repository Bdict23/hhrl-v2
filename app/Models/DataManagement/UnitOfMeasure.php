<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    protected $table = 'unit_of_measures';
    protected $fillable = [
        'unit_name',
        'unit_symbol',
        'company_id',
        'status',
    ];

}
