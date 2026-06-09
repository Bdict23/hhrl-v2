<?php

namespace App\Http\Controllers\Api\DataManagement;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\DataManagement\Bank;



class BankApiController extends Controller
{
    public function getBranchBanks(Request $request)
    {
        $branchId = $request->query('branch_id');
        $banks = Bank::where('branch_id', $branchId)->get();
        return response()->json($banks);
    }
}
