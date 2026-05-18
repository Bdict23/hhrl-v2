<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Inventory\FixedAsset\AssetCardex;
use App\Models\DataManagement\Item;

new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = ['column' => 'created_at', 'direction' => 'desc',];
    public $tab = 'Asset Lists';
    public $batchStatus = null;


    public function with(): array
    {
        return [
            'batchHeader' => [
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'purpose', 'label' => 'Purpose' , 'sortable' => false],
                ['index' => 'issued_date', 'label' => 'Date Issued'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'reviewed_by', 'label' => 'Reviewed By' , 'sortable' => false],
                ['index' => 'approved_by', 'label' => 'Approved By',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'assetListHeader' => [
                ['index' => 'item_description', 'label' => 'Item'],
                ['index' => 'item_code', 'label' => 'code', 'sortable' => false],
                ['index' => 'is_serialized', 'label' => 'With Serial' , 'sortable' => false],
                ['index' => 'qty', 'label' => 'Quantity'],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'batchRows' => AssetBatchHeader::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->when($this->batchStatus, function (Builder $query) {
                    return $query->where('status', $this->batchStatus);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'assetListRows' => Item::query()
                ->with('assetCardex')
                ->whereHas('assetCardex') // Only items that exist in the cardex
                ->when($this->search, function ($query) {
                    $query->where('item_description', 'like', "%{$this->search}%")
                        ->orWhere('item_code', 'like', "%{$this->search}%");
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
                    ];
    }
};
?>

<div>
    <x-ts-tab wire:model.live="tab">
        <x-ts-tab.items tab="Asset Lists">
                <x-ts-table :headers="$assetListHeader" :rows="$assetListRows" striped :$sort paginate persistent loading filter>
                    @interact('column_is_serialized', $row)
                        @if($row->assetCardex->first()->is_serialized)
                            <x-ts-badge text="Yes" color="fuchsia" />
                        @else
                            <x-ts-badge text="No" color="sky" />
                        @endif
                    @endinteract
                    @interact('column_qty', $row)
                        {{ $row->assetCardex->sum('qty') }}
                    @endinteract
                    @interact('column_action', $row)
                        <x-ts-dropdown icon="ellipsis-vertical" static lg>
                            <a href="">
                                <x-ts-dropdown.items text="View Cardex" icon="eye" />
                            </a>
                        </x-ts-dropdown>
                    @endinteract
                </x-ts-table>
        </x-ts-tab.items>
        <x-ts-tab.items tab="Registration">
            <x-ts-table :headers="$batchHeader" :rows="$batchRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->status == 'OPEN')
                        <x-ts-badge :text="$row->status" color="yellow" />
                    @elseif($row->status == 'CLOSED')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->status == 'CANCELLED')
                        <x-ts-badge :text="$row->status" color="rose" />
                    @endif
                </div>
                @endinteract
                @interact('column_issued_date', $row)
                    {{ \Carbon\Carbon::parse($row->issued_date)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_reviewed_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->reviewedBy?->name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_approved_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->approvedBy?->name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        @if($row->status == 'DRAFT')
                            <a href="{{route('purchase-order-edit', ['id' => $row->id])}}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{route('fixed-asset.batch-view', ['id' => $row->id])}}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
    </x-ts-tab>


    @if($tab == 'Registration')
        <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="Register new asset " href="{{ route('fixed-asset.registration')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>
    @endif

</div>
