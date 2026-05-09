<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\Settings\Position;

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
    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function position(){
        return $this->belongsTo(Position::class, 'position_id');
    }
}
