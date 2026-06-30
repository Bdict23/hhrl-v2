<?php

namespace App\Services\Event;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\EventLiquidation;
use App\Models\Inventory\Cardex;


class EventLiquidationService
{

    protected $liquidation;
    protected $event;
    protected $branch;

    public function __construct(
        EventLiquidation $liquidation,
        Event $event,
        Branch $branch,

    ) {
        $this->liquidation = $liquidation;
        $this->event = $event;
        $this->branch = $branch;
    }

    public function createLiquidation(array $data): EventLiquidation
    {
        return  DB::transaction(function () use ($data) {
            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->liquidation->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'ELQ-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            $liquidate = $this->liquidation->create([
                'reference'         => $reference,
                'branch_id'         => $branchId,
                'created_by'        => $data['prepared_by'],
                'event_id'          => $data['event_id'],
                'status'          =>   $data['status'] == 'FINAL' ? 'FOR REVIEW' : 'DRAFT',
                'note'              => $data['notes'],
                'total_incurred'    => $data['total_incurred'],
                'reviewed_by'       => $data['reviewed_by'],
                'approved_by'       => $data['approved_by'],
            ]);

            return $liquidate;
        });
    }
}
