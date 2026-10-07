<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\Settings\Position;
use App\Models\Settings\Signatory;
use App\Models\Settings\ModulePermission;
use App\Models\Settings\Module;
use App\Models\User;

class Employee extends Model
{
    //
    protected $table = "employees";
    protected $fillable = [
        'corporate_id',
        'name',
        'middle_name',
        'last_name',
        'position_id',
        'branch_id',
        'department_id',
        'contact_number',
        'status',
        'birth_date'
    ];
    protected $appends = ['full_name', 'position_name'];

    public function getPositionNameAttribute(): string
    {
        return $this->position->position_name;
    }


    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
    public function signatory()
    {
        return $this->hasMany(Signatory::class, 'employee_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'emp_id');
    }

    public function modulePermission()
    {
        return $this->hasMany(ModulePermission::class, 'employee_id');
    }

    public function getModulePermission($moduleName)
    {
        $moduleId = Module::where('module_name', $moduleName)->value('id');

        if (!$moduleId) {
            return 2; // or handle the case when the module is not found
        }
        $access = ModulePermission::where([['module_id', $moduleId], ['employee_id', $this->id]])->first();
        return $access->access ?? 2;
    }
}
