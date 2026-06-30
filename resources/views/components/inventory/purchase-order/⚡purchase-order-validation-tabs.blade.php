<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Inventory\PurchaseOrder;

new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = ['column' => 'created_at', 'direction' => 'desc',];
    public $tab = 'For Review';


    public function with(): array
    {
        return [
            'batchHeader' => [
                ['index' => 'requisition_status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'requisition_number', 'label' => 'Reference'],
                ['index' => 'trans_date', 'label' => 'date'],
                ['index' => 'total_amount', 'label' => 'Amount', 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'batchHeaderForReview' => [
                ['index' => 'requisition_number', 'label' => 'Reference'],
                ['index' => 'trans_date', 'label' => 'date' ],
                ['index' => 'total_amount', 'label' => 'Amount' , 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'batchHeaderForApproval' => [
                ['index' => 'requisition_number', 'label' => 'Reference'],
                ['index' => 'trans_date', 'label' => 'date'],
                ['index' => 'total_amount', 'label' => 'Amount', 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event',  'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'batchRows' => PurchaseOrder::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('requisition_number', 'like', "%{$this->search}%");

                })
                ->where(function ($query) {
                    $query->where('reviewed_by', auth()->user()->emp_id)
                        ->orWhere('approved_by', auth()->user()->emp_id);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForReview' => PurchaseOrder::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->where('reviewed_by', auth()->user()->emp_id)
                ->where('requisition_status', 'FOR REVIEW')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('requisition_number', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForApproval' => PurchaseOrder::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->where('approved_by', auth()->user()->emp_id)
                ->where('requisition_status', 'FOR APPROVAL')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('requisition_number', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }
};
?>

<div>
    <x-ts-tab wire:model.live="tab">
        <x-ts-tab.items tab="For Review">
            <x-ts-table :headers="$batchHeaderForReview" :rows="$batchRowsForReview" striped :$sort paginate persistent loading filter>
                @interact('column_trans_date', $row)
                    {{ \Carbon\Carbon::parse($row->trans_date)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_total_amount', $row)
                     ₱ {{ number_format($row->total_amount, 2) }}
                @endinteract
                @interact('column_event_id', $row)
                    {{ ($row->event?->event_name ?? '') }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('purchase-order.validation.review-show', ['id' => $row->id])}}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
        <x-ts-tab.items tab="For Approval">
            <x-ts-table :headers="$batchHeaderForApproval" :rows="$batchRowsForApproval" striped :$sort paginate persistent loading filter>
                @interact('column_trans_date', $row)
                    {{ \Carbon\Carbon::parse($row->issued_date)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                 @interact('column_total_amount', $row)
                     ₱ {{ number_format($row->total_amount, 2) }}
                @endinteract
                @interact('column_event_id', $row)
                    {{ ($row->event?->event_name ?? '') }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('purchase-order.validation.approval-show', ['id' => $row->id])}}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark"/>
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
        <x-ts-tab.items tab="Summary">
            <x-ts-table :headers="$batchHeader" :rows="$batchRows" striped :$sort paginate persistent loading filter>
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
                @interact('column_trans_date', $row)
                    {{ \Carbon\Carbon::parse($row->issued_date)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_amount', $row)
                    ₱ {{ number_format($row->total_amount, 2) }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('purchase-order.validation.review-show', ['id' => $row->id])}}">
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
