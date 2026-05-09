<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\Restaurant\ProductionOrder;
use Illuminate\Routing\Controller;


class RestaurantApiController extends Controller
{
    // activeProductionOrder function : kuhaon niya tong mga production order nga active nga na create na ug na approved
    // used by : Purchase Order
    public function activeProductionOrder(Request $request){
        $branch_id = $request->query('branch_id');
        $productionOrder = ProductionOrder::query()->where('branch_id', $branch_id)->where('status', 'PENDING')->get();
        return response()->json($productionOrder);
        }


}
