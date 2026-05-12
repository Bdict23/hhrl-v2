<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Inventory\FixedAsset\AssetBatchHeader;

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
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'purpose', 'label' => 'Purpose' , 'sortable' => false],
                ['index' => 'issued_date', 'label' => 'Date Issued'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'reviewed_by', 'label' => 'Reviewed By' , 'sortable' => false],
                ['index' => 'approved_by', 'label' => 'Approved By',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'batchHeaderForReview' => [
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'purpose', 'label' => 'Purpose' , 'sortable' => false],
                ['index' => 'issued_date', 'label' => 'Date Issued'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'reviewed_by', 'label' => 'Reviewed By' , 'sortable' => false],
                ['index' => 'approved_by', 'label' => 'Approved By',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'batchHeaderForApproval' => [
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'status', 'label' => 'Status'  , 'sortable' => false],
                ['index' => 'purpose', 'label' => 'Purpose' , 'sortable' => false],
                ['index' => 'issued_date', 'label' => 'Date Issued'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'reviewed_by', 'label' => 'Reviewed By' , 'sortable' => false],
                ['index' => 'approved_by', 'label' => 'Approved By',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'batchRows' => AssetBatchHeader::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->where(function ($query) {
                    $query->where('reviewed_by', auth()->user()->emp_id)
                        ->orWhere('approved_by', auth()->user()->emp_id);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForReview' => AssetBatchHeader::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->where('reviewed_by', auth()->user()->emp_id)
                ->where('reviewed_date', null)
                ->where('status', 'OPEN')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForApproval' => AssetBatchHeader::query()
                ->with('preparedBy','reviewedBy','approvedBy')
                ->where('approved_by', auth()->user()->emp_id)
                ->where('approved_date', null)
                ->where('reviewed_date', '!=', null)
                ->where('status', 'OPEN')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

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
        <x-ts-tab.items tab="For Approval">
            <x-ts-table :headers="$batchHeaderForApproval" :rows="$batchRowsForApproval" striped :$sort paginate persistent loading filter>
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
                        <a href="{{route('fixed-asset.validation.approval-view', ['id' => $row->id])}}">
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
