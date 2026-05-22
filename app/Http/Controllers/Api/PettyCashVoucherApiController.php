<?php

namespace App\Http\Controllers\Api;
use Illuminate\Routing\Controller;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Settings\SystemParameter;

use Illuminate\Http\Request;

class PettyCashVoucherApiController extends Controller
{
    public function activeDisbursementTypePettyCashVoucher(Request $request)
    {
        $typeId = SystemParameter::where('module_id', 68)->where('status', 'ACTIVE')->where('key', 'pcv_type')->get()->first()->id;
        $branch_id = $request->query('branch_id');
        $types = PettyCashVoucher::where('branch_id', $branch_id)->where('status', 'OPEN')->where('type_id', $typeId)->get();
        return response()->json($types);
    }
}
