<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class TemplateName extends Model
{
   protected $table = 'actng_template_names';
    protected $fillable = [
        'template_name',
        'company_id',
        'created_by',
        'is_active',
        'created_at',
        'updated_at',
    ];
}
