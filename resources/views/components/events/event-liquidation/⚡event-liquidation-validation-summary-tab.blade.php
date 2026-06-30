<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\BanquetEvent\EventLiquidation;

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

            'batchHeaderForReview' => [
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'created_at', 'label' => 'date' ],
                ['index' => 'total_incurred', 'label' => 'Amount' , 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
             'batchHeaderForApproval' => [
                ['index' => 'reference', 'label' => 'Reference'],
                ['index' => 'created_at', 'label' => 'date' ],
                ['index' => 'total_incurred', 'label' => 'Amount' , 'sortable' => false],
                ['index' => 'event_id', 'label' => 'Event'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],

            'batchRowsForReview' => EventLiquidation::query()
                ->where('reviewed_by', auth()->user()->emp_id)
                ->where('status', 'FOR REVIEW')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForApproval' => EventLiquidation::query()
                ->where('approved_by', auth()->user()->emp_id)
                ->where('status', 'FOR APPROVAL')
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
                @interact('column_created_at', $row)
                    {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_total_incurred', $row)
                     ₱ {{ number_format($row->total_incurred, 2) }}
                @endinteract
                @interact('column_event_id', $row)
                    {{ ($row->event?->event_name ?? '') }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('event-liquidation.validation.review-show', ['id' => $row->id])}}">
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
                @interact('column_created_at', $row)
                    {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                 @interact('column_total_incurred', $row)
                     ₱ {{ number_format($row->total_incurred, 2) }}
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
    </x-ts-tab>
</div>
