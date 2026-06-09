<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\PettyCashVoucherDetail;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\TransactionTemplate;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\RevolvingFund;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Models\Transaction\PcvLiquidationSnapshot;








class PettyCashVoucherService
{

    protected $branch;
    protected $pettyCashVoucher;
    protected $pettyCashVoucherDetail;
    protected $purchaseOrder;
    protected $accountType;
    protected $transactionTemplate;
    protected $revolvingFund;
    protected $advancesForLiquidation;
    protected $pcvLiquidationSnapshot;





    public function __construct(
        PettyCashVoucher $pettyCashVoucher,
        PettyCashVoucherDetail $pettyCashVoucherDetail,
        Branch $branch,
        PurchaseOrder $purchaseOrder,
        AccountType $accountType,
        TransactionTemplate $transactionTemplate,
        RevolvingFund $revolvingFund,
        AdvancesForLiquidation $advancesForLiquidation,
        PcvLiquidationSnapshot $pcvLiquidationSnapshot
    ) {
        $this->pettyCashVoucher = $pettyCashVoucher;
        $this->pettyCashVoucherDetail = $pettyCashVoucherDetail;
        $this->branch = $branch;
        $this->purchaseOrder = $purchaseOrder;
        $this->accountType = $accountType;
        $this->transactionTemplate = $transactionTemplate;
        $this->revolvingFund = $revolvingFund;
        $this->advancesForLiquidation = $advancesForLiquidation;
        $this->pcvLiquidationSnapshot = $pcvLiquidationSnapshot;
    }

    public function create(array $data): PettyCashVoucher
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            if (!$data['requisition_id']) {
                $event_id = null;
            } else {
                $event_id = $this->purchaseOrder->findOrFail($data['requisition_id'])->event_id;
            }

