<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Models\Transaction\AdditionalFund;
use App\Models\Transaction\AdvancesForLiquidationSnapshot;
use App\Exceptions\InvalidAdtlAmountException;
use Illuminate\Support\Facades\Auth;





class AdvancesForLiquidationService
{

    protected $advanceLiquidation;
    protected $advanceLiquidationSnapshot;
    protected $branch;
    private $aflId;
    protected $additionalFund;

    public function __construct(AdvancesForLiquidation $advanceLiquidation, Branch $branch, AdditionalFund  $additionalFund, AdvancesForLiquidationSnapshot $snapshot)
    {
        $this->advanceLiquidation = $advanceLiquidation;
        $this->branch = $branch;
        $this->additionalFund = $additionalFund;
        $this->advanceLiquidationSnapshot = $snapshot;
    }

    public function create(array $data): AdvancesForLiquidation
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $company_id = $branch->company_id;
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->advanceLiquidation
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'AFL-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $ar = $this->advanceLiquidation->create([
                'branch_id' => $branchId,
                'company_id' => $company_id,
                'reference' => $reference,
                'status' => $data['status'],
                'prepared_by' => $data['prepared_by'],
                'received_by' => $data['received_by'],
                'date_received' => $data['date_received'],
                'approved_by' => $data['approved_by'],
                'amount_received' => $data['amount_received'],
                'notes' => $data['note'],
                'event_id' => $data['event_id'],

            ]);
            $itemsToInsert = [];
            $itemsToInsert[] = [
                'advance_liquidation_id' => $ar->id,
                'type' => 'IN',
                'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                'description' => 'ENCASHMENT',
                'amount' => $data['amount_received'],
                'branch_id' => $branchId,
                'balance' => $data['amount_received'],
            ];

            $ar->advanceLiquidationSnapshot()->createMany($itemsToInsert);
            return $ar;
        });
    }

    public function update(array $data): AdvancesForLiquidation
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->advanceLiquidation->findOrFail($data['id']);
            $ar->update([
                'status' => $data['status'],
                'prepared_by' => $data['prepared_by'],
                'received_by' => $data['received_by'],
                'date_received' => $data['date_received'],
                'approved_by' => $data['approved_by'],
                'amount_received' => $data['amount_received'],
                'notes' => $data['note'],
                'event_id' => $data['event_id'],
            ]);
            // Update the corresponding snapshot record
            $snapshot = AdvancesForLiquidationSnapshot::where('advance_liquidation_id', $ar->id)
                ->where('type', 'IN')
                ->where('description', 'ENCASHMENT')
                ->first();
            if ($snapshot) {
                $snapshot->update([
                    'amount' => $data['amount_received'],
                    'balance' => $data['amount_received'],
                    'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                ]);
            }
            return $ar;
        });
    }

    public static function currentBalance(int $id)
    {
        $detailData = AdvancesForLiquidationSnapshot::where('advance_liquidation_id', $id)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        $fund = $detailData->where('status', 'FINAL')->where('type', 'IN')->sum('amount');
        $result = (float) ($fund - $expense);
        return $result;
    }
    public static function totalExpense(int $id)
    {
        $detailData = AdvancesForLiquidationSnapshot::where('advance_liquidation_id', $id)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        return $expense;
    }

    public static function hasPendingTransaction(int $id)
    {
        $openPcv = AdvancesForLiquidationSnapshot::where('advance_liquidation_id', $id)->whereHas('pettyCashVoucher', function ($query) {
            $query->where('status', 'OPEN');
        })
            ->get()->isEmpty() ? false : true;

        $openCrs = AdvancesForLiquidationSnapshot::where('advance_liquidation_id', $id)->whereHas('cashReturn', function ($query) {
            $query->where('status', 'DRAFT')
                ->where('reference', 'like', '%CRP-%');
        })
            ->get()->isEmpty() ? false : true;

        return $openPcv || $openCrs;
    }

    public function addFund(array $data): AdditionalFund
    {
        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->advanceLiquidation
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $balance = $this->currentBalance($data['afl_id']);

            $reference = 'ADL-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $additional = $this->additionalFund->create([
                'reference'                 => $reference,
                'advances_liquidation_id'   => $data['afl_id'],
                'amount'                    => $data['amount'],
                'prepared_by'               => $data['prepared_by'],
                'remarks'                   => $data['remarks'],
            ]);
            $this->advanceLiquidationSnapshot->create([
                'advance_liquidation_id' => $data['afl_id'],
                'status' => 'FINAL',
                'description' => 'ADDITIONAL FUND',
                'type' => 'IN',
                'branch_id' => $data['branch_id'],
                'amount' => $data['amount'],
                'adtl_fund_id' => $additional->id,
                'balance' => $balance + $data['amount'],
            ]);
            return $additional;
        });
    }
}
