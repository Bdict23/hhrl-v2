<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\Reimbursement;
use Illuminate\Support\Facades\DB;








class ReimbursementService{

    protected $branch;
    protected $pettyCashVoucher;
    protected $reimbursement;


        public function __construct(
            PettyCashVoucher $pettyCashVoucher,
            Branch $branch,
            Reimbursement $reimbursement
            )
    {
        $this->pettyCashVoucher = $pettyCashVoucher;
        $this->branch = $branch;
        $this->reimbursement = $reimbursement;
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


}
