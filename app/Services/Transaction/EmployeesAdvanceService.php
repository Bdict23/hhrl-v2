<?php

namespace App\Services\Transaction;

use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction\EmployeeAdvance;
use App\Models\Transaction\EmployeeAdvanceSnapshot;
use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\CashReturn;







class EmployeesAdvanceService
{

    protected $employeeAdvance;
    protected $branch;

    public function __construct(EmployeeAdvance $employeeAdvance, Branch $branch)
    {
        $this->employeeAdvance = $employeeAdvance;
        $this->branch = $branch;
    }

    public function create(array $data): EmployeeAdvance
    {

        return DB::transaction(function () use ($data) {
            $branchId = $data['branch_id'];
            $branch = $this->branch->findOrFail($branchId);
            $branchCode = $branch->branch_code;
            $currentYear = now()->year;
            $yearlyCount = $this->employeeAdvance
                ->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;

            $reference = 'ECA-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);
            $ar = $this->employeeAdvance->create([
                'branch_id' => $branchId,
                'reference' => $reference,
                'prepared_by' => $data['prepared_by'],
                'received_by' => $data['received_by'],
                'approved_by' => $data['approved_by'],
                'status' => $data['status'],
                'amount' => $data['amount_received'],
                'remarks' => $data['note'],
            ]);
            return $ar;
        });
    }

    public function update(array $data): EmployeeAdvance
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->employeeAdvance->findOrFail($data['id']);
            $ar->update([
                'status' => $data['status'],
                'prepared_by' => $data['prepared_by'],
                'received_by' => $data['received_by'],
                'approved_by' => $data['approved_by'],
                'amount' => $data['amount_received'],
                'remarks' => $data['note'],
            ]);
            return $ar;
        });
    }

    public function approve(array $data): EmployeeAdvance
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->employeeAdvance->findOrFail($data['id']);
            $ar->update([
                'status' => 'FOR DISBURSEMENT',
                'approved_at' => now(),
            ]);
            return $ar;
        });
    }

    public function revise(array $data): EmployeeAdvance
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->employeeAdvance->findOrFail($data['id']);
            $ar->update([
                'status' => 'DRAFT',
            ]);
            return $ar;
        });
    }

    public function reject(array $data): EmployeeAdvance
    {
        return DB::transaction(function () use ($data) {
            $ar = $this->employeeAdvance->findOrFail($data['id']);
            $ar->update([
                'status' => 'REJECTED',
                'rejected_at' => now(),
            ]);
            return $ar;
        });
    }


    public static function currentBalance(int $id)
    {
        $detailData = EmployeeAdvanceSnapshot::where('advance_id', $id)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        $fund = $detailData->where('status', 'FINAL')->where('type', 'IN')->sum('amount');
        $result = (float) ($fund - $expense);
        return $result;
    }
    public static function totalExpense(int $id)
    {
        $detailData = EmployeeAdvanceSnapshot::where('advance_liquidation_id', $id)->get();
        $expense = $detailData->where('status', 'FINAL')->where('type', 'OUT')->sum('amount');
        return $expense;
    }

    public static function hasPendingTransaction(int $id)
    {
        $openPcv = EmployeeAdvanceSnapshot::where('advance_liquidation_id', $id)->whereHas('pettyCashVoucher', function ($query) {
            $query->where('status', 'OPEN');
        })
            ->get()->isEmpty() ? false : true;

        $openCrs = EmployeeAdvanceSnapshot::where('advance_liquidation_id', $id)->whereHas('cashReturn', function ($query) {
            $query->where('status', 'DRAFT');
        })
            ->get()->isEmpty() ? false : true;

        return $openPcv || $openCrs;
    }


    //used by cash return on crs for employee cash advance
    public static function hasPendingCashReturn(int $id): ?int
    {
        return CashReturn::where('employee_advance_id', $id)
            ->where('status', 'DRAFT')
            ->value('id');
    }
}
