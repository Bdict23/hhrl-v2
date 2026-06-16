<?php

namespace App\Http\Controllers\Api\Business;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Business\Employee;
use App\Models\Transaction\EmployeeAdvance;



class EmployeeApiController extends Controller
{
    public function getActiveBranchEmployees(Request $request)
    {
        $branchId = $request->query('branch_id');
        $employees = Employee::where('branch_id', $branchId)->where('status', 'ACTIVE')->get();
        return response()->json($employees);
    }
    public function getActiveCashAdvancesForDisburse(Request $request)
    {
        $branchId = $request->query('branch_id');
        $lists = EmployeeAdvance::where('branch_id', $branchId)->where('status', 'FOR DISBURSEMENT')->get();
        return response()->json($lists);
    }
}
