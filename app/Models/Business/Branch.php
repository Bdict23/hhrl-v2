<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    //
   protected $table = "branches";

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
