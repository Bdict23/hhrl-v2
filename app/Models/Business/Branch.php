<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\Settings\SystemParameter;

class Branch extends Model
{
    //
   protected $table = "branches";

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function ceilingAmount()
    {
        return $this->hasOne(SystemParameter::class, 'branch_id')->where("key", "ceiling_amount");
    }
    public function maxExpenditurePercentage()
    {
        return $this->hasOne(SystemParameter::class, 'branch_id')->where("key", "max_expenditure_percentage");

    }
}
