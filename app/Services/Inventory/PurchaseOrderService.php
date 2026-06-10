<?php

namespace App\Services\Inventory;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * This Purchase order are using dependecy injection -ben
     */
    protected $purchaseOrder;
    protected $branch;


    //  injects the model
    public function __construct(PurchaseOrder $purchaseOrder, Branch $branch)
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->branch = $branch;
    }

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($data['items'] as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['cost'];

                $itemsToInsert[] = [
                    'item_id'        => $item['id'],
                    'qty'            => $item['quantity'],
                    'price_level_id' => $item['price_id'],
                ];
            }

            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->purchaseOrder->where('from_branch_id', $branchId)
                ->whereYear('trans_date', $currentYear)
                ->count() + 1;
            $requisitionNumber = 'PO-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            // 1. Create the Header (requisition_infos)
            $po = $this->purchaseOrder->create([
                'event_id'                  => $data['event_id'],
                'requisition_number'        => $requisitionNumber,
                'from_branch_id'            => $branchId,
                'trans_date'                =>  now(),
                'merchandise_po_number'     => $data['merchandiseNumber'],
                'prepared_by'               => $data['preparedBy'],
                'reviewed_by'               => $data['reviewedBy'],
                'approved_by'               => $data['approvedBy'],
                'category'                  => 'PO',
                'term_type_id'              => $data['term_id'],
                'remarks'                   => $data['notes'] ?? null,
                'total_amount'              => $totalAmount,
                'supplier_id'               => $data['supplier_id'],
                'production_id'             => $data['production_id'],
                'type_id'                   => $data['type_id'] ?? null,
                'requisition_status'        => $data['status'],
            ]);

            // 3. Bulk Insert Items (1 Query instead of N Queries)
            $po->purchaseOrderItems()->createMany($itemsToInsert);

            return $po;
        });
    }

    public function update(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $totalAmount = 0;
            $itemsToInsert = [];

            foreach ($data['items'] as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['cost'];

                $itemsToInsert[] = [
                    'item_id'        => $item['id'],
                    'qty'            => $item['quantity'],
                    'price_level_id' => $item['price_id'],
                ];
            }
            $po = $this->purchaseOrder->findOrFail($data['purchase_order_id']);
            $po->update([
                'event_id'                  => $data['event_id'],
                'merchandise_po_number'     => $data['merchandiseNumber'],
                'prepared_by'               => $data['preparedBy'],
                'reviewed_by'               => $data['reviewedBy'],
                'approved_by'               => $data['approvedBy'],
                'term_type_id'              => $data['term_id'],
                'remarks'                   => $data['notes'] ?? null,
                'total_amount'              => $totalAmount,
                'supplier_id'               => $data['supplier_id'],
                'production_id'             => $data['production_id'],
                'type_id'                   => $data['type_id'] ?? null,
                'requisition_status'        => $data['status'],


            ]);
            //delete items first
            $po->purchaseOrderItems()->delete();
            $po->purchaseOrderItems()->createMany($itemsToInsert);
            return $po;
        });
    }
    public function reviewed(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $po = $this->purchaseOrder->findOrFail($data['purchase_order_id']);
            if ($data['reviewed_as'] === 'REVIEWED') {
                $po->update([
                    'requisition_status'        => $data['reviewed_as'],
                    'reviewed_date'        => now(),
                ]);
            } else {
                $po->update([
                    'requisition_status'        => $data['reviewed_as'],
                ]);
            }
            return $po;
        });
    }
    public function approval(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $po = $this->purchaseOrder->findOrFail($data['purchase_order_id']);

            if ($data['approved_as'] === 'APPROVED') {
                $po->update([
                    'requisition_status'   => $data['approved_as'],
                    'approved_date'        => now(),
                ]);
            } else if ($data['approved_as'] === 'REJECTED') {
                $po->update([
                    'requisition_status'   => $data['approved_as'],
                    'rejected_date'        => now(),
                ]);
            } else {
                $po->update([
                    'requisition_status'  => $data['approved_as'],
                ]);
            }
            return $po;
        });
    }
}