            $accountType = $this->accountType->findOrFail($data['account_types_id']);
            $transactionTitle = $this->transactionTemplate->findOrFail($data['template_id'])->templateName->template_name;
            $branchCode = $branch->branch_code;
            $company_id = $branch->company_id;
            $currentYear = now()->year;
            $yearlyCount = $this->pettyCashVoucher
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'PCV-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $pcv = $this->pettyCashVoucher->create([
                'branch_id' => $branchId,
                'company_id' => $company_id,
                'event_id' => $event_id,
                'reference' => $reference,
                'paid_to_employee_id' => $data['paid_to_employee_id'],
                'paid_to_customer_id' => $data['paid_to_customer_id'],
                'total_amount' => $data['total_amount'],
                'purpose' => $data['purpose'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'requisition_id' => $data['requisition_id'],
                'account_types_id' => $data['account_types_id'],
                'account_type' => $accountType->type_name,
                'template_id' => $data['template_id'],
                'transaction_title' => $transactionTitle,
                'advance_liquidation_id' => $data['afl_id'],



            ]);
            $pcvItems = [];
            foreach ($data['items'] as $item) {
                $pcvItems[] = [
                    'transaction_title_id'      => $item['transaction_title_id'],
                    'transaction_title'         => $item['transaction_title'],
                    'type'                      => $item['type'],
                    'amount'                    => $item['debit'] == 0 ? $item['credit'] : $item['debit'],
                ];
            }

            $pcv->pettyCashVoucherDetail()->createMany($pcvItems);

            // check if the fund source passed is revolving fund else AFL fund
            $balance = $data['fund_balance'] - $data['total_amount'];
            if ($data['fund_source'] == 'REVOLVING') {
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $branchId)->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    //save expense on revolving fund ledger
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'OUT',
                        'pcv_id' => $pcv->id,
                        'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                        'amount' => $data['total_amount'],
                        'balance' => $balance,
                        'description' => 'PETTY CASH VOUCHER',
                    ]);
                }
            } else {
                $afl = $this->advancesForLiquidation->findOrFail($data['afl_id']);
                $afl->advanceLiquidationSnapshot()->create([
                    'branch_id' => $branchId,
                    'type' => 'OUT',
                    'pcv_id' => $pcv->id,
                    'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                    'amount' => $data['total_amount'],
                    'balance' => $balance,
                    'description' => 'PETTY CASH VOUCHER',
                ]);
            }


            return $pcv;
        });
    }

    public function update(array $data): PettyCashVoucher
    {
        return DB::transaction(function () use ($data) {
            $itemsToInsert = [];
            if (!$data['requisition_id']) {
                $event_id = null;
            } else {
                $event_id = $this->purchaseOrder->findOrFail($data['requisition_id'])->event_id ?? null;
            }
            $accountType = $this->accountType->findOrFail($data['account_types_id']);
            $transactionTitle = $this->transactionTemplate->findOrFail($data['template_id'])->templateName->template_name;

            foreach ($data['items'] as $item) {
                $itemsToInsert[] = [
                    'transaction_title_id'      => $item['transaction_title_id'],
                    'transaction_title'         => $item['transaction_title'],
                    'type'                      => $item['type'],
                    'amount'                    => $item['debit'] == 0 ? $item['credit'] : $item['debit'],
                ];
            }
            $pcv = $this->pettyCashVoucher->findOrFail($data['petty_cash_voucher_id']);
            $pcv->update([
                'event_id' => $event_id,
                'paid_to_employee_id' => $data['paid_to_employee_id'],
                'paid_to_customer_id' => $data['paid_to_customer_id'],
                'total_amount' => $data['total_amount'],
                'purpose' => $data['purpose'],
                'status' => $data['status'],
                'created_by' => $data['created_by'],
                'requisition_id' => $data['requisition_id'],
                'account_types_id' => $data['account_types_id'],
                'account_type' => $accountType->type_name,
                'template_id' => $data['template_id'],
                'transaction_title' => $transactionTitle,
            ]);

            // Delete existing PCV details, AFL snapshots, and Revolving Fund snapshots related to this PCV before inserting updated records
            $pcv->advancesForLiquidationSnapshot()->delete();
            $pcv->revolvingFundSnapshot()->delete();
            $pcv->pettyCashVoucherDetail()->delete();
            $pcv->pettyCashVoucherDetail()->createMany($itemsToInsert);
            // check if the fund source passed is revolving fund else AFL fund
            $balance = $data['fund_balance'] - $data['total_amount'];
            if ($data['fund_source'] == 'REVOLVING') {
                $activeRevolvingFund = $this->revolvingFund->where('branch_id', $data['branch_id'])->where('status', 'OPEN')->first();
                if ($activeRevolvingFund) {
                    //save expense on revolving fund ledger
                    $activeRevolvingFund->revolvingFundSnapshot()->create([
                        'type' => 'OUT',
                        'pcv_id' => $pcv->id,
                        'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                        'amount' => $data['total_amount'],
                        'balance' => $balance,
                        'description' => 'PETTY CASH VOUCHER',
                    ]);
                }
            } else {
                $afl = $this->advancesForLiquidation->findOrFail($data['afl_id']);
                $afl->advanceLiquidationSnapshot()->create([
                    'branch_id' => $data['branch_id'],
                    'type' => 'OUT',
                    'pcv_id' => $pcv->id,
                    'status' => $data['status'] == 'OPEN' ? 'FINAL' : 'DRAFT',
                    'amount' => $data['total_amount'],
                    'balance' => $balance,
                    'description' => 'PETTY CASH VOUCHER',
                ]);
            }
            return $pcv;
        });
    }

    public function liquidate(array $data): PettyCashVoucher
    {
        return DB::transaction(function () use ($data) {
            $itemsToInsert = [];
            foreach ($data['items'] as $item) {
                $itemsToInsert[] = [
                    'pcv_id'                    => $data['pcv_id'],
                    'branch_id'                 => $data['branch_id'],
                    'purchase_date'             => $item['purchase_date'],
                    'vendor'                    => $item['vendor'],
                    'reference'                 => $item['reference'],
                    'particular'                => $item['particular'],
                    'amount'                    => (float) str_replace(",", "", $item['amount']),
                ];
            }
            // Change 'createMany' to 'insert'
            $this->pcvLiquidationSnapshot->insert($itemsToInsert);

            $pcvHeader = $this->pettyCashVoucher->findOrFail($data['pcv_id']);
            $liquidatedAmt = collect($itemsToInsert)->sum('amount');
            if ($pcvHeader->total_amount == $liquidatedAmt) {
                $pcvHeader->update([
                    'status' => 'CLOSED',
                ]);
            }
            return $pcvHeader;
        });
    }

    public static function liquidatedAmount(int $id)
    {
        $detailData = PcvLiquidationSnapshot::where('pcv_id', $id)->get();
        $expense = $detailData->sum('amount');
        return $expense;
    }
}
