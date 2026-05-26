<?php

namespace App\Http\Controllers\Api;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Models\Transaction\Acknowledgement;

class RevolvingFundApiController extends Controller
{
    public function activeAcknowledgementForRevolvingFund(Request $request)
    {
        $branchId = $request->query('branch_id');
        $acknowledgement = Acknowledgement::where('branch_id', $branchId)->where('status', 'OPEN')->get();
        return response()->json($acknowledgement);

    }
}
