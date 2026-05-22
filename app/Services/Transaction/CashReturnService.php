<?php

namespace App\Services\Transaction;

use App\Models\Transaction\CashReturn;
use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;



class CashReturnService{

    protected $cashReturn;
    protected $branch;

        public function __construct( CashReturn $cashReturn, Branch $branch)
    {
        $this->cashReturn = $cashReturn;
        $this->branch = $branch;
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

            $reference = 'PCR-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $pcr = $this->cashReturn->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'status' => $data['status'],
                'pcv_id' => $data['pcv_id'],
                'prepared_by' => $data['prepared_by'],
                'amount_returned' => $data['amount_returned'],
                'notes' => $data['notes'],
            ]);
            $pcr->cashReturnDetail()->createMany($data['items']);


        return $pcr;
    });

    }

}
