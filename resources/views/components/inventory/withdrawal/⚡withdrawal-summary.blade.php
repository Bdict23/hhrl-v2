<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Inventory\Withdrawal;


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
                ['index' => 'withdrawal_status', 'label' => 'Status'],
                ['index' => 'reference_number', 'label' => 'reference', 'sortable' => false],
                ['index' => 'department_id', 'label' => 'Department', 'sortable' => false],
                ['index' => 'withdrawal_amount', 'label' => 'Withdrawal Amount' , 'sortable' => false],
                ['index' => 'type', 'label' => 'Type' , 'sortable' => false],
                ['index' => 'PREPARED_BY', 'label' => 'Prepared By',  'sortable' => false],
                ['index' => 'created_at', 'label' => 'Created Date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'rows' => Withdrawal::query()
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference_number', 'like', "%{$this->search}%");
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('withdrawal_status', $this->status);
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
                          ['label' => 'Inventory','link' => route('withdrawal-summary'), 'icon' => 'archive-box' ],
                          ['label' => 'Withdrawal Summary', 'icon' => 'list-bullet'],
              ]"  />
                <div class="lg:flex gap-3 grid grid-cols-3">
                    <x-ts-select.native wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                            ['name' => 'All', 'id' => null],
                            ['name' => 'DRAFT', 'id' => 'PREPARING'],
                            ['name' => 'FOR REVIEW', 'id' => 'FOR REVIEW'],
                            ['name' => 'FOR APPROVAL', 'id' => 'FOR APPROVAL'],
                            ['name' => 'REJECTED', 'id' => 'REJECTED'],
                            ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                            ['name' => 'APPROVED', 'id' => 'APPROVED'],
                    ]" select="label:name|value:id" />
                    <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                </div>
    </div>

    <div>
        <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
            @interact('column_withdrawal_status', $row)
                <div class="flex items-center gap-2">
                    @if($row->withdrawal_status == 'PREPARING')
                        <x-ts-badge text="DRAFT" color="secondary" />
                    @elseif($row->withdrawal_status == 'FOR REVIEW')
                        <x-ts-badge :text="$row->withdrawal_status" color="yellow" />
                    @elseif($row->withdrawal_status == 'FOR APPROVAL')
                        <x-ts-badge :text="$row->withdrawal_status" color="cyan" />
                    @elseif($row->withdrawal_status == 'REJECTED')
                        <x-ts-badge :text="$row->withdrawal_status" color="rose" />
                    @elseif($row->withdrawal_status == 'CANCELLED')
                        <x-ts-badge :text="$row->withdrawal_status" color="rose" />
                    @elseif($row->withdrawal_status == 'APPROVED')
                        <x-ts-badge :text="$row->withdrawal_status" color="green" />
                    @endif
                </div>
            @endinteract
            @interact('column_created_at', $row)
                {{ \Illuminate\Support\Carbon::parse($row->created_at)->format('M. d, Y') }}
            @endinteract
            @interact('column_department_id', $row)
                {{ $row->department?->department_name ?? 'Unknown' }}
            @endinteract
            @interact('column_reference_number', $row)
                {{ $row->reference_number }}
            @endinteract
            
            @interact('column_withdrawal_amount', $row)
                ₱ {{  number_format(($row->cost_amount) ?? 0 , 2) }}
            @endinteract
            @interact('column_type', $row)
                @if($row->type)
                    {{ $row->type->name }}
                @else
                    Unknown
                @endif
            @endinteract
            @interact('column_PREPARED_BY', $row)
                <div class="flex items-center gap-2">
                    <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                </div>
            @endinteract
            @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>
                @if ($row->withdrawal_status == 'PREPARING')
                    <a href="{{ route('withdrawal.edit', ['id' => $row->id]) }}">
                        <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                    </a>
                @endif
                <a href="{{ route('withdrawal.view', ['id' => $row->id]) }}">
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
            <x-ts-dial.items icon="plus" label="New Withdrawal" href="{{ route('withdrawal.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
        </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
