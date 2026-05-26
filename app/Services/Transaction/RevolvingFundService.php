<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\RevolvingFund;
use App\Models\Transaction\RevolvingFundDetail;




class RevolvingFundService{

    protected $branch;
    protected $revolvingFund;
    protected $revolvingFundDetail;

        public function __construct( Branch $branch, RevolvingFund $revolvingFund, RevolvingFundDetail $detail)
    {
        $this->branch = $branch;
        $this->revolvingFund = $revolvingFund;
        $this->revolvingFundDetail = $detail;
    }

    public function createBatch(array $data): RevolvingFund
    {

    return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $ceilingAmount = $branch->ceilingAmount->name;
            $replenishedAmount = $data['replenished_amount'];
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->revolvingFund
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'REV-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $rev = $this->revolvingFund->create([
                'reference' => $reference,
                'branch_id' => $branchId,
                'replenished_amount' => $replenishedAmount,
                'status' => 'OPEN',
                'prepared_by' => $data['prepared_by'],
                'ceiling_amount' => $ceilingAmount,
                'starting_balance' =>  $data['balance'],
                'amount' => $data['replenished_amount'] + $data['balance'],

            ]);
            $itemsToInsert = [];
            foreach ($data['items'] as $item) {
                $itemsToInsert[] = [
                    'forwarded_revolving_fund_id'       => $item['forwarded_revolving_fund_id'],
                    'type'                              => 'IN',
                    'acknowledgement_id'                => $item['acknowledgement_id'],
                    'description'                       => $item['description'],
                    'amount'                            => $item['amount'],
                    'prepared_by'                       => $data['prepared_by'],
                    'balance'                           => $item['balance'],
                    'status'                            => 'FINAL',


                ];
            }

            // UPDATE THE PREV. REVOLVING FUND TO CLOSE
            if($data['current_revolving_fund_id'] != null){
                 $this->revolvingFund->findorFail($data['current_revolving_fund_id'])->update([
                'status' => 'CLOSED',
                'ending_balance' => $data['balance'],
                'closed_at' => now()
            ]);
            }



            $rev->revolvingFundDetail()->createMany($itemsToInsert);

        return $rev;
    });

    }
    public static function currentBalance(string $branchId)
    {
        $fundId = RevolvingFund::with('revolvingFundDetail')->where('branch_id',$branchId)->where('status', 'OPEN')->first()->id ?? null;
        $detailData = RevolvingFundDetail::where('revolving_fund_id', $fundId)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        $fund = $detailData->where('status', 'FINAL')->where('type', 'IN')->sum('amount');
        $result = (float) ($fund - $expense);
        return $result;
    }

    public static function expensedAmount(string $branchId)
    {
        $fundId = RevolvingFund::with('revolvingFundDetail')->where('branch_id',$branchId)->where('status', 'OPEN')->first()->id ?? null;
        $detailData = RevolvingFundDetail::where('revolving_fund_id', $fundId)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        return $expense;

    }

}
