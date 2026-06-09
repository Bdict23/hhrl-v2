<?php

namespace App\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Settings\Signatory;


use Illuminate\Http\Request;

class PettyCashVoucherApiController extends Controller
{
    public function getActivePettyCashVouchers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = PettyCashVoucher::where('branch_id', $branch_id)->where('status', 'OPEN')->get();
        return response()->json($types);
    }
    public function getForDisbursementPcv(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $pcv = PettyCashVoucher::where('branch_id', $branch_id)
            ->where('status', 'OPEN')
            ->where(function ($query) {
                // Runs an inner correlated subquery to calculate the snapshot sum dynamically
                $query->where('total_amount', '<', function ($subQuery) {
                    $subQuery->selectRaw('SUM(amount)')
                        ->from('pcv_liquidation_snapshots')
                        ->whereColumn('pcv_id', 'petty_cash_vouchers.id');
                });
            })
            ->whereDoesntHave('reimbursements')
            ->get();
        return response()->json($pcv);
    }
    public function getForCashReturnPcv(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $pcv = PettyCashVoucher::where('branch_id', $branch_id)
            ->where('status', 'OPEN')
            ->where(function ($query) {
                $query->whereRaw('total_amount > (
                SELECT COALESCE(SUM(amount_returned), 0) 
                FROM cash_returns 
                WHERE cash_returns.pcv_id = petty_cash_vouchers.id
            ) + COALESCE((
                SELECT SUM(amount) 
                FROM pcv_liquidation_snapshots 
                WHERE pcv_liquidation_snapshots.pcv_id = petty_cash_vouchers.id
            ), 0)');
            })
            ->get();
        return response()->json($pcv);
    }

    public function getReimbursementApprovers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')->where('signatory_type', 'APPROVER')
            ->where('module_id', 87)
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
}
