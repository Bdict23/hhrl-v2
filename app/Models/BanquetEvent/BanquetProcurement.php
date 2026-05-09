<?php

namespace App\Models\BanquetEvent;

use Illuminate\Database\Eloquent\Model;

class BanquetProcurement extends Model
{
    protected $table = 'banquet_procurements';
    protected $fillable = [
        'event_id',
        'document_number',
        'reference_number',
        'suggested_amount',
        'approved_by',
        'created_by',
        'noted_by',
        'branch_id',
        'updated_by',
        'notes',
        'create_at',
        'updated_at',
        'status',
        'services_included',
    ];
}
