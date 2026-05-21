<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\PettyCashVoucherDetail;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Accounting\AccountType;
use App\Models\Accounting\TransactionTemplate;
use Illuminate\Support\Facades\DB;



class PettyCashVoucherService{

    protected $branch;
    protected $pettyCashVoucher;
    protected $pettyCashVoucherDetail;
    protected $purchaseOrder;
    protected $accountType;
    protected $transactionTemplate;






        public function __construct(
            PettyCashVoucher $pettyCashVoucher,
            PettyCashVoucherDetail $pettyCashVoucherDetail,
            Branch $branch,
            PurchaseOrder $purchaseOrder,
            AccountType $accountType,
            TransactionTemplate $transactionTemplate
            )
    {
        $this->pettyCashVoucher = $pettyCashVoucher;
        $this->pettyCashVoucherDetail = $pettyCashVoucherDetail;
        $this->branch = $branch;
        $this->purchaseOrder = $purchaseOrder;
        $this->accountType = $accountType;
        $this->transactionTemplate = $transactionTemplate;
    }

    public function create(array $data): PettyCashVoucher
    {

    return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $event_id = $this->purchaseOrder->findOrFail($data['requisition_id'])->event_id;
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
                'type_id' => $data['type_id'],

            ]);
            $pcv->pettyCashVoucherDetail()->createMany($data['items']);

        return $pcv;
    });

    }

    public function update(array $data): PettyCashVoucher
    {
        return DB::transaction(function () use ($data) {
            $itemsToInsert = [];
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $event_id = $this->purchaseOrder->findOrFail($data['requisition_id'])->event_id;
            $accountType = $this->accountType->findOrFail($data['account_types_id']);
            $transactionTitle = $this->transactionTemplate->findOrFail($data['template_id'])->templateName->template_name;
            $company_id = $branch->company_id;

            foreach($data['items'] as $item)
            {
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
                'type_id' => $data['type_id'],
                ]);

                $pcv->pettyCashVoucherDetail()->delete();
                $pcv->pettyCashVoucherDetail()->createMany($itemsToInsert);

            return $pcv;

            });
    }



}
