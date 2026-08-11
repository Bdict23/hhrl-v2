<?php

namespace App\Services\Event;

use App\Models\BanquetEvent\Event;
use App\Models\BanquetEvent\BanquetProcurement;
use App\Models\Business\Branch;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Exceptions\ClosedEventRollbackException;


class BanquetProcurementService extends Exception
{

    protected $procurement;
    protected $event;
    protected $branch;
    protected $message = 'Cannot rollback budget for an event that is already closed.';
    protected $code = 422;

    public function __construct(BanquetProcurement $procurement, Event $event, Branch $branch,)
    {
        $this->procurement = $procurement;
        $this->event = $event;
        $this->branch = $branch;
    }

    public function createEventBudget(array $data): BanquetProcurement
    {
        return  DB::transaction(function () use ($data) {
            $currentYear = now()->year;
            $branchId = $data['branch_id'];
            $branchCode = $this->branch->find($branchId)->branch_code;
            $yearlyCount = $this->procurement->where('branch_id', $branchId)
                ->whereYear('created_at', $currentYear)
                ->count() + 1;
            $reference = 'BEB-' . $branchCode . '-' . now()->format('my') . '-' . str_pad($yearlyCount, 2, '0', STR_PAD_LEFT);

            $liquidate = $this->procurement->create([
                'reference_number'  => $reference,
                'branch_id'         => $branchId,
                'created_by'        => $data['prepared_by'],
                'event_id'          => $data['event_id'],
                'status'            => $data['status'] == 'FINAL' ? 'PENDING' : 'PREPARING',
                'notes'              => $data['notes'],
                'suggested_amount'  => $data['suggested_amount'],
                'noted_by'          => $data['reviewed_by'],
                'approved_by'       => $data['approved_by'],
                'services_included' => $data['services_included'],
            ]);

            return $liquidate;
        });
    }
    public function updateEventBudget(array $data): BanquetProcurement
    {
        return  DB::transaction(function () use ($data) {
            $budget =  $this->procurement->findOrFail($data['id']);
            $budget->update([
                'created_by'        => $data['prepared_by'],
                'status'            => $data['status'] == 'FINAL' ? 'PENDING' : 'PREPARING',
                'notes'              => $data['notes'],
                'suggested_amount'  => $data['suggested_amount'],
                'noted_by'          => $data['reviewed_by'],
                'approved_by'       => $data['approved_by'],
                'services_included' => $data['services_included'],
            ]);

            return $budget;
        });
    }

    public function validateAction(array $data): BanquetProcurement
    {
        return  DB::transaction(function () use ($data) {
            $budget =  $this->procurement->findOrFail($data['id']);
            $status = '';
            if ($data['status'] == 'APPROVED') {
                $status = 'APPROVED';
            } elseif ($data['status'] == 'REJECT') {
                $status = 'REJECTED';
            } else {
                $status = 'PREPARING';
            }
            $budget->update([
                'status'            => $status,
            ]);

            return $budget;
        });
    }

    public function viewBudget(int $budgetId): BanquetProcurement
    {
        return $this->procurement->findOrFail($budgetId);
    }
    public function applyRollback(int $budgetId): BanquetProcurement
    {
        return DB::transaction(function () use ($budgetId) {
            // Eager load event to prevent N+1 and lock for update if concurrent edits happen
            $budget = BanquetProcurement::with('event')
                ->lockForUpdate()
                ->findOrFail($budgetId);

            if ($budget->event && $budget->event->status === 'CLOSED') {
                throw new ClosedEventRollbackException();
            }
            $budget->update(['status' => 'PREPARING']);
            return $budget;
        });
    }
}
