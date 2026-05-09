<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Business\Supplier;

class SupplierApiController extends Controller
{
    public function index(Request $request)
    {
        $company_id = $request->query('company_id');

        $suppliers = Supplier::where('company_id', $company_id)
            ->where('supplier_status', 'ACTIVE')
            ->get();

        return response()->json($suppliers);
    }
}

