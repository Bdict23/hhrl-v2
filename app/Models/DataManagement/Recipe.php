<?php

namespace App\Models\DataManagement;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Price;
use Illuminate\Support\Facades\Auth;

use App\Models\Business\Employee;

class Recipe extends Model
{
    protected $table = 'menus';
    protected $fillable = [
        'menu_name',
        'menu_image',
        'category_id',
        'status',
        'created_by',
        'reviewer_id',
        'approver_id',
        'approved_by', //added -ag 
        'company_id',
        'menu_code',
        'recipe_type',
        // added -ag
        'menu_description',
        'total_cost',
        'serving_size',
        'reviewed_date',
        'approved_date',
        'rejected_date',
    ];

    //added all function on -ag
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function approvedByEmployee()
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function preparer()
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function ingredients()
    {
        return $this->hasMany(Ingredient::class, 'menu_id');
    }

    public function rate()
    {
        $relation = $this->hasOne(Price::class, 'menu_id')->where('price_type', 'RATE');
        if (Auth::check() && Auth::user()->branch_id) {
            $relation->where('branch_id', Auth::user()->branch_id);
        }
        return $relation->latest('created_at');
    }

    public function cost()
    {
        $relation = $this->hasOne(Price::class, 'menu_id')->where('price_type', 'COST');
        if (Auth::check() && Auth::user()->branch_id) {
            $relation->where('branch_id', Auth::user()->branch_id);
        }
        return $relation->latest('created_at');
    }

    public function costHistory()
    {
        return $this->hasMany(Price::class, 'menu_id')
            ->where('price_type', 'COST')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Calculate live current market cost based on latest PO ingredient prices.
     */
    public function getCurrentMarketCost(): float
    {
        $metrics = app(\App\Services\DataManagement\UnitConversionService::class)->calculateRecipeLiveMetrics($this);
        return $metrics['current_cost'];
    }

    /**
     * Get complete dual-cost variance breakdown.
     */
    public function getCostVariance(): array
    {
        return app(\App\Services\DataManagement\UnitConversionService::class)->calculateRecipeLiveMetrics($this);
    }
}

