<?php

namespace App\Http\Controllers\Api\DataManagement;

use Illuminate\Routing\Controller;
use App\Models\DataManagement\Category;
use App\Models\DataManagement\Brand;
use App\Models\DataManagement\Classification;
use App\Models\DataManagement\UnitOfMeasure;
use App\Models\Settings\SystemParameter;


use Illuminate\Http\Request;

class ItemApiController extends Controller
{
    public function activeItemCategory(Request $request)
    {
        $company_id = $request->query('company_id');
        $result = Category::where('company_id', $company_id)
            ->where('status', 'ACTIVE')
            ->get()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'label' => $category->category_name,
                    'description' => $category->category_description,
                ];
            });
        return response()->json($result);
    }

    public function activeItemBrand(Request $request)
    {
        $company_id = $request->query('company_id');
        $result = Brand::where('company_id', $company_id)
            ->where('status', 'ACTIVE')
            ->get()->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'label' => $brand->brand_name,
                    'description' => $brand->brand_description,
                ];
            });
        return response()->json($result);
    }

    public function activeItemClassification(Request $request)
    {
        $company_id = $request->query('company_id');
        $result = Classification::where('company_id', $company_id)
            ->where('status', 'ACTIVE')
            ->where('class_parent',  null)
            ->get()->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'label' => $brand->classification_name,
                    'description' => $brand->classification_description,
                ];
            });
        return response()->json($result);
    }

    public function activeItemSubClassification(Request $request)
    {
        $company_id = $request->query('company_id');
        $result = Classification::where('company_id', $company_id)
            ->where('status', 'ACTIVE')
            ->where('class_parent', 'IS NOT', null)
            ->get()->map(function ($subClass) {
                return [
                    'id' => $subClass->id,
                    'label' => $subClass->classification_name,
                    'description' => $subClass->classification_description,
                ];
            });
        return response()->json($result);
    }

    public function measuredSymbol(Request $request)
    {
        $result = SystemParameter::query()
            ->where('status', 'ACTIVE')
            ->where('key', function ($query) use ($request) {
                $query->select('name')
                    ->from('system_parameters') // Replace with actual table name if different
                    ->where('id', $request->query('measure_type_id'));
            })->orderBy('sequence', 'asc')
            ->get(['id', 'name as label', 'description']);

        return response()->json($result);
    }
    public function measuredType()
    {
        $result = SystemParameter::where('status', 'ACTIVE')
            ->where('key', 'measure_type')
            ->get()->map(function ($symbol) {
                return [
                    'id' => $symbol->id,
                    'label' => $symbol->name,
                    'description' => $symbol->description,
                ];
            });
        return response()->json($result);
    }
}
