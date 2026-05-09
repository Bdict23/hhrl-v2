<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Classification extends Model
{
    protected $table = 'classifications';
    protected $fillable = [
        'name',
        'classification_name',
        'classification_description',
        'category_id',
        'class_parent',
        'company_id'
    ];

    public function sub_classification()
    {
        return $this->hasMany(Classification::class, 'class_parent');
    }
}
