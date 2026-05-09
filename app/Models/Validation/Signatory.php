<?php

namespace App\Models\Validation;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Employee;

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

    public function employee(){
        return $this->belongsTo(Employee::class,'employee_id');
    }
}
