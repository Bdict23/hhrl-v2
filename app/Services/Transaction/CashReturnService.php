<?php

namespace App\Services\Transaction;

use App\Models\Transaction\CashReturn;
use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Models\Transaction\RevolvingFund;
use App\Services\Transaction\AdvancesForLiquidationService;
use App\Services\Transaction\RevolvingFundService;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\EmployeeAdvance;
use App\Models\BanquetEvent\EventLiquidation;








class CashReturnService
{

    protected $cashReturn;
    protected $branch;
    protected $advancesForLiquidation;
    protected $revolvingFund;
    protected $pettyCashVoucher;
    protected $employeeAdvance;
    protected $eventLiquidation;




    public function __construct(
        CashReturn $cashReturn,
        Branch $branch,
        AdvancesForLiquidation $advancesForLiquidation,
        RevolvingFund $revolvingFund,
        PettyCashVoucher $pettyCashVoucher,
        EmployeeAdvance $employeeAdvance,
        EventLiquidation $eventLiquidation

    ) {
        $this->cashReturn = $cashReturn;
        $this->branch = $branch;
        $this->advancesForLiquidation = $advancesForLiquidation;
        $this->revolvingFund = $revolvingFund;
        $this->pettyCashVoucher = $pettyCashVoucher;
        $this->employeeAdvance = $employeeAdvance;
        $this->eventLiquidation = $eventLiquidation;
    }

