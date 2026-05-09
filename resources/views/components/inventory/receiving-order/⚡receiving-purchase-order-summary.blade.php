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

    public array $sort = [
            'column' => 'created_at',
            'direction' => 'desc',
        ];
    public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'RECEIVING_STATUS', 'label' => 'Status'],
                ['index' => 'REQUISITION_ID', 'label' => 'P.O Number'],
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
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }
};
?>

<div>
    <div class="lg:flex lg:justify-between grid mb-4">
        <x-ts-breadcrumbs :items="[
                        ['label' => 'Inventory', ],
                        ['label' => 'Receiving Summary', 'link' => route('PO-summary')],
            ]"  class="mb-3"/>
    </div>

    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
            <x-slot:header>
                <div class="lg:flex lg:justify-between mb-3 grid">
                    <div class="w-auto mb-3">
                        <x-ts-button href="https://google.com.br" target="_blank" icon="plus">New</x-ts-button>
                    </div>
                    <div class="lg:flex gap-3 grid grid-cols-3">
                        <x-ts-select.native :options="[
                            ['name' => 'DRAFT', 'id' => 1],
                            ['name' => 'FINAL', 'id' => 2],
                        ]" select="label:name|value:id" />
                        <x-ts-date range placeholder="Date range" />
                        <x-ts-button icon="funnel" position="left" sm class="text-center lg:w-20 lg:h-9">Filter</x-ts-button>
                    </div>
                </div>
            </x-slot:header>
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
    <x-ts-back-to-top />
</div>
