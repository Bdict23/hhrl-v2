<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use App\Models\Business\Company;

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

    public function businessUnit()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function templates()
    {
        return $this->hasMany(TransactionTemplate::class, 'template_name_id');
    }

    public function pettyCashVouchers()
    {
        return $this->hasManyThrough(
            \App\Models\Transaction\PettyCashVoucher::class,
            TransactionTemplate::class,
            'template_name_id',
            'template_id',
            'id',
            'id'
        );
    }
}
