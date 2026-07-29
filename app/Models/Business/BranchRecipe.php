<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Recipe;


class BranchRecipe extends Model
{
    protected $table = 'branch_menu_recipes';
    protected $fillable = [
        'branch_menu_id',
        'menu_id',
        'default_qty',
        'bal_qty',
    ];


    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'menu_id');
    }

    public function branchMenu()
    {
        return $this->belongsTo(RecipeController::class, 'branch_menu_id');
    }

    public function activeBranchMenu()
    {
        return $this->belongsTo(RecipeController::class, 'branch_menu_id')->where('is_available', true);
    }
}
