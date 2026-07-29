<?php

namespace App\Http\Controllers\Api\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\DataManagement\Category;



class RecipeApiController extends Controller
{
    public function activeRecipeCategories(Request $request)
    {
        $company_id = $request->query('company_id');

        $categories = Category::query()
            ->where('company_id', $company_id)
            ->where('status', 'ACTIVE')
            ->where('category_type', 'MENU')
            ->get();

        return response()->json($categories);
    }
}
