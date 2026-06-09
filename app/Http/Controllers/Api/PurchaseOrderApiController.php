<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\Settings\SystemParameter;
use App\Models\Validation\Signatory;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Receiving;


class PurchaseOrderApiController extends Controller
{
    public function activePurchaseType(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $types = SystemParameter::where('module_id', 1)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'purchase_type%')->get();
        return response()->json($types);
    }
    public function activePurchaseTerm(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $term = SystemParameter::where('module_id', 1)->where('branch_id', $branch_id)->where('status', 'ACTIVE')->where('key', 'LIKE', 'purchase_term%')->get();
        return response()->json($term);
    }
    public function activeApprovers(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'APPROVER')
            ->where('module_id', 1)
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
        $approvers = Signatory::with('employee')
            ->where('signatory_type', 'REVIEWER')
            ->where('module_id', 1)
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
    public function receivedPurchaseOrderFilter1(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $purchaseOrder = PurchaseOrder::query()->where('from_branch_id', $branch_id)
            ->whereDoesntHave('assetBatchHeader')
            ->whereIn('requisition_status', ['PARTIALLY FULFILLED', 'COMPLETED'])
            ->get();
        return response()->json($purchaseOrder);
    }
    public function activePurchaseOrder(Request $request)
    {
        $branch_id = $request->query('branch_id');
        $purchaseOrder = PurchaseOrder::query()->where('from_branch_id', $branch_id)
            ->whereDoesntHave('assetBatchHeader')
            ->whereIn('requisition_status', ['PARTIALLY FULFILLED', 'TO RECEIVE'])
            ->get();
        return response()->json($purchaseOrder);
    }
    public function getReceivedItems(Request $request)
    {
        $purchase_order_id = $request->query('purchase_order_id');
        $purchaseOrderItems = PurchaseOrder::with('purchaseOrderItems')->where('id', $purchase_order_id)->first();
        return response()->json($purchaseOrderItems);
    }
    public function getReceivingReferences(Request $request)
    {
        $purchase_order_id = $request->query('purchase_order_id');
        $receivingReferences = Receiving::where('requisition_id', $purchase_order_id)->get();
        return response()->json($receivingReferences);
    }
    public function eventPurchaseOrder(Request $request) // use on cash return screen
    {
        $event = $request->query('event_id');
        $purchaseOrder = PurchaseOrder::query()->where('event_id', $event)
            ->where('requisition_status', 'TO RECEIVE')
            ->get();
        return response()->json($purchaseOrder);
    }
    public function nonEventPurchaseOrder(Request $request) // used on cash return screen
    {
        $purchaseOrder = PurchaseOrder::query()->whereNull('event_id')
            ->where('requisition_status', 'TO RECEIVE')
            ->get();
        return response()->json($purchaseOrder);
    }
}
