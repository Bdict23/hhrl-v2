<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\UnitOfMeasure;
use App\Models\DataManagement\Price;
use App\Models\Inventory\FixedAsset\AssetCardex;

class Item extends Model
{
    protected $table = "items";
    protected $fillable = [
        'item_code',
        'item_description',
        'item_barcode',
        'company_id',
        'classification_id',
        'sub_class_id',
        'brand_id',
        'category_id',
        'uom_id',
        'orderpoint',
        'created_by',
    ];

    public function unit(){
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }
    public function cost(){
          return $this->hasOne(Price::class, 'item_id')->where('price_type', 'Cost')->latest('created_at')->with('supplier');
    }
    public function brand(){
        return $this->belongsTo(Brand::class,'brand_id');
    }
    public function classification(){
        return $this->belongsTo(Classification::class,'classification_id');
    }
    public function subClassification(){
        return $this->belongsTo(Classification::class,'sub_class_id');
    }
    public function category(){
        return $this->belongsTo(Category::class,'category_id');
    }
    public function assetCardex(){
        return $this->hasMany(AssetCardex::class, 'item_id');
    }
}
