<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Model;
use App\Models\DataManagement\Recipe;


class RecipeController extends Model
{
    protected $table = 'branch_menus';
    protected $fillable = [
        'branch_id',
        'control_name',
        'is_available',
        'start_date',
        'end_date',
        'mon',
        'tue',
        'wed',
        'thu',
        'fri',
        'sat',
        'sun',
        'created_by',
        'notes',
    ];


    public function banquetMenu()
    {
        return $this->belongsTo(Recipe::class, 'menu_id')->where('recipe_type', 'Banquet');
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
    public function Branchrecipes()
    {
        return $this->hasMany(BranchRecipe::class, 'branch_menu_id');
    }
}
