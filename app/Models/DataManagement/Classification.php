<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{
    protected $table = 'classifications';
    protected $fillable = [
        'classification_name',
        'classification_description',
        'category_id',
        'class_parent',
        'company_id'
    ];

    public function classificationParent()
    {
        return $this->belongsTo(Classification::class, 'class_parent');
    }
}
