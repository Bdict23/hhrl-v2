<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'modules';

    public function permissions()
    {
        return $this->hasMany(ModulePermission::class, 'module_id');
    }
}