    public function createPcvCrs(array $data): CashReturn
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->cashReturn
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'CRP-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $pcr = $this->cashReturn->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'status' => $data['status'],
                'pcv_id' => $data['pcv_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);
            if ($data['advances_liquidation_id'] != null) {
                $aflCurrentBalance = AdvancesForLiquidationService::currentBalance($data['advances_liquidation_id']);

                $afl = $this->advancesForLiquidation->findOrFail($data['advances_liquidation_id']);
                $afl->advanceLiquidationSnapshot()->create([
                    'advance_liquidation_id' => $data['advances_liquidation_id'],
                    'branch_id' => $branchId,
                    'type' => 'IN',
                    'status' => $data['status'],
                    'amount' => $data['amount_returned'],
                    'balance' => $data['amount_returned'] + $aflCurrentBalance,
                    'description' => 'CASH RETURN - PCV',
                    'cash_return_id' => $pcr->id,
                ]);
            } else {
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $branchId)->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    $currentRevolvingFundBalance = RevolvingFundService::currentBalance($branchId);
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'IN',
                        'status' => $data['status'],
                        'amount' => $data['amount_returned'],
                        'balance' => $data['amount_returned'] + $currentRevolvingFundBalance,
                        'description' => 'CASH RETURN - PCV',
                        'cash_return_id' => $pcr->id,
                    ]);
                }
            }
            // update pcv to close if full amount is returned
            $pcv = $this->pettyCashVoucher->findOrFail($data['pcv_id']);
            $totalReturnedAmount = $pcv->liquidationData()->sum('amount') + $this->cashReturn->where('pcv_id', $pcr->pcv_id)->where('status', 'FINAL')->sum('amount_returned') ?? 0;
            if ($totalReturnedAmount >= $pcv->total_amount) {
                $pcv->update(['status' => 'CLOSED']);
            }




            return $pcr;
        });
    }

    public function createEventCrs(array $data): CashReturn
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->cashReturn
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'CRE-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $pcr = $this->cashReturn->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'status' => $data['status'],
                'event_id' => $data['event_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);

            // update pcv to close if full amount is returned
            $liquidation = $this->eventLiquidation->findOrFail($data['liquidation_id']);
            if ($data['status'] == 'FINAL') {
                $liquidation->update(['status' => 'FOR APPROVAL']);
            }




            return $pcr;
        });
    }
    public function update(array $data): CashReturn
    {
        return DB::transaction(function () use ($data) {
            $pcr = $this->cashReturn->findOrFail($data['id']);
            $pcr->update([
                'status' => $data['status'],
                'pcv_id' => $data['pcv_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);

            // delete the old snapshot record
            $pcr->advancesLiquidationSnapshot()->delete();
            $pcr->revolvingFundSnapshot()->delete();

            if ($data['advances_liquidation_id'] != null) {
                // create a new snapshot record with the updated amount
                $aflCurrentBalance = AdvancesForLiquidationService::currentBalance($data['advances_liquidation_id']);
                $afl = $this->advancesForLiquidation->findOrFail($data['advances_liquidation_id']);
                $afl->advanceLiquidationSnapshot()->create([
                    'advance_liquidation_id' => $data['advances_liquidation_id'],
                    'branch_id' => $data['branch_id'],
                    'type' => 'IN',
                    'status' => $data['status'],
                    'amount' => $data['amount_returned'],
                    'balance' => $data['amount_returned'] + $aflCurrentBalance,
                    'description' => 'CASH RETURN - PCV',
                    'cash_return_id' => $pcr->id,
                ]);
            } else {
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $data['branch_id'])->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    $currentRevolvingFundBalance = RevolvingFundService::currentBalance($data['branch_id']);
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'IN',
                        'status' => $data['status'],
                        'amount' => $data['amount_returned'],
                        'balance' => $data['amount_returned'] + $currentRevolvingFundBalance,
                        'description' => 'CASH RETURN - PCV',
                        'cash_return_id' => $pcr->id,
                    ]);
                }
            }
            // update pcv to close if full amount is returned
            $pcv = $this->pettyCashVoucher->findOrFail($pcr->pcv_id);
            $totalReturnedAmount = $pcv->liquidationData()->sum('amount') + $this->cashReturn->where('pcv_id', $pcr->pcv_id)->where('status', 'FINAL')->sum('amount_returned') ?? 0;
            if ($totalReturnedAmount >= $pcv->total_amount) {
                $pcv->update(['status' => 'CLOSED']);
            }
            return $pcr;
        });
    }
    public function createAflCrs(array $data): CashReturn
    {
        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->cashReturn
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'CRL-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $pcr = $this->cashReturn->create([
                'branch_id'                 => $branchId,
                'reference'                 => $reference,
                'status'                    => $data['status'],
                'advances_liquidation_id'   => $data['advances_liquidation_id'],
                'prepared_by'               => $data['prepared_by'],
                'amount_returned'           => $data['amount_returned'],
                'notes'                     => $data['notes'],
            ]);

            $aflCurrentBalance = round(AdvancesForLiquidationService::currentBalance($data['advances_liquidation_id']), 2);
            $afl = $this->advancesForLiquidation->findOrFail($data['advances_liquidation_id']);
            $afl->advanceLiquidationSnapshot()->create([
                'advance_liquidation_id' => $data['advances_liquidation_id'],
                'branch_id' => $branchId,
                'type' => 'OUT',
                'status' => $data['status'],
                'amount' => $data['amount_returned'],
                'balance' =>  $aflCurrentBalance - $data['amount_returned'],
                'description' => 'CASH RETURN - EXCESS',
                'cash_return_id' => $pcr->id,
            ]);
            // update afl to close if full amount is returned and has no pending transaction
            if ($data['status'] == 'FINAL' && $data['has_pending_transaction'] == false && $aflCurrentBalance ==  $data['amount_returned']) {
                $afl->update(['status' => 'CLOSED']);
            }

            return $pcr;
        });
    }
    public function updateAflCrs(array $data): CashReturn
    {
        return DB::transaction(function () use ($data) {
            $crs = $this->cashReturn->findOrFail($data['id']);
            $crs->update([
                'status' => $data['status'],
                'advances_liquidation_id' => $data['advances_liquidation_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);

            // delete the old snapshot record
            $crs->advancesLiquidationSnapshot()->delete();
            $branchId = $data['branch_id'];
            $aflCurrentBalance = round(AdvancesForLiquidationService::currentBalance($data['advances_liquidation_id']), 2);
            $afl = $this->advancesForLiquidation->findOrFail($data['advances_liquidation_id']);
            $afl->advanceLiquidationSnapshot()->create([
                'advance_liquidation_id' => $data['advances_liquidation_id'],
                'branch_id' => $branchId,
                'type' => 'OUT',
                'status' => $data['status'],
                'amount' => $data['amount_returned'],
                'balance' =>  $aflCurrentBalance - $data['amount_returned'],
                'description' => 'CASH RETURN - EXCESS',
                'cash_return_id' => $crs->id,
            ]);

            // update afl to close if full amount is returned and has no pending transaction
            if ($data['status'] == 'FINAL' && $data['has_pending_transaction'] == false && $aflCurrentBalance ==  $data['amount_returned']) {
                $afl->update(['status' => 'CLOSED']);
            }

            return $crs;
        });
    }

    public function createEmployeeAdvanceCrs(array $data): CashReturn
    {
        return DB::transaction(function () use ($data) {
            $balance = round(EmployeesAdvanceService::currentBalance($data['employee_advance_id']), 2);
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->cashReturn
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'CRA-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $cra = $this->cashReturn->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'status' => $data['status'],
                'employee_advance_id' => $data['employee_advance_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);

            $employeeAdvance = $this->employeeAdvance->find($data['employee_advance_id']);
            if ($employeeAdvance) {
                $isOpen = ($data['status'] === 'FINAL');

                // 2. Create the snapshot
                $employeeAdvance->employeeAdvanceSnapshot()->create([
                    'type'              => 'OUT',
                    'status'            => $isOpen ? 'FINAL' : 'DRAFT',
                    'description'       => 'CASH RETURN',
                    'amount'            => $data['amount_returned'],
                    'balance'           => $balance - $data['amount_returned'],
                    'cash_return_id'    => $cra->id,
                ]);

                // 3. Update the advance status if open
                if ($isOpen && $balance - $data['amount_returned'] == 0) {
                    $employeeAdvance->update(['status' => 'CLOSED', 'closed_at' => now()]);
                }

                // 4. insert revolving ledger
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $branchId)->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    $currentRevolvingFundBalance = RevolvingFundService::currentBalance($branchId);
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'IN',
                        'status' => $data['status'],
                        'amount' => $data['amount_returned'],
                        'balance' => $data['amount_returned'] + $currentRevolvingFundBalance,
                        'description' => 'CASH RETURN - CA',
                        'cash_return_id' => $cra->id,
                    ]);
                }
            }
            return $cra;
        });
    }


    public function updateEmployeeAdvanceCrs(array $data): CashReturn
    {
        return DB::transaction(function () use ($data) {
            $balance = round(EmployeesAdvanceService::currentBalance($data['employee_advance_id']), 2);
            $branchId = $data['branch_id'];
            $cra = $this->cashReturn->findOrFail($data['cash_return_id']);
            $cra->update([
                'status' => $data['status'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);

            $employeeAdvance = $this->employeeAdvance->find($data['employee_advance_id']);
            if ($employeeAdvance) {
                $isOpen = ($data['status'] === 'FINAL');
                // delete the old snapshot record
                $employeeAdvance->employeeAdvanceSnapshot()->where('cash_return_id', $cra->id)->delete();

                // 2. Create the snapshot
                $employeeAdvance->employeeAdvanceSnapshot()->create([
                    'type'              => 'OUT',
                    'status'            => $isOpen ? 'FINAL' : 'DRAFT',
                    'description'       => 'CASH RETURN',
                    'amount'            => $data['amount_returned'],
                    'balance'           => $balance - $data['amount_returned'],
                    'cash_return_id'    => $cra->id,
                ]);

                // 3. Update the advance status if open
                if ($isOpen && $balance - $data['amount_returned'] == 0) {
                    $employeeAdvance->update(['status' => 'CLOSED', 'closed_at' => now()]);
                }

                // 4. insert revolving ledger
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $branchId)->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    $currentRevolvingFundBalance = RevolvingFundService::currentBalance($branchId);

                    //delete the old snapshot
                    $activeRevolvingFund->revolvingFundSnapshot()->where('cash_return_id', $cra->id)->delete();

                    // create new snapshot
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'IN',
                        'status' => $data['status'],
                        'amount' => $data['amount_returned'],
                        'balance' => $data['amount_returned'] + $currentRevolvingFundBalance,
                        'description' => 'CASH RETURN - CA',
                        'cash_return_id' => $cra->id,
                    ]);
                }
            }
            return $cra;
        });
    }
}
