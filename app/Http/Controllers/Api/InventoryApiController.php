<?php

namespace App\Http\Controllers\Api;
use App\Models\Settings\SystemParameter;
use Illuminate\Routing\Controller;
use App\Models\Validation\Signatory;


use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    public function activeAssetRegistrationType(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = SystemParameter::where('module_id', 84)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'asset_registration_type%')->get();
        return response()->json($types);
    }
    public function activeAssetRegistrationReviewers(Request $request){
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 84 )
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
    public function activeAssetRegistrationApprovers(Request $request){
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 84 )
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
