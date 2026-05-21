<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
protected $table = "customers";
protected $fillable = [
        'customer_fname',
        'customer_lname',
        'customer_mname',
        'suffix',
        'contact_person',
        'contact_person_relation',
        'gender',
        'contact_no_1',
        'contact_no_2',
        'customer_address',
        'email',
        'tin',
        'birthday',
        'branch_id',
    ];

protected $appends = ['full_name'];

public function getFullNameAttribute(): string
{
    return "{$this->customer_fname}  {$this->customer_mname} {$this->customer_lname}";
}

}
