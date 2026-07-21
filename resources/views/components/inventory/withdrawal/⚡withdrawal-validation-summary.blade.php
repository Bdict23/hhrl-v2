<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Inventory\Withdrawal;


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
                ['index' => 'reference_number', 'label' => 'Reference'],
                ['index' => 'created_at', 'label' => 'Date' ],
                ['index' => 'cost_amount', 'label' => 'Total Cost' , 'sortable' => false],
                ['index' => 'type_id', 'label' => 'Type'],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'batchHeaderForApproval' => [
                ['index' => 'reference_number', 'label' => 'Reference'],
                ['index' => 'created_at', 'label' => 'Date'],
                ['index' => 'cost_amount', 'label' => 'Total Cost', 'sortable' => false],
                ['index' => 'type_id', 'label' => 'Type',  'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'Prepared By' , 'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],


            'batchRowsForReview' => Withdrawal::query()
                ->where('reviewed_by', auth()->user()->emp_id)
                ->where('withdrawal_status', 'FOR REVIEW')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference_number', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'batchRowsForApproval' => Withdrawal::query()
                ->where('approved_by', auth()->user()->emp_id)
                ->where('withdrawal_status', 'FOR APPROVAL')
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference_number', 'like', "%{$this->search}%");

                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Validation', 'link' => route('withdrawal.validation-summary'), 'icon' => 'check-badge' ],
                              ['label' => 'withdrawal validation tab', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>
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
                @interact('column_cost_amount', $row)
                     ₱ {{ number_format($row->cost_amount, 2) }}
                @endinteract
                @interact('column_type_id', $row)
                    {{ ($row->type?->name ?? '') }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('withdrawal.validation.review-show', ['id' => $row->id])}}">
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
                 @interact('column_cost_amount', $row)
                     ₱ {{ number_format($row->cost_amount, 2) }}
                @endinteract
                @interact('column_type_id', $row)
                    {{ ($row->type?->name ?? '') }}
                @endinteract
                @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        <a href="{{route('withdrawal.validation.approval-show', ['id' => $row->id])}}">
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
