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
        'measure_type_id',
        'measure_value',
        'measure_symbol',
        'created_by',
        'updated_by',
        'unit_description',
    ];
}
