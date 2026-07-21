<?php

namespace App\Http\Controllers\Api\Inventory;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Inventory\Receiving;
use App\Models\Settings\SystemParameter;
use App\Models\Business\Department;
use App\Models\Validation\Signatory;




class ReceivingApiController extends Controller
{
    public function activeReceivingNumber(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = Receiving::where('branch_id', $branch_id)
            ->where('receiving_status',  'FINAL')
            ->where('receiving_type',  'PO')
            ->orderBy('created_at', 'desc')
            ->where('reference', '!=', 'N/A')
            ->get();
        return response()->json($types);
    }

    public function withdrawalType(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = SystemParameter::where('module_id', 2)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'withdrawal_type%')->get();
        return response()->json($types);
    }

    public function activeDepartment(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = Department::where('branch_id', $branch_id)->where('department_status', 'ACTIVE')->get();
        return response()->json($types);
    }
    public function activeApprovers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 2)
            ->where('branch_id', $branch_id)
            ->get()->map(function ($signatory) {
                return [
                    'id' => $signatory->employee_id,
                    'fullName' => $signatory->employee->full_name,
                    'position' => $signatory->employee->position->position_name,
                ];
            });
        return response()->json($approvers);
    }
    public function activeReviewers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $reviewers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 2)
            ->where('branch_id', $branch_id)
            ->get()->map(function ($signatory) {
                return [
                    'id' => $signatory->employee_id,
                    'fullName' => $signatory->employee->full_name,
                    'position' => $signatory->employee->position->position_name,
                ];
            });
        return response()->json($reviewers);
    }
}
