<?php

namespace App\Http\Controllers\Api\Transaction;

use Illuminate\Routing\Controller;
use App\Models\Transaction\EmployeeAdvance;


use Illuminate\Http\Request;

class EmployeeCashAdvanceApiController extends Controller
{
    public $branchId;

    // used by : cash return module
    public function getBarnchActiveCashAdvances(Request $request)
    {
        $this->branchId = $request->query('branch_id');
        $data = EmployeeAdvance::query()
            ->where('branch_id', $this->branchId)
            ->where('status', 'OPEN')
            ->get();

        return response()->json($data);
    }
}
