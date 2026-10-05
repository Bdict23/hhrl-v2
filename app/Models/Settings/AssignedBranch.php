<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Branch;

class AssignedBranch extends Model
{
    protected $table = 'assigned_branches';
    protected $fillable = [
        'employee_id',
        'branch_id',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
