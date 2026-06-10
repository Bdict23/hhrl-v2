<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventory\Receiving;


new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public ?string $status = null;
    public ?array $dates = null;

    public array $sort = [
            'column' => 'created_at',
            'direction' => 'desc',
        ];
    public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'RECEIVING_STATUS', 'label' => 'Status'],
                ['index' => 'REQUISITION_ID', 'label' => 'P.O Number', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'Receive Date'],
                ['index' => 'receive_amount', 'label' => 'Receive Amount' , 'sortable' => false],
                ['index' => 'remarks', 'label' => 'Remarks' , 'sortable' => false],
                ['index' => 'PREPARED_BY', 'label' => 'Prepared By',  'sortable' => false],
            ],
            'rows' => Receiving::query()
                ->with('preparedBy','purchaseOrder') // Eager load the relationship
                ->when($this->search, function (Builder $query) {
                    return $query->whereHas('purchaseOrder', function (Builder $query) {
                        $query->where('requisition_number', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('RECEIVING_STATUS', $this->status);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }
};
?>

<div>
    <div class="lg:flex lg:justify-between grid mb-4">
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                          ['label' => 'Inventory','link' => route('receiving-summary'), 'icon' => 'archive-box' ],
                          ['label' => 'Receiving Summary', 'icon' => 'list-bullet'],
              ]"  />
                <div class="lg:flex gap-3 grid grid-cols-3">
                    <x-ts-select.native wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                            ['name' => 'All', 'id' => null],
                            ['name' => 'DRAFT', 'id' => 'DRAFT'],
                            ['name' => 'FINAL', 'id' => 'FINAL'],
                    ]" select="label:name|value:id" />
                    <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                </div>
    </div>

    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
            @interact('column_created_at', $row)
                {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
            @endinteract
            @interact('column_REQUISITION_ID', $row)
                {{ $row->purchaseOrder->requisition_number }}
            @endinteract
             @interact('column_RECEIVING_STATUS', $row)
                <div class="flex items-center gap-2">
                    @if($row->RECEIVING_STATUS == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->RECEIVING_STATUS == 'FINAL')
                        <x-ts-badge :text="$row->RECEIVING_STATUS" color="green" />
                    @endif
                </div>
            @endinteract
             @interact('column_receive_amount', $row)
                ₱ {{  number_format(($row->receive_amount) ?? 0 , 2) }}
            @endinteract
            @interact('column_PREPARED_BY', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->name ?? 'Unknown'" outline />
                </div>
            @endinteract
        </x-ts-table>
    </div>
    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Receiving" href="{{ route('purcahse-order.receving.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
