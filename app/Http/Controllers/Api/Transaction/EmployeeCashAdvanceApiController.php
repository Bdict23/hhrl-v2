<?php

namespace App\Http\Controllers\Api\Transaction;

use Illuminate\Routing\Controller;
use App\Models\Transaction\EmployeeAdvance;
use App\Models\Business\Employee;


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

    public function getBranchCashAdvanceApprovers(Request $request)
    {
        $this->branchId = $request->query('branch_id');
        $approver = Employee::query()
            ->whereHas('signatory', function ($query) {
                $query->where('module_id', 88)
                    ->where('signatory_type', 'APPROVER')
                    ->where('branch_id', $this->branchId);
            })
            ->get();

        return response()->json($approver);
    }
}
