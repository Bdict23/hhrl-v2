<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use App\Models\Inventory\FixedAsset\AssetBatchDetail;
use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Business\Branch;

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
                        'cost'        => $item['sub_total'], // Bulk cost
                        'lifespan'    => $usefulLife,
                        'span_ended'  => now()->addYears($usefulLife),
                        'condition'   => $item['condition'],
                        'qty'         => $item['quantity'],
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
}
