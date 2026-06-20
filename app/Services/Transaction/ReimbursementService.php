<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\Reimbursement;
use App\Models\Transaction\RevolvingFund;
use Illuminate\Support\Facades\DB;
use App\Services\Transaction\RevolvingFundService;
use App\Services\Transaction\AdvancesForLiquidationService;
use App\Models\Transaction\AdvancesForLiquidation;








class ReimbursementService
{

    protected $branch;
    protected $pettyCashVoucher;
    protected $reimbursement;
    protected $revolvingFund;
    protected $advancesForLiquidation;






    public function __construct(
        PettyCashVoucher $pettyCashVoucher,
        Branch $branch,
        Reimbursement $reimbursement,
        RevolvingFund $revolvingFund,
        AdvancesForLiquidation $advancesForLiquidation
    ) {
        $this->pettyCashVoucher = $pettyCashVoucher;
        $this->branch = $branch;
        $this->reimbursement = $reimbursement;
        $this->revolvingFund = $revolvingFund;
        $this->advancesForLiquidation = $advancesForLiquidation;
    }

    public function create(array $data): Reimbursement
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->reimbursement
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'RMB-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $rmb = $this->reimbursement->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'pcv_id' => $data['pcv_id'],
                'amount' => $data['amount'],
                'prepared_by' => $data['prepared_by'],
                'approved_by' => $data['approved_by'],
                'note' => $data['note'],
                'status' => $data['status'],
            ]);

            return $rmb;
        });
    }

    public function update(array $data): Reimbursement
    {

        return DB::transaction(function () use ($data) {
            $reimbursement = $this->reimbursement->findOrFail($data['reimbursement_id']);
            $reimbursement->update([
                'prepared_by' => $data['prepared_by'],
                'approved_by' => $data['approved_by'],
                'note' => $data['note'],
                'status' => $data['status'],
            ]);

            return $reimbursement;
        });
    }


    public function reject(array $data): Reimbursement
    {
        return DB::transaction(function () use ($data) {
            $rmb = $this->reimbursement->findOrFail($data['reimbursement_id']);
            $rmb->update([
                'status' => 'REJECTED',
                'rejected_date' => $data['rejected_date'],
                'note' => $data['note'],
            ]);
            return $rmb;
        });
    }

    public function revise(array $data): Reimbursement
    {
        return DB::transaction(function () use ($data) {
            $rmb = $this->reimbursement->findOrFail($data['reimbursement_id']);
            $rmb->update([
                'status' => 'DRAFT',
            ]);
            return $rmb;
        });
    }
    public function approve(array $data): Reimbursement
    {
        return DB::transaction(function () use ($data) {
            $rmb = $this->reimbursement->findOrFail($data['reimbursement_id']);
            $rmb->update([
                'status' => 'CLOSED',
                'approved_date' => now(),
            ]);

            // UPDATE PECV TO CLOSE
            $this->pettyCashVoucher->findOrFail($data['pcv_id'])->update(['status' => 'CLOSED']);

            //add data to revolving fund if aflId is null
            if ($data['afl_id'] == null) {
                //get active revolving fund
                $revolvingFund = $this->revolvingFund->where('branch_id', $data['branch_id'])->where('status', 'OPEN')->first();
                $currentBalance = RevolvingFundService::currentBalance($data['branch_id']);

                $revolvingFund->revolvingFundSnapshot()->create([
                    'type' => 'OUT',
                    'status' => 'FINAL',
                    'amount' => $data['amount'],
                    'balance' => $currentBalance - $data['amount'],
                    'description' => 'REIMBURSEMENT',
                    'reimbursement_id' => $data['reimbursement_id'],
                ]);
            } else {
                $afl = $this->advancesForLiquidation->findOrFail($data['afl_id']);
                $currentBalance = AdvancesForLiquidationService::currentBalance($data['afl_id']);
                $afl->advanceLiquidationSnapshot()->create([
                    'type' => 'OUT',
                    'status' => 'FINAL',
                    'amount' => $data['amount'],
                    'balance' => $currentBalance - $data['amount'],
                    'description' => 'REIMBURSEMENT',
                    'reimbursement_id' => $data['reimbursement_id'],
                    'branch_id' => $data['branch_id'],
                ]);
            }
            return $rmb;
        });
    }
}
