<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $table = 'employee_positions';
    protected $fillable = [
        'position_name',
        'position_description',
        'position_status',
        'branch_id',
        'company_id',
    ];
}
