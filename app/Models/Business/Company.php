<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    //
    protected $table = "companies";
    protected $fillable = [
        'company_name',
        'company_code',
        'company_tin',
        'company_type',
        'company_description',
        'company_status',
        'company_logo',
        'created_at',
        'updated_at',
    ];
}
