<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;
use App\Models\Business\Branch;

class Signatory extends Model
{
    protected $table = "signatories";
    protected $fillable = [
        'signatory_name',
        'employee_id',
        'signatory_type',
        'module_id',
        'company_id',
        'branch_id',

    ];
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
