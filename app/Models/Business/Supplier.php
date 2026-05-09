<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'company_id',
        'supp_name',
        'postal_address',
        'contact_no_1',
        'supp_address',
        'contact_no_2',
        'tin_number',
        'contact_person',
        'input_tax',
        'supplier_code',
        'email',
        'description',
    ];

    protected $table = "suppliers";
}

