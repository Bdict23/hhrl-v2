<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'banks';
    protected $fillable = [
        'branch_id',
        'bank_name',
        'bank_code',
        'bank_address',
        'contact_number',
        'email',
    ];
}
