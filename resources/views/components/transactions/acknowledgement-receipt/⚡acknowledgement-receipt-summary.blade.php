<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Transaction\Acknowledgement;

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
                ['index' => 'check_number', 'label' => 'Check number', 'sortable' => false],
                ['index' => 'check_amount', 'label' => 'Amount'],
                ['index' => 'account_name', 'label' => 'Acount' , 'sortable' => false],
                ['index' => 'bank_id', 'label' => 'Bank',  'sortable' => false],
                ['index' => 'check_date', 'label' => 'Check date',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'rows' => Acknowledgement::query()
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('check_date', $this->dates);
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
                        ['label' => 'Transaction', 'link' =>  route('acknowledgement-receipt.summary'), 'icon' => 'cog'],
                        ['label' => 'Acknowledgement Receipt Summary', 'icon' => 'list-bullet' ],

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
                                ['name' => 'OPEN', 'id' => 'OPEN'],
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
                    @elseif($row->status == 'OPEN')
                        <x-ts-badge :text="$row->status" color="amber" />
                    @elseif($row->status == 'CLOSED')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->requisition_status == 'CANCELLED')
                        <x-ts-badge :text="$row->requisition_status" color="rose" />
                    @endif
                </div>
            @endinteract
            @interact('column_check_date', $row)
                {{ \Illuminate\Support\Carbon::parse($row->check_date)->format('M. d, Y') }}
            @endinteract
            @interact('column_check_amount', $row)
                ₱ {{  number_format(($row->check_amount) ?? 0 , 2) }}
            @endinteract

            @interact('column_bank_id', $row)
                {{ $row->bank->bank_name }}
            @endinteract
            @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                @if($row->status == 'DRAFT')
                    <a href="{{route('petty-cash-voucher.edit',  ['id' => $row->id])}}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @endif
                <a href="{{route('petty-cash-voucher.view', ['id' => $row->id])}}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <a>
                    <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                </a>
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>


        <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Acknowledgement Receipt" href="{{ route('acknowledgement-receipt.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>

</div>
