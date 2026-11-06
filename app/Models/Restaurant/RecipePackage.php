<?php

namespace App\Models\Restaurant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\DataManagement\Recipe;

class RecipePackage extends Model
{
    protected $table = 'recipe_packages';
    protected $fillable = [
        'recipe_id',
        'package_id',
        'quantity',
    ];


    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'package_id');
    }
}
