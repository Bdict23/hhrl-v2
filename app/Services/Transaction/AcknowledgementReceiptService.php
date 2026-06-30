<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\Acknowledgement;



class AcknowledgementReceiptService
{

    protected $acknowledgement;
    protected $branch;

    public function __construct(Acknowledgement $acknowledgement, Branch $branch)
    {
        $this->acknowledgement = $acknowledgement;
        $this->branch = $branch;
    }

    public function create(array $data): Acknowledgement
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->acknowledgement
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'AR-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $ar = $this->acknowledgement->create([
                'reference' => $reference,
                'company_id' => $data['company_id'],
                'branch_id' => $branchId,
                'customer_id' => $data['customer_id'],
                'bank_id' => $data['bank_id'],
                'account_name' => $data['account_name'],
                'check_number' => $data['check_number'],
                'check_amount' => $data['check_amount'],
                'check_date' => $data['check_date'],
                'amount_in_words' => $data['amount_in_words'],
                'check_status' => $data['check_status'],
                'created_by' => $data['created_by'],
                'event_id' => $data['event_id'],
                'status' => $data['status'],
                'notes' => $data['note'],
            ]);
            return $ar;
        });
    }

    public function update(array $data): Acknowledgement
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->acknowledgement->findOrFail($data['id']);
            $ar->update([
                'customer_id' => $data['customer_id'],
                'bank_id' => $data['bank_id'],
                'account_name' => $data['account_name'],
                'check_number' => $data['check_number'],
                'check_amount' => $data['check_amount'],
                'check_date' => $data['check_date'],
                'amount_in_words' => $data['amount_in_words'],
                'check_status' => $data['check_status'],
                'created_by' => $data['created_by'],
                'event_id' => $data['event_id'],
                'status' => $data['status'],
                'notes' => $data['note'],
            ]);
            return $ar;
        });
    }


    public static function eventCheckData(int $event, int $branch)
    {
        $data = Acknowledgement::where('event_id', $event)->where('branch_id', $branch)->first();
        return $data;
    }
}
