<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Accounting\TemplateName;

class TransactionTemplate extends Model
{
    protected $table = 'actng_trans_templates';
    protected $fillable = [
        'company_id',
        'template_name_id',
        'description',
        'transaction_type',
        'module_type',
        'is_active',
        'created_by',
        'created_at',
        'updated_at',
        'status',
        'approved_by',
        'reviewed_by',
        'reviewed_date',
        'approved_date',
    ];


    public function templateName()
    {
        return $this->belongsTo(TemplateName::class, 'template_name_id');
    }
    public function transactionDetails()
    {
        return $this->hasMany(TemplateDetail::class, 'template_id');
    }
}
