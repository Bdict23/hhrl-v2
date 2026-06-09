<?php

namespace App\Http\Controllers\Api\Transaction;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Business\Employee;
use App\Models\Transaction\AdvancesForLiquidation;





class AflApiController extends Controller
{
    public $branchId;
    public function getBranchApprovers(Request $request)
    {
    $this->branchId = $request->query('branch_id');
    $approver = Employee::query()
        ->whereHas('signatory', function ($query) {
            $query->where('module_id', 70)
                ->where('signatory_type', 'APPROVER')
                ->where('branch_id', $this->branchId);
            })
        ->get();

     return response()->json($approver);

    }
    public function getBranchDisbursers(Request $request)
    {
        $this->branchId = $request->query('branch_id');
        $disbursers = Employee::query()
        ->where('branch_id', $this->branchId)
        ->whereHas('modulePermission', function ($query) {
            $query->where('module_id', 70)
            ->where('access', 1);
            })
        ->get();

     return response()->json($disbursers);
    }

    public function getActiveAdvancesForLiquidation(Request $request)
    {
        $this->branchId = $request->query('branch_id');
        $afl = AdvancesForLiquidation::query()
        ->where('branch_id', $this->branchId)
        ->where('status','OPEN')
        ->get();

        return response()->json($afl);
    }
}
