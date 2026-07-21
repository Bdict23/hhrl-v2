<?php

namespace App\Http\Controllers\Api\Event;

use Illuminate\Routing\Controller;
use App\Models\Validation\Signatory;
use App\Models\BanquetEvent\EventLiquidation;

use Illuminate\Http\Request;

class EventLiquidationApiController extends Controller
{

    public function activeReviewers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 78)
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


    public function activeApprovers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 78)
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

    public function getCashReturnEventLiquidation(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $eventLiquidations = EventLiquidation::with('event')
            ->where('branch_id', $branch_id)
            ->where('status', 'FOR SETTLEMENT')
            ->whereDoesntHave('cashReturn')
            ->get()->map(function ($liquidation) {
                return [
                    'id' => $liquidation->id,
                    'reference' => $liquidation->reference,
                    'description' => $liquidation->event?->event_name,
                ];
            });
        return response()->json($eventLiquidations);
    }
}
