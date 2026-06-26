<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ReceivingAttachment extends Model
{
    protected $table = 'receiving_attachments'; // Keep the table name as it is in the database
    protected $fillable = [
        'receiving_id',
        'file_path',
    ];

    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }
}
