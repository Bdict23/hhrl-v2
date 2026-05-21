<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;


new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = [
            'column' => 'trans_date',
            'direction' => 'desc',
        ];

    public ?string $status = null;
    public ?array $dates = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function updatedDates()
    {
        $this->resetPage();
    }

    public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'requisition_status', 'label' => 'Status'],
                ['index' => 'requisition_number', 'label' => 'Reference'],
                ['index' => 'trans_date', 'label' => 'Date'],
                ['index' => 'total_amount', 'label' => 'Amount' , 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'rows' => PurchaseOrder::query()
                ->with('preparedBy','event') // Eager load the relationship
                ->when($this->search, function (Builder $query) {
                    return  $query->where('requisition_number', 'like', "%{$this->search}%");

                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('requisition_status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('trans_date', $this->dates);
                    }
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
                'Eit' => 'Edit',
                'type' => 'View',
                'type' => 'Cancel'
        ];
    }
};
?>

<div>
    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent loading striped filter>
            <x-slot:header>
                <div class="lg:flex lg:justify-between mb-3 grid">
                    <div class="w-auto mb-3">
                         <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                            ['label' => 'Inventory', 'icon' => 'archive-box' ],
                            ['label' => 'Purchase Summary', 'link' => route('purchase-order-summary'), 'icon' => 'list-bullet'],
                        ]"  class="mb-3"/>
                    </div>
                    <div class="lg:flex gap-2 grid grid-cols-2">
                        <x-ts-select.native
                            wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                                ['name' => 'FOR APPROVAL', 'id' => 'FOR APPROVAL'],
                                ['name' => 'PARTIALLY FULFILLED', 'id' => 'PARTIALLY FULFILLED'],
                                ['name' => 'TO RECEIVE', 'id' => 'TO RECEIVE'],
                                ['name' => 'FOR REVIEW', 'id' => 'FOR REVIEW'],
                                ['name' => 'REJECTED', 'id' => 'REJECTED'],
                                ['name' => 'DRAFT', 'id' => 'PREPARING'],
                                ['name' => 'COMPLETED', 'id' => 'COMPLETED'],
                                ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                            ]"
                            select="label:name|value:id" />
                        <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                    </div>
                </div>
            </x-slot:header>
            @interact('column_trans_date', $row)
                {{ \Illuminate\Support\Carbon::parse($row->trans_date)->format('M. d, Y') }}
            @endinteract
             @interact('column_requisition_status', $row)
                <div class="flex items-center gap-2">
                    @if ($row->requisition_status == 'FOR APPROVAL')
                        <x-ts-badge :text="$row->requisition_status" color="cyan" />
                    @elseif($row->requisition_status == 'PARTIALLY FULFILLED')
                        <x-ts-badge :text="$row->requisition_status" color="amber" />
                    @elseif($row->requisition_status == 'TO RECEIVE')
                        <x-ts-badge :text="$row->requisition_status" color="primary" />
                    @elseif($row->requisition_status == 'FOR REVIEW')
                        <x-ts-badge :text="$row->requisition_status" color="teal" />
                    @elseif($row->requisition_status == 'REJECTED')
                        <x-ts-badge :text="$row->requisition_status" color="red" />
                    @elseif($row->requisition_status == 'PREPARING')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->requisition_status == 'COMPLETED')
                        <x-ts-badge :text="$row->requisition_status" color="green" />
                    @elseif($row->requisition_status == 'CANCELLED')
                        <x-ts-badge :text="$row->requisition_status" color="rose" />
                    @endif
                </div>
            @endinteract
             @interact('column_total_amount', $row)
                ₱ {{  number_format(($row->total_amount) ?? 0 , 2) }}
            @endinteract
            @interact('column_event_id', $row)
                {{ $row->event?->event_name ?? '' }}
            @endinteract
            @interact('column_prepared_by', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->name ?? 'Unknown'" outline />
                </div>
            @endinteract
             @interact('column_action', $row , $type)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                @if($row->requisition_status == 'PREPARING')
                    <a href="{{route('purchase-order-edit', ['id' => $row->id])}}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @endif
                <a href="{{route('purchase-order-view', ['id' => $row->id])}}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <a>
                    <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                </a>
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>
    </div>
    <x-ts-back-to-top />
    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Purchase Order" href="{{ route('purchase-order-create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>
</div>
