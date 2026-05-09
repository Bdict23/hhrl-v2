<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = "brands";
        protected $fillable = [
        'brand_name',
        'brand_code',
        'status',
        'company_id',
        'created_by',
        'updated_by',
    ];
}
