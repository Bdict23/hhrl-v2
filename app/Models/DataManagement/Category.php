<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
   protected $table = 'categories';
    protected $fillable = [
        'category_name',
        'category_description',
        'category_type',
        'created_by',
        'updated_by',
        'company_id',
    ];
}
