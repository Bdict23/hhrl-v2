<?php

namespace App\Services\Inventory;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\Withdrawal;
use App\Models\BanquetEvent\Event;
use App\Models\Inventory\Cardex;


class ItemWithdrawalService
{

    protected $withdrawal;
    protected $event;
    protected $branch;

    public function __construct(Withdrawal $withdrawal, Event $event, Branch $branch)
    {
        $this->withdrawal = $withdrawal;
        $this->event = $event;
        $this->branch = $branch;
    }


    public static function getEventwithdrawals(int $event, int $branch)
    {
        $withdrawals = Withdrawal::where('event_id', $event)->where('source_branch_id', $branch)->get();
        return $withdrawals;
    }
    public static function getEventWithdrawalTotal(int $event, int $branch)
    {
        $total = 0;
        $withdrawalIds = Withdrawal::where('event_id', $event)->where('source_branch_id', $branch)->get()->pluck('id');
        $cardex = Cardex::whereIn('withdrawal_id', $withdrawalIds)->get();
        foreach ($cardex as $item) {
            $total += $item->cost->amount * ($item->qty == 0 ? $item->qty_out : $item->qty);
        }
        return $total;
    }

    public function createWithdrawal(array $data): withdrawal
    {
        return DB::transaction(function () use ($data) {
            $itemsToInsert = [];


            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->withdrawal->where('source_branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'IW-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            // 1. Create the withdrawal record
            $withdrawal = $this->withdrawal->create([
                'event_id'          => $data['event_id'],
                'usage_date'        => $data['effectiveDate'],
                'useful_date'       => $data['restockDate'],
                'source_branch_id'  => $data['branch_id'],
                'department_id'     => $data['department_id'],
                'prepared_by'       => $data['preparedBy'],
                'reviewed_by'       => $data['reviewedBy'],
                'approved_by'       => $data['approvedBy'],
                'remarks'           => $data['notes'],
                'type_id'           => $data['type_id'],
                'production_id'     => $data['production_id'],
                'withdrawal_status' => $data['status'] == 'FINAL' ? 'FOR REVIEW' : 'PREPARING',
                'reference_number'         => $reference,
                'final_date'         => $data['status'] == 'FINAL' ? now() : null,
            ]);

            // 2. Attach items to the withdrawal

            foreach ($data['items'] as $item) {
                $itemsToInsert[] = [
                    'item_id'               => $item['id'],
                    'qty'                   => $item['quantity'],
                    'qty_out'               => $item['quantity'],
                    'price_level_id'        => $item['price_id'],
                    'type'                  => 'OUT',
                    'source_branch_id'      => $branchId,
                    'transaction_type'      => 'WITHDRAWAL',
                    'status'                => $data['status'] == 'FINAL' ? 'FINAL' : 'TEMP',
                    'reference'             => $reference,
                ];
            }
            $withdrawal->cardex()->createMany($itemsToInsert);

            return $withdrawal;
        });
    }

    public function updateWithdrawal(array $data): Withdrawal
    {
        return DB::transaction(function () use ($data) {
            $withdrawal = $this->withdrawal->findOrFail($data['withdrawal_id']);
            // Update the withdrawal record
            $withdrawal->update([
                'event_id'          => $data['event_id'],
                'usage_date'        => $data['effectiveDate'],
                'useful_date'       => $data['restockDate'],
                'source_branch_id'  => $data['branch_id'],
                'department_id'     => $data['department_id'],
                'prepared_by'       => $data['preparedBy'],
                'reviewed_by'       => $data['reviewedBy'],
                'approved_by'       => $data['approvedBy'],
                'remarks'           => $data['notes'],
                'type_id'           => $data['type_id'],
                'production_id'     => $data['production_id'],
                'withdrawal_status' => $data['status'] == 'FINAL' ? 'FOR REVIEW' : 'PREPARING',
                'final_date'         => $data['status'] == 'FINAL' ? now() : null,
            ]);

            // Delete existing cardex items
            $withdrawal->cardex()->delete();

            // Attach new items to the withdrawal
            foreach ($data['items'] as $item) {
                $withdrawal->cardex()->create([
                    'item_id'               => $item['id'],
                    'qty'                   => $item['quantity'],
                    'qty_out'               => $item['quantity'],
                    'price_level_id'        => $item['price_id'],
                    'type'                  => 'OUT',
                    'source_branch_id'      => $data['branch_id'],
                    'transaction_type'      => 'WITHDRAWAL',
                    'status'                => $data['status'] == 'FINAL' ? 'FINAL' : 'TEMP',
                    'reference'             => $withdrawal->reference_number,
                ]);
            }

            return $withdrawal;
        });
    }

    public function reviewAction(array $data): Withdrawal
    {
        return DB::transaction(function () use ($data) {
            $withdrawal = $this->withdrawal->findOrFail($data['withdrawal_id']);
            // Update the withdrawal status
            $withdrawal->update([
                'withdrawal_status' => $data['status'] == 'REVIEWED' ? 'FOR APPROVAL' : 'PREPARING',
                'reviewed_date'     => now(),
            ]);

            return $withdrawal;
        });
    }


    public function approveAction(array $data): Withdrawal
    {
        return DB::transaction(function () use ($data) {
            $withdrawal = $this->withdrawal->findOrFail($data['withdrawal_id']);
            // Update the withdrawal status
            $withdrawal->update([
                'withdrawal_status' => $data['status'] == 'APPROVED' ? 'APPROVED' : 'PREPARING',
                'reviewed_date'     => now(),
            ]);

            // Update the status of associated cardex items
            $withdrawal->cardex()->update([
                'status' => $data['status'] == 'APPROVED' ? 'FINAL' : 'TEMP',
            ]);

            return $withdrawal;
        });
    }
}
