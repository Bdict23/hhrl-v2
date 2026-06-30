<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Transaction\Reimbursement;
use Illuminate\Support\Facades\Auth;
use App\Services\Transaction\ReimbursementService;
use TallStackUi\Traits\Interactions;




new class extends Component
{
    use WithPagination;
     use Interactions;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = [
            'column' => 'created_at',
            'direction' => 'desc',
        ];
    public $action;

        public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'pcv_id', 'label' => 'PCV Reference' , 'sortable' => false],
                ['index' => 'amount', 'label' => 'amount', 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'parepared by' ,'sortable' => false],
                ['index' => 'created_at', 'label' => 'Date'],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'rows' => Reimbursement::query()
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");
                })
                ->where('approved_by', Auth::user()->emp_id)
                ->where('status', 'FOR APPROVAL')
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }

    public function approve($id)
    {
        $reimbursement = Reimbursement::find($id);
        if($reimbursement)
        {
            $reimbursementService = app(ReimbursementService::class);
            $data = [
            'pcv_id' => $reimbursement->pcv_id,
            'amount' => str_replace(",", "", $reimbursement->amount),
            'afl_id' => $reimbursement->pettyCashVoucher->advance_liquidation_id,
            'branch_id' => Auth::user()->branch_id,
            'reimbursement_id' => $reimbursement->id,
        ];
        $this->toast()->success('success', "Reimbursement status {$reimbursement->reference} updated successfully!")->send();
        $reimbursementService->approve($data);
        }else{
            $this->toast()->success('error', "Something went wrong, can't approve this reimbursement!")->send();
        }
    }
    public function revise($id)
    {
        $reimbursement = Reimbursement::find($id);
        if($reimbursement)
        {
            $reimbursementService = app(ReimbursementService::class);
            $data = [
            'pcv_id' => $reimbursement->pcv_id,
            'amount' => str_replace(",", "", $reimbursement->amount),
            'afl_id' => $reimbursement->pettyCashVoucher->advance_liquidation_id,
            'branch_id' => Auth::user()->branch_id,
            'reimbursement_id' => $reimbursement->id,
        ];
        $reimbursementService->revise($data);
        $this->toast()->success('success', "Reimbursement status {$reimbursement->reference} updated successfully!")->send();

        }else{
            $this->toast()->success('error', "Something went wrong, can't approve this reimbursement!")->send();
        }
    }
    public function reject($id)
    {
        $reimbursement = Reimbursement::find($id);
        if($reimbursement)
        {
            $reimbursementService = app(ReimbursementService::class);
            $data = [
            'pcv_id' => $reimbursement->pcv_id,
            'amount' => str_replace(",", "", $reimbursement->amount),
            'afl_id' => $reimbursement->pettyCashVoucher->advance_liquidation_id,
            'branch_id' => Auth::user()->branch_id,
            'reimbursement_id' => $reimbursement->id,
        ];
        $this->toast()->success('success', "Reimbursement status {$reimbursement->reference} updated successfully!")->send();
        $reimbursementService->reject($data);
        }else{
            $this->toast()->success('error', "Something went wrong, can't approve this reimbursement!")->send();
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
                        ['label' => 'Transaction', 'link' =>  route('afl.summary'), 'icon' => 'cog'],
                        ['label' => 'Validation approval Summary', 'icon' => 'list-bullet' ],

                        ]"  class="mb-3"/>
                    </div>
                </div>
            </x-slot:header>
            @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->status == 'FOR APPROVAL')
                        <x-ts-badge :text="$row->status" color="amber" />
                    @elseif($row->status == 'CLOSED')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->requisition_status == 'CANCELLED'| $row->requisition_status == 'REJECTED')
                        <x-ts-badge :text="$row->requisition_status" color="rose" />
                    @endif
                </div>
            @endinteract
            @interact('column_pcv_id', $row)
                {{ $row->pettyCashVoucher->reference}}
            @endinteract
            @interact('column_created_at', $row)
                {{ \Illuminate\Support\Carbon::parse($row->trans_date)->format('M. d, Y') }}
            @endinteract
            @interact('column_amount', $row)
                ₱ {{  number_format(($row->amount) ?? 0 , 2) }}
            @endinteract
            @interact('column_prepared_by', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->fullName ?? 'Unknown'" outline />
                </div>
            @endinteract
            @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                <a href="{{route('reimbursement.validation.approval-view', ['id' => $row->id])}}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                    <x-ts-dropdown.items text="Approve" color="rose" separator icon="check" wire:click="approve({{$row->id}})"/>
                    <x-ts-dropdown.items text="Revise" color="rose" separator icon="arrow-path" wire:click="revise({{$row->id}})"/>
                    <x-ts-dropdown.items text="Reject" color="rose" separator icon="x-mark" wire:click="reject({{$row->id}})"/>
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>


        <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Reimbursement" href="{{ route('reimbursement.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>

</div>
