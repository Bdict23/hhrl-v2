<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BanquetEvent\EventLiquidation;
use Illuminate\Support\Facades\Auth;



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
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'reference', 'label' => 'reference', 'sortable' => false],
                ['index' => 'event_name', 'label' => 'event', 'sortable' => false],
                ['index' => 'budget', 'label' => 'Budget' , 'sortable' => false],
                ['index' => 'total_incurred', 'label' => 'incurred' , 'sortable' => false],
                ['index' => 'prepared_by', 'label' => 'prepared by',  'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date',  'sortable' => false],
                ['index' => 'action', 'label' => 'action'],

            ],
            'rows' => EventLiquidation::query()
                ->when($this->search, function (Builder $query) {
                    return  $query->where('reference', 'like', "%{$this->search}%");
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->where('branch_id', Auth::user()->branch_id)
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
                          ['label' => 'Event','link' => route('event-liquidation-summary'), 'icon' => 'archive-box' ],
                          ['label' => 'Event liquidation Summary', 'icon' => 'list-bullet'],
              ]"  />
                <div class="lg:flex gap-3 grid grid-cols-3">
                    <x-ts-select.native wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                            ['name' => 'All', 'id' => null],
                            ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                            ['name' => 'FULFILLED', 'id' => 'FULFILLED'],
                            ['name' => 'FOR PO', 'id' => 'FOR PO'],
                            ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                    ]" select="label:name|value:id" />
                    <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                </div>
    </div>

    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
            @interact('column_created_at', $row)
                {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
            @endinteract
             @interact('column_reference', $row)
                {{ $row->reference }}
            @endinteract
            @interact('column_event_name', $row)
                {{ $row->event->event_name }}
            @endinteract
            @interact('column_budget', $row)
               ₱ {{ number_format($row->event->banquetEventBudget->suggested_amount, 2) }}
            @endinteract
            @interact('column_total_incurred', $row)
               ₱ {{ number_format($row->total_incurred, 2) }}
            @endinteract
             @interact('column_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->status == 'DRAFT')
                        <x-ts-badge :text="$row->status" color="gray" />
                    @elseif($row->status == 'FOR REVIEW')
                        <x-ts-badge :text="$row->status" color="teal" />
                    @elseif($row->status == 'FOR SETTLEMENT')
                        <x-ts-badge :text="$row->status" color="amber" />
                    @elseif($row->status == 'FOR APPROVAL')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->status == 'FOR APPROVAL')
                        <x-ts-badge :text="$row->status" color="cyan" />
                    @elseif($row->status == 'CLOSED')
                        <x-ts-badge :text="$row->status" color="green" />
                    @elseif($row->status == 'CANCELLED')
                        <x-ts-badge :text="$row->status" color="red" />
                    @endif
                </div>
            @endinteract
            @interact('column_prepared_by', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                </div>
            @endinteract
            @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                @if ($row->status == 'DRAFT')
                    <a href="{{ route('event-liquidation-edit', ['id' => $row->id]) }}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @endif
                <a href="{{ route('event-liquidation-view', ['id' => $row->id]) }}">
                    <x-ts-dropdown.items text="View" separator icon="eye" />
                </a>
                <a>
                    <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                </a>
            </x-ts-dropdown>
        @endinteract
        </x-ts-table>
    </div>
    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Liquidation" href="{{ route('event-liquidation-create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
