<?php

namespace App\Models\Inventory\FixedAsset;
use App\Models\Business\Employee;
use Illuminate\Database\Eloquent\Model;

class AssetBatchHeader extends Model
{
    protected $table = 'batch_properties';
     protected $fillable = [
         'reference',
         'status',
         'type_id',
         'requisition_id',
         'branch_id',
         'note',
         'purpose',
         'prepared_by',
         'approved_by',
         'reviewed_by',
         'approved_date',
         'reviewed_date',
         'issued_date',
         'created_at',
         'updated_at',
     ];

     public function preparedBy()
     {
         return $this->belongsTo(Employee::class, 'prepared_by');
     }
     public function reviewedBy()
     {
         return $this->belongsTo(Employee::class, 'reviewed_by');
     }
     public function approvedBy()
     {
         return $this->belongsTo(Employee::class, 'approved_by');
     }
     public function assetBatchDetails()
     {
         return $this->hasMany(AssetBatchDetail::class, 'batch_id');
     }
}
