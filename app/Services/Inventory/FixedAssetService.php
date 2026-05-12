<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use App\Models\Inventory\FixedAsset\AssetBatchDetail;
use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Business\Branch;
use App\Models\Inventory\Cardex;
use Carbon\Carbon;

class FixedAssetService
{
    protected $assetBatchHeader;
    protected $assetBatchDetail;
    protected $branch;

    public function __construct(AssetBatchHeader $assetBatchHeader, AssetBatchDetail $assetBatchDetail, Branch $branch)
    {
        $this->assetBatchHeader = $assetBatchHeader;
        $this->assetBatchDetail = $assetBatchDetail;
        $this->branch = $branch;
    }

    public function createBatch(array $data): AssetBatchHeader
    {
        return DB::transaction(function () use ($data) {
            $itemsToInsert = [];

            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;

            $currentYear = now()->year;
            $yearlyCount = $this->assetBatchHeader
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            // Generate Batch Reference: FAB-BRANCH-MMYY-01
            $reference = 'FAB-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            // This counter is for the individual asset tags suffix
            $assetTagCounter = 1;

            foreach ($data['items'] as $item) {
                if ($item['is_serialized']) {
                    /**
                     * SERIALIZED LOGIC:
                     * Create one individual database entry for each serial number.
                     */
                    foreach ($item['serialized_items'] as $subItem) {
                        $usefulLife = (int) ($subItem['useful_life'] ?? 0);

                        // Generate Unique Asset Tag for this specific unit
                        $code = $reference . '-' . $item['item_code'] . '-' . str_pad($assetTagCounter, 3, '0', STR_PAD_LEFT);

                        $itemsToInsert[] = [
                            'code'        => $code,
                            'item_id'     => $item['item_id'],
                            'branch_id'   => $branchId,
                            'serial'      => $subItem['serial_number'],
                            'cost'        => $item['sub_total'] / $item['quantity'], // Unit cost
                            'lifespan'    => $usefulLife,
                            'span_ended'  => now()->addYears($usefulLife),
                            'condition'   => $subItem['condition'],
                            'qty'         => 1, // Always 1 for serialized
                            'location'    => $subItem['location'] ?? null,
                        ];
                        $assetTagCounter++;
                    }
                } else {
                    /**
                     * NON-SERIALIZED LOGIC:
                     * Create a single database entry with the total quantity.
                     */
                    $usefulLife = (int) ($item['useful_life'] ?? 0);
                    $code = 'FAB-' . $branchCode . '-' . $item['item_code'];

                    $itemsToInsert[] = [
                        'code'        => $code,
                        'item_id'     => $item['item_id'],
                        'branch_id'   => $branchId,
                        'serial'      => null,
                        'cost'        => $item['cost'] * $item['quantity'],
                        'lifespan'    => $usefulLife,
                        'span_ended'  => now()->addYears($usefulLife),
                        'condition'   => $item['condition'],
                        'qty'         => $item['quantity'],
                        'location'    => $item['location'] ?? null,
                    ];
                }
            }

            // Create the Header
            $batchHeader = $this->assetBatchHeader->create([
                'reference'      => $reference,
                'status'         => $data['status'] ?? 'PENDING',
                'type_id'        => $data['type_id'],
                'requisition_id' => $data['requisition_id'],
                'branch_id'      => $branchId,
                'note'           => $data['note'] ?? null,
                'purpose'        => $data['purpose'] ?? null,
                'prepared_by'    => $data['prepared_by'],
                'reviewed_by'    => $data['reviewed_by'],
                'approved_by'    => $data['approved_by'],
                'issued_date'    => $data['issued_date'],
            ]);

            // Bulk Insert Details
            $batchHeader->assetBatchDetails()->createMany($itemsToInsert);

            return $batchHeader;
        });
    }
    public function finalizeBatch($batchId)
    {
        // Update status from DRAFT to FOR REVIEW
        $batchHeader = $this->assetBatchHeader->findOrFail($batchId);
        if ($batchHeader->status !== 'DRAFT') {
            throw new \Exception('Only batches in DRAFT status can be finalized.');
        }
        $batchHeader->status = 'OPEN';
        $batchHeader->save();
        return $batchHeader;
    }
    public function batchReviewed($batchId)
    {
        $batchHeader = $this->assetBatchHeader->findOrFail($batchId);
        if ($batchHeader->reviewed_date !== null) {
            throw new \Exception('Batch has already been reviewed.');
        }
        $batchHeader->reviewed_date = now();
        $batchHeader->save();
        return $batchHeader;
    }
    public function batchApproved($batchId)
    {
        $batchHeader = $this->assetBatchHeader->findOrFail($batchId);
        if ($batchHeader->approved_date !== null) {
            throw new \Exception('Batch has already been approved.');
        }
        $batchHeader->approved_date = now();
        $batchHeader->status = 'CLOSED';
        $batchHeader->save();
        return $batchHeader;
    }
    public function batchRevised($batchId)
    {
        $batchHeader = $this->assetBatchHeader->findOrFail($batchId);
        if ($batchHeader->status !== 'OPEN') {
            throw new \Exception('Only batches in OPEN status can be revised.');
        }
        $batchHeader->status = 'DRAFT';
        $batchHeader->approved_date = null;
        $batchHeader->reviewed_date = null;
        $batchHeader->save();
        // add cardex entry
        $details = $batchHeader->assetBatchDetails;
        foreach($details as $detail){
            Cardex::create([
                'item_id' => $detail->item_id,
                'source_branch_id' => $batchHeader->branch_id,
                'reference' => $batchHeader->reference,
                'qty_out' => 1,
                'status' => 'FINAL',
                'transaction_type' => 'ADJUSTMENT',
                'price_level_id' => null,
                'batch_id' => $batchHeader->id,
                'created_at' => Carbon::now('Asia/Manila')
            ]);
        }

        return $batchHeader;
    }
}
