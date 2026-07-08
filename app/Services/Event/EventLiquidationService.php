<?php

namespace App\Services\Event;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\EventLiquidation;
use App\Models\Transaction\Reimbursement;
use App\Models\Transaction\CashReturn;


class EventLiquidationService
{

    protected $liquidation;
    protected $event;
    protected $branch;
    protected $cashReturn;
    protected $reimbursement;

    public function __construct(
        EventLiquidation $liquidation,
        Event $event,
        Branch $branch,
        CashReturn $cashReturn,
        Reimbursement $reimbursement

    ) {
        $this->liquidation = $liquidation;
        $this->event = $event;
        $this->branch = $branch;
        $this->cashReturn = $cashReturn;
        $this->reimbursement = $reimbursement;
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
                'status'            =>   $data['status'] == 'FINAL' ? 'FOR REVIEW' : 'DRAFT',
                'note'              => $data['notes'],
                'total_incurred'    => $data['total_incurred'],
                'reviewed_by'       => $data['reviewed_by'],
                'approved_by'       => $data['approved_by'],
            ]);

            return $liquidate;
        });
    }

    public function updateLiquidation(array $data): EventLiquidation
    {
        return  DB::transaction(function () use ($data) {
            $liquidation = $this->liquidation->findOrFail($data['liquidation_id']);
            $liquidation->update([
                'created_by'        => $data['prepared_by'],
                'updated_by'        => $data['prepared_by'],
                'status'            => $data['status'] == 'FINAL' ? 'FOR REVIEW' : 'DRAFT',
                'updated_at'        => now(),
                'note'              => $data['notes'],
                'total_incurred'    => $data['total_incurred'],
                'reviewed_by'       => $data['reviewed_by'],
                'approved_by'       => $data['approved_by'],
            ]);

            return $liquidation;
        });
    }


    public function reviewed(array $data): EventLiquidation
    {
        return  DB::transaction(function () use ($data) {
            $liquidation = $this->liquidation->findOrFail($data['liquidation_id']);
            $liquidation->update([
                'status'            => $data['status'] == 'REVISE' ? 'DRAFT' : 'FOR SETTLEMENT',
                'reviewed_date' => $data['status'] == 'FOR APPROVAL' ? now() : null,
            ]);

            return $liquidation;
        });
    }

    public function approval(array $data): EventLiquidation
    {
        return  DB::transaction(function () use ($data) {
            $liquidation = $this->liquidation->findOrFail($data['liquidation_id']);
            $liquidation->update([
                'status'            => $data['status'] == 'REVISED' ? 'DRAFT' : 'CLOSED',
                'reviewed_date' => $data['status'] == 'FOR APPROVAL' ? now() : null,
            ]);

            //update the event status to CLOSED if liquidation is approved
            if ($data['status'] == 'APPROVED') {
                $event = $this->event->findOrFail($liquidation->event_id);
                $event->update([
                    'status' => 'CLOSED',
                    'liquidation_status' => 'LIQUIDATED',
                ]);

                // Close the acknowledgment
                $acknowledgment = $event->acknowledgment;
                if ($acknowledgment) {
                    $acknowledgment->update([
                        'status' => 'CLOSED',
                    ]);
                }
            }
            return $liquidation;
        });
    }
}
