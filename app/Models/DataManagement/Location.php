<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'locations';
    protected $fillable = [
        'location_name',
        'location_group',
        'branch_id',
        'employee_id',
        'item_id',
    ];
}
