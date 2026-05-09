<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class SystemParameter extends Model
{
     protected $table = 'system_parameters';
    protected $fillable = [
        'module_id',
        'key',
        'name',
        'description',
        'created_by',
        'updated_by',
        'status',
        'branch_id',
        'created_at',
        'updated_at',
    ];
}
