<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Transaction\EmployeeAdvance;
use App\Services\Transaction\AdvancesForLiquidationService;
use Illuminate\Support\Facades\Auth;
use App\Services\Transaction\EmployeesAdvanceService;
use TallStackUi\Traits\Interactions;


new class extends Component {
    use WithPagination;
    use Interactions;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public function with(): array
    {
        return [
            'headers' => [
                ['index' => 'status', 'label' => 'Status'], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'amount', 'label' => 'Received', 'sortable' => false], 
                ['index' => 'balance', 'label' => 'balance', 'sortable' => false], 
                ['index' => 'received_by', 'label' => 'Received By', 'sortable' => false], 
                ['index' => 'prepared_by', 'label' => 'Prepared By', 'sortable' => false], 
                ['index' => 'created_at', 'label' => 'Date'], 
                ['index' => 'action', 'label' => 'Action', 'sortable' => false]],
            'rows' => EmployeeAdvance::query()
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%");
                })
                ->where('approved_by', Auth::user()->emp_id)
                ->where('status', '=', 'FOR APPROVAL')
                ->where('branch_id', Auth::user()->branch_id)
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }

     public function approve($id)
    {
        $cashAdvance = EmployeeAdvance::find($id);
        if($cashAdvance)
        {
            $data = [
            'id' => $id,
        ];
            $cashAdvanceService = app(EmployeesAdvanceService::class);
            $cashAdvanceService->approve($data);
            $this->toast()->success('success', "Employee cash advance status {$cashAdvance->reference} updated successfully!")->send();
        }else{
            $this->toast()->success('error', "Something went wrong, can't approve this reimbursement!")->send();
        }
    }
    public function revise($id)
    {
        $cashAdvance = EmployeeAdvance::find($id);
        if($cashAdvance)
        {
            $data = [
            'id' => $id,
        ];
            $cashAdvanceService = app(EmployeesAdvanceService::class);
            $cashAdvanceService->revise($data);
            $this->toast()->success('success', "Employee cash advance status {$cashAdvance->reference} updated successfully!")->send();
        }else{
            $this->toast()->success('error', "Something went wrong, can't approve this reimbursement!")->send();
        }
    }
    public function reject($id)
    {
        $cashAdvance = EmployeeAdvance::find($id);
        if($cashAdvance)
        {
            $data = [
            'id' => $id,
        ];
            $cashAdvanceService = app(EmployeesAdvanceService::class);
            $cashAdvanceService->reject($data);
            $this->toast()->success('success', "Employee cash advance status {$cashAdvance->reference} updated successfully!")->send();
        }else{
            $this->toast()->error('error', "Something went wrong, can't approve this reimbursement!")->send();
        }
    }
};
?>

<div>
    <x-ts-table :$headers :$rows :$sort paginate loading striped filter>
        <x-slot:header>
            <div class="lg:flex lg:justify-between mb-3 grid">
                <div class="w-auto mb-3">
                    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                        ['label' => 'Transaction', 'link' => route('cash-advances.validation.approval-summary'), 'icon' => 'cog'],
                        ['label' => 'Employee cash advances approval Summary', 'icon' => 'list-bullet'],
                    ]" class="mb-3" />
                </div>
            </div>
        </x-slot:header>
        @interact('column_status', $row)
            <div class="flex items-center gap-2">
                @if ($row->status == 'DRAFT')
                    <x-ts-badge text="DRAFT" color="secondary" />
                @elseif($row->status == 'OPEN')
                    <x-ts-badge :text="$row->status" color="amber" />
                @elseif($row->status == 'FOR APPROVAL')
                    <x-ts-badge :text="$row->status" color="yellow" />
                @elseif($row->status == 'FOR DISBURSEMENT')
                    <x-ts-badge :text="$row->status" color="cyan" />
                @elseif($row->status == 'CLOSED')
                    <x-ts-badge :text="$row->status" color="green" />
                @elseif($row->status == 'CANCELLED' || $row->status == 'REJECTED')
                    <x-ts-badge :text="$row->status" color="rose" />
                @endif
            </div>
        @endinteract
        @interact('column_created_at', $row)
            {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
        @endinteract
        @interact('column_amount', $row)
            ₱ {{ number_format($row->amount ?? 0, 2) }}
        @endinteract
        @interact('column_balance', $row)
            {{-- @php
                $balance = AdvancesForLiquidationService::currentBalance($row->id);
            @endphp
            ₱ {{ number_format($balance ?? 0, 2) }} --}}
        @endinteract
        @interact('column_received_by', $row)
            <div class="flex items-center gap-2">
                <x-ts-badge :text="$row->receivedBy?->fullName ?? 'Unknown'" outline />
            </div>
        @endinteract
        @interact('column_prepared_by', $row)
            <div class="flex items-center gap-2">
                <x-ts-badge :text="$row->preparedBy?->fullName ?? 'Unknown'" outline />
            </div>
        @endinteract
        @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                
                <a href="{{ route('cash-advances.validation.approval-view', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                    <x-ts-dropdown.items text="Approve" color="rose" separator icon="check" wire:click="approve({{$row->id}})"/>
                    <x-ts-dropdown.items text="Revise" color="rose" separator icon="arrow-path" wire:click="revise({{$row->id}})"/>
                    <x-ts-dropdown.items text="Reject" color="rose" separator icon="x-mark" wire:click="reject({{$row->id}})"/>
            </x-ts-dropdown>
        @endinteract
    </x-ts-table>


    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="New Employee cash advance" href="{{ route('employees-advances.create') }}" navigate />
    </x-ts-dial>

</div>
 