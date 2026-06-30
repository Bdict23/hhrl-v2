<?php

namespace App\Services\Inventory;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Business\Branch;
use App\Models\Inventory\Receiving;
use App\Models\Inventory\Backorder;
use App\Models\Inventory\ReceivingAttachment;
use App\Models\DataManagement\Price;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderService
{
    /**
     * This Purchase order are using dependecy injection -ben
     */
    protected $purchaseOrder;
    protected $receiving;
    protected $receivingAttachment;
    protected $branch;
    protected $price;
    protected $backorder;


    //  injects the model
    public function __construct(
        PurchaseOrder $purchaseOrder,
        Branch $branch,
        Receiving $receiving,
        ReceivingAttachment $receivingAttachment,
        Price $price,
        Backorder $backorder
    ) {
        $this->purchaseOrder = $purchaseOrder;
        $this->branch = $branch;
        $this->receiving = $receiving;
        $this->receivingAttachment = $receivingAttachment;
        $this->price = $price;
        $this->backorder = $backorder;
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

    public function createReceiving(array $data): Receiving
    {
        return DB::transaction(function () use ($data) {
            $receivedAmt = 0;
            $itemsToInsert = [];
            $attachmentToInsert = [];
            $price = null;
            $backorderToInsert = [];
            $companyId = $data['company_id'];
            $backorderCount = 0;

            $requestInfo = PurchaseOrder::where('id', $data['requisition_id'])->first();

            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->receiving->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'REC-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            // 1. Create the Header (requisition_infos)
            $rec = $this->receiving->create([
                'reference'                 => $reference,
                'branch_id'                 => $branchId,
                'company_id'                => $companyId,
                'requisition_id'            => $data['requisition_id'],
                'receiving_type'            => $data['receiving_type'],
                'receiving_number'          => $data['receiving_number'],
                'waybill_number'          => $data['waybill_number'],
                'delivery_number'          => $data['delivery_number'],
                'invoice_number'          => $data['invoice_number'],
                'receiving_status'          => $data['status'],
                'remarks'                   => $data['note'],
                'prepared_by'               => $data['preparedBy'],
                'delivered_by'              => $data['deliveredBy'],
                'receive_amount'            => 0,
            ]);

            foreach ($data['attachments'] as $photo) {
                // 1. Extract the original file name
                $originalName = $photo->getClientOriginalName();

                // 2. Clean the filename (removes spaces/special characters) 
                // and append a timestamp to prevent duplicate file name collisions
                $cleanName =  Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time()
                    . '.' .
                    $photo->getClientOriginalExtension();
                // 3. Store it into your preferred directory (e.g., public disk)
                $path = $photo->storeAs('receiving_attachments', $cleanName, 'public');

                // 4. Save the $path or $cleanName to your attachments/receiving database table here...
                // $receiving->attachments()->create(['file_path' => $path]);
                $attachmentToInsert[] = [
                    'file_path' =>  $path,
                ];
            }

            $rec->attachments()->createMany($attachmentToInsert);

            foreach ($data['items'] as $item) {
                if ($item['received'] > 0) {
                    $receivedAmt += (float)$item['received'] * (float)$item['newCost'];

                    // NOTE:price will effecive imediately when status is final
                    if ($item['oldCost'] != $item['newCost']) {
                        $price = $this->price->create([
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'item_id'   => $item['item_id'],
                            'price_type' => $data['status'] == 'FINAL' ? 'COST' : 'TEMP_COST',
                            'amount' => $item['newCost'],
                            'created_by' => $data['preparedBy'],
                            'supplier_id' => $data['supplier_id'],
                        ]);
                    }

                    // for backorder
                    if ($item['toReceive'] > $item['received'] && $data['status'] == 'FINAL') {
                        $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                            ->where('item_id', $item['item_id'])
                            ->where('bo_type', 'PO')
                            ->first();
                        if (!$hasBackorder) {
                            $backorderToInsert[] = [
                                'item_id'           => $item['item_id'],
                                'status'            => 'ACTIVE',
                                'cancelled_date'    => null,
                                'bo_type'           => 'PO',
                                'remarks'           => $reference,
                                'branch_id'         => $branchId,
                                'company_id'        => $companyId,
                                'receiving_attempt' => 1,

                            ];
                        } else {
                            $hasBackorder->update([
                                'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                                'remarks'           => ', ' . $reference,
                            ]);
                        }
                        $backorderCount++;
                    }
                    //update backorder from active to fullfilled if toReceive is equal to recieved
                    elseif ($item['toReceive'] == $item['received'] && $data['status'] == 'FINAL') {
                        $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                            ->where('item_id', $item['item_id'])
                            ->where('bo_type', 'PO')
                            ->first();
                        if ($hasBackorder) {
                            $hasBackorder->update([
                                'status'            => 'FULFILLED',
                                'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                                'remarks'           => ', ' . $reference,
                            ]);
                        }
                    }
                    $itemsToInsert[] = [
                        'source_branch_id'  => $branchId,
                        'item_id'           => $item['item_id'],
                        'qty_in'            => $item['received'],
                        'status'            => $data['status'] == 'FINAL' ? 'FINAL' : 'TEMP',
                        'price_level_id'    => $price->id ?? $item['price_id'],
                        'transaction_type'  => 'RECEVING',
                        'final_date'        => $data['status'] == 'FINAL' ? now() : null,
                        'requisition_id'    => $data['requisition_id'],
                        'reference'         => $reference,
                    ];
                }
                // also for backorder
                elseif (!$item['notIncluded'] && $data['status'] == 'FINAL' && $item['toReceive'] > 0) {
                    $backorderCount++;
                    $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                        ->where('item_id', $item['item_id'])
                        ->where('bo_type', 'PO')
                        ->first();
                    if (!$hasBackorder) {
                        $backorderToInsert[] = [
                            'item_id'           => $item['item_id'],
                            'status'            => 'ACTIVE',
                            'cancelled_date'    => null,
                            'bo_type'           => 'PO',
                            'remarks'           => $reference,
                            'branch_id'         => $branchId,
                            'company_id'        => $companyId,
                            'receiving_attempt' => 1,
                        ];
                    } else {
                        $hasBackorder->update([
                            'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                            'remarks'           => ', ' . $reference,
                        ]);
                    }
                }
            }
            $rec->update(['receive_amount' => $receivedAmt]);
            if ($backorderCount == 0 && $data['status'] == 'FINAL') {
                $requestInfo->update(['requisition_status' => 'COMPLETED']);
            } elseif ($backorderCount > 0 && $data['status'] == 'FINAL') {
                $requestInfo->update(['requisition_status' => 'PARTIALLY FULFILLED']);
                if (count($backorderToInsert) > 0) {
                    $requestInfo->backorder()->createMany($backorderToInsert);
                }
            }
            $rec->cardex()->createMany($itemsToInsert);
            return $rec;
        });
    }
    public function updateReceiving(array $data): Receiving
    {
        return DB::transaction(function () use ($data) {
            $receivedAmt = 0;
            $itemsToInsert = [];
            $attachmentToInsert = [];
            $price = null;
            $backorderToInsert = [];
            $branchId = $data['branch_id'];
            $companyId = $data['company_id'];
            $backorderCount = 0;

            $requestInfo = PurchaseOrder::where('id', $data['requisition_id'])->first();

            $rec = $this->receiving->findOrFail($data['receiving_id']);

            // 1. Create the Header (requisition_infos)
            $rec->update([
                'receiving_number'          => $data['receiving_number'],
                'waybill_number'          => $data['waybill_number'],
                'delivery_number'          => $data['delivery_number'],
                'invoice_number'          => $data['invoice_number'],
                'receiving_status'          => $data['status'],
                'remarks'                   => $data['note'],
                'prepared_by'               => $data['preparedBy'],
                'delivered_by'              => $data['deliveredBy'],
                'receive_amount'            => 0,
            ]);

            foreach ($data['attachments'] as $photo) {
                // 1. Extract the original file name
                $originalName = $photo->getClientOriginalName();

                // 2. Clean the filename (removes spaces/special characters) 
                // and append a timestamp to prevent duplicate file name collisions
                $cleanName =  Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '_' . time()
                    . '.' .
                    $photo->getClientOriginalExtension();
                // 3. Store it into your preferred directory (e.g., public disk)
                $path = $photo->storeAs('receiving_attachments', $cleanName, 'public');

                // 4. Save the $path or $cleanName to your attachments/receiving database table here...
                $attachmentToInsert[] = [
                    'file_path' =>  $path,
                ];
            }

            //insert attachement path to the database
            $rec->attachments()->createMany($attachmentToInsert);

            // delete prev. entered cost
            $cardexIds = $rec->cardex()->pluck('price_level_id');
            Price::whereIn('id', $cardexIds)->where('price_type', 'TEMP_COST')->delete();

            //delete existing cardex to re-insert
            $rec->cardex()->delete();



            foreach ($data['items'] as $item) {
                if ($item['received'] > 0) {
                    $receivedAmt += (float)$item['received'] * (float)$item['newCost'];

                    // NOTE: price will effecive imediately when status is final
                    if ($item['oldCost'] != $item['newCost']) {
                        $price = $this->price->create([
                            'branch_id' => $branchId,
                            'company_id' => $companyId,
                            'item_id'   => $item['item_id'],
                            'price_type' => $data['status'] == 'FINAL' ? 'COST' : 'TEMP_COST',
                            'amount' => $item['newCost'],
                            'created_by' => $data['preparedBy'],
                            'supplier_id' => $data['supplier_id'],
                        ]);
                    }

                    // for backorder
                    if ($item['toReceive'] > $item['received'] && $data['status'] == 'FINAL') {
                        $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                            ->where('item_id', $item['item_id'])
                            ->where('bo_type', 'PO')
                            ->first();
                        if (!$hasBackorder) {
                            $backorderToInsert[] = [
                                'item_id'           => $item['item_id'],
                                'status'            => 'ACTIVE',
                                'cancelled_date'    => null,
                                'bo_type'           => 'PO',
                                'remarks'           => $rec->reference,
                                'branch_id'         => $branchId,
                                'company_id'        => $companyId,
                                'receiving_attempt' => 1,

                            ];
                        } else {
                            $hasBackorder->update([
                                'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                                'remarks'           => ', ' . $rec->reference,
                            ]);
                        }
                        $backorderCount++;
                    }
                    //update backorder from active to fullfilled if toReceive is equal to recieved
                    elseif ($item['toReceive'] == $item['received'] && $data['status'] == 'FINAL') {
                        $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                            ->where('item_id', $item['item_id'])
                            ->where('bo_type', 'PO')
                            ->first();
                        if ($hasBackorder) {
                            $hasBackorder->update([
                                'status'            => 'FULFILLED',
                                'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                                'remarks'           => ', ' . $rec->reference,
                            ]);
                        }
                    }
                    $itemsToInsert[] = [
                        'source_branch_id'  => $branchId,
                        'item_id'           => $item['item_id'],
                        'qty_in'            => $item['received'],
                        'status'            => $data['status'] == 'FINAL' ? 'FINAL' : 'TEMP',
                        'price_level_id'    => $price->id ?? $item['price_id'],
                        'transaction_type'  => 'RECEVING',
                        'final_date'        => $data['status'] == 'FINAL' ? now() : null,
                        'requisition_id'    => $data['requisition_id'],
                        'reference'         => $rec->reference,
                    ];

                    // set the price to null to avoid using it to the next item
                    $price = null;
                }
                // also for backorder
                elseif (!$item['notIncluded'] && $data['status'] == 'FINAL' && $item['toReceive'] > 0) {
                    $backorderCount++;
                    $hasBackorder = Backorder::where('requisition_id', $data['requisition_id'])
                        ->where('item_id', $item['item_id'])
                        ->where('bo_type', 'PO')
                        ->first();
                    if (!$hasBackorder) {
                        $backorderToInsert[] = [
                            'item_id'           => $item['item_id'],
                            'status'            => 'ACTIVE',
                            'cancelled_date'    => null,
                            'bo_type'           => 'PO',
                            'remarks'           => $rec->reference,
                            'branch_id'         => $branchId,
                            'company_id'        => $companyId,
                            'receiving_attempt' => 1,
                        ];
                    } else {
                        $hasBackorder->update([
                            'receiving_attempt' => $hasBackorder->receiving_attempt + 1,
                            'remarks'           => ', ' . $rec->reference,
                        ]);
                    }
                }
            }
            $rec->update(['receive_amount' => $receivedAmt]);
            if ($backorderCount == 0 && $data['status'] == 'FINAL') {
                $requestInfo->update(['requisition_status' => 'COMPLETED']);
            } elseif ($backorderCount > 0 && $data['status'] == 'FINAL') {
                $requestInfo->update(['requisition_status' => 'PARTIALLY FULFILLED']);
                if (count($backorderToInsert) > 0) {
                    $requestInfo->backorder()->createMany($backorderToInsert);
                }
            }
            $rec->cardex()->createMany($itemsToInsert);
            return $rec;
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


    public static function removeReceivingAttachment(int $receivingId, string $attacmentPath)
    {
        $path = ReceivingAttachment::where('receiving_id', $receivingId)->where('file_path', $attacmentPath)->first();
        if ($path) {
            $path->delete();
        }
    }

    public static function getEventReceivingAttachments(int $event, int $branch)
    {
        $purchaseIds = PurchaseOrder::where('event_id', $event)->where('from_branch_id', $branch)->get()->pluck('id');
        $receivingIds  = Receiving::whereIn('requisition_id', $purchaseIds)->get()->pluck('id');
        $attachments = ReceivingAttachment::whereIn('receiving_id', $receivingIds)->get();
        return $attachments;
    }
    public static function purchaseReceivedData(int $event, int $branch)
    {
        //get the list of purchase order first
        $purchaseIds = PurchaseOrder::where('event_id', $event)->where('from_branch_id', $branch)->get()->pluck('id');

        ///get the receiving data for each purchase order
        $receiving = Receiving::whereIn('requisition_id', $purchaseIds)->get();
        return $receiving;
    }
}
