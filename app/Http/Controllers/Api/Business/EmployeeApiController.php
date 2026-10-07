<?php

namespace App\Http\Controllers\Api\Business;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Business\Employee;
use App\Models\Transaction\EmployeeAdvance;
use App\Models\Settings\Position;

class EmployeeApiController extends Controller
{
    public function getActiveBranchEmployees(Request $request)
    {
        $branchId = $request->query('branch_id');
        $employees = Employee::where('branch_id', $branchId)
            ->where('status', 'ACTIVE')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'label' => $emp->full_name,
                    'description' => $emp->position_name,
                ];
            });
        return response()->json($employees);
    }
    public function getActiveCashAdvancesForDisburse(Request $request)
    {
        $branchId = $request->query('branch_id');
        $lists = EmployeeAdvance::where('branch_id', $branchId)->where('status', 'FOR DISBURSEMENT')->get();
        return response()->json($lists);
    }
    public function getActiveBranchEmployeePosition(Request $request)
    {
        $companyId = $request->query('company_id');
        $positions = Position::where('company_id', $companyId)
            ->where('position_status', 'ACTIVE')
            ->get()->map(function ($position) {
                return [
                    'id' => $position->id,
                    'label' => $position->position_name,
                    'description' => $position->position_description,
                ];
            });
        return response()->json($positions);
    }
}
