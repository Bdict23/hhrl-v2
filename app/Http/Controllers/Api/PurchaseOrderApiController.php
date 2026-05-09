<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Settings\SystemParameter;
use App\Models\Validation\Signatory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Inventory\PurchaseOrder;


class PurchaseOrderApiController extends Controller
{
    public function activePurchaseType(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = SystemParameter::where('module_id', 1)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'purchase_type%')->get();
        return response()->json($types);
    }
    public function activePurchaseTerm(Request $request){
        $branch_id = $request->query('branch_id');
        $term = SystemParameter::where('module_id', 1)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'purchase_term%')->get();
        return response()->json($term);
    }
    public function activeApprovers(Request $request){
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 1 )
            ->where('branch_id', $branch_id)
            ->get()->map(function($signatory){
                return [
                    'id'=> $signatory->employee_id,
                    'fullName'=> $signatory->employee->full_name,
                    'position'=> $signatory->employee->position->position_name,
                ];
            });
        return response()->json($approvers);
    }
    public function activeReviewers(Request $request){
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 1 )
            ->where('branch_id', $branch_id)
            ->get()->map(function($signatory){
                return [
                    'id'=> $signatory->employee_id,
                    'fullName'=> $signatory->employee->full_name,
                    'position'=> $signatory->employee->position->position_name,
                ];
            });
        return response()->json($approvers);
    }

}
