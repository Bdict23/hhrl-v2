<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'discounts';
    protected $fillable = [
        'code',
        'title',
        'type',
        'amount',
        'percentage',
        'start_date',
        'end_date',
        'description',
        'auto_apply',
        'branch_id',
        'company_id',
        'status',
        'created_by',
        'updated_by',
    ];
}
