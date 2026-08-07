<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\BanquetEvent\Event;
use App\Models\Validation\Signatory;


class BanquetEventApiController extends Controller
{
    public function activeEvent(Request $request)
    {
        $branch_id = $request->query('branch_id');

        $events = Event::query()
            ->where('branch_id', $branch_id)
            ->where('status', 'CONFIRMED')
            ->where('liquidation_status', 'PENDING')
            ->whereHas('budgetAllocation', function ($query) {
                $query->where('status', 'APPROVED');
            })
            ->whereDoesntHave('banquetEventLiquidation')
            ->get();

        return response()->json($events);
    }

    public function getFundedEvent(Request $request)
    {
        $branch_id = $request->query('branch_id');

        $events = Event::query()
            ->where('branch_id', $branch_id)
            ->where('status', 'CONFIRMED')
            ->where('liquidation_status', 'PENDING')
            ->whereHas('budgetAllocation', function ($query) {
                $query->where('status', 'APPROVED');
            })
            ->whereDoesntHave('banquetEventLiquidation')
            ->get();

        return response()->json($events);
    }

    public function activeReviewers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $reviewers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 38)
            ->where('branch_id', $branch_id)
            ->get()->map(function ($signatory) {
                return [
                    'id' => $signatory->employee_id,
                    'full_name' => $signatory->employee->full_name,
                    'position' => $signatory->employee->position->position_name,
                ];
            });
        return response()->json($reviewers);
    }

    public function activeApprovers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $reviewers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 38)
            ->where('branch_id', $branch_id)
            ->get()->map(function ($signatory) {
                return [
                    'id' => $signatory->employee_id,
                    'full_name' => $signatory->employee->full_name,
                    'position' => $signatory->employee->position->position_name,
                ];
            });
        return response()->json($reviewers);
    }

    public $branchId = null;
    public function forProcumentEvent(Request $request)
    {

        $branch_id = $request->query('branch_id');
        $this->branchId = $branch_id;
        $events = Event::query()
            ->where('branch_id', $branch_id)
            ->where('status', 'CONFIRMED')
            ->where('liquidation_status', 'PENDING')
            ->whereDoesntHave('budgetAllocation', function ($query) {
                $query->where('branch_id', $this->branchId)
                    ->whereIn('status', ['PENDING', 'PREPARING', 'APPROVED']);
            })
            ->get();
        $this->branchId = null;

        return response()->json($events);
    }
}
