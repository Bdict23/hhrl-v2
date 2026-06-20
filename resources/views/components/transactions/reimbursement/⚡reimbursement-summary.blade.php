<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Transaction\Reimbursement;

new class extends Component
{
    use WithPagination;
    public ?string $status = null;
    public ?array $dates = null;
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
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }

};
?>

<div>
        <x-ts-table :$headers :$rows :$sort paginate loading striped filter>
            <x-slot:header>
                <div class="lg:flex lg:justify-between mb-3 grid">
                    <div class="w-auto mb-3">
                       <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                        ['label' => 'Transaction', 'link' =>  route('reimbursement.summary'), 'icon' => 'cog'],
                        ['label' => 'Reimbursement Summary', 'icon' => 'list-bullet' ],

                        ]"  class="mb-3"/>
                    </div>
                    <div class="lg:flex gap-2 grid grid-cols-2">
                        <x-ts-select.native
                            wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                                //ENUM('DRAFT','OPEN','CLOSED','CANCELLED')
                                ['name' => 'All', 'id' => null],
                                ['name' => 'DRAFT', 'id' => 'DRAFT'],
                                ['name' => 'FOR APPROVAL', 'id' => 'FOR APPROVAL'],
                                ['name' => 'REJECTED', 'id' => 'REJECTED'],
                                ['name' => 'CLOSED', 'id' => 'CLOSED'],
                                ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                            ]"
                            select="label:name|value:id" />
                        <x-ts-date wire:model.live="dates" range placeholder="Date range" />
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
                    @elseif($row->status == 'CANCELLED'| $row->requisition_status == 'REJECTED')
                        <x-ts-badge :text="$row->status" color="rose" />
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
                @if($row->status == 'DRAFT')
                    <a href="{{route('reimbursement.edit',  ['id' => $row->id])}}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @endif
                <a href="{{route('reimbursement.view', ['id' => $row->id])}}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <a>
                    <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                </a>
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>


        <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Reimbursement" href="{{ route('reimbursement.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>

</div>
