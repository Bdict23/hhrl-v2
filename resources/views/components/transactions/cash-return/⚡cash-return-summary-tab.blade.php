<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Transaction\CashReturn;

new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = ['column' => 'created_at', 'direction' => 'desc',];
    public $tab = 'CRS (PCV)';


    public function with(): array
    {
        return [
            'pcvCrsHeader' => [
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'pcv_id', 'label' => 'PCV-REF' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'amount_returned', 'label' => 'Return Amount' , 'sortable' => false],
                ['index' => 'created_at', 'label' => 'Returned Date'],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'eventCrsHeader' => [
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'event_id', 'label' => 'Event-REF' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'amount_returned', 'label' => 'Return Amount' , 'sortable' => false],
                ['index' => 'created_at', 'label' => 'Returned Date'],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],


            'pcvCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('pcv_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'eventCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('event_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

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
        <x-ts-tab.items tab="CRS (PCV)">
            <x-ts-table :headers="$pcvCrsHeader" :rows="$pcvCrsRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->status == 'FINAL')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->status == 'CANCELLED')
                        <x-ts-badge :text="$row->status" color="rose" />
                    @endif
                </div>
                @endinteract
                @interact('column_pcv_id', $row)
                    <span class="font-mono">{{ $row->pettyCashVoucher->reference }}</span>
                @endinteract
                @interact('column_created_at', $row)
                    {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->fullName ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        @if($row->status == 'DRAFT')
                            <a href="{{route('purchase-order-edit', ['id' => $row->id])}}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{route('fixed-asset.validation.review-view', ['id' => $row->id])}}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
        <x-ts-tab.items tab="CRS (BEO)">
            <x-ts-table :headers="$eventCrsHeader" :rows="$eventCrsRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->status == 'FINAL')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->status == 'CANCELLED')
                        <x-ts-badge :text="$row->status" color="rose" />
                    @endif
                </div>
                @endinteract
                @interact('column_pcv_id', $row)
                    <span class="font-mono">{{ $row->pettyCashVoucher->reference }}</span>
                @endinteract
                @interact('column_created_at', $row)
                    {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->fullName ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        @if($row->status == 'DRAFT')
                            <a href="{{route('purchase-order-edit', ['id' => $row->id])}}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{route('fixed-asset.validation.review-view', ['id' => $row->id])}}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
        <x-ts-tab.items tab="CRS (CASH ADVANCES)">


        </x-ts-tab.items>
    </x-ts-tab>


    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New CRS for PCV" href="{{ route('cash-return.pcv-crs.create')}}" navigate />
            <x-ts-dial.items icon="plus" label="New CRS for Event" href="{{ route('cash-return.event-crs.create')}}" navigate />
            <x-ts-dial.items icon="plus" label="New CRS for Cash Advances" href="{{ route('cash-return.cash-advance-crs.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
    </x-ts-dial>

</div>
