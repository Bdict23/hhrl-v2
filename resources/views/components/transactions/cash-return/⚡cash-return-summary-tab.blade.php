<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;

use App\Models\Inventory\FixedAsset\AssetBatchHeader;
use App\Models\Transaction\CashReturn;

new class extends Component {
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = ['column' => 'created_at', 'direction' => 'desc'];
    public $tab = 'CRS (PCV)';
    public ?string $status = null;
    public ?array $dates = null;

    public function with(): array
    {
        return [
            'pcvCrsHeader' => [
                ['index' => 'status', 'label' => 'Status', 'sortable' => false], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'pcv_id', 'label' => 'PCV-REF', 'sortable' => false], 
                ['index' => 'prepared_by', 'label' => 'Prepared By', 'sortable' => false], 
                ['index' => 'amount_returned', 'label' => 'Return Amount', 'sortable' => false], 
                ['index' => 'created_at', 'label' => 'Returned Date'], 
                ['index' => 'action', 'label' => 'Action', 'sortable' => false]],

            'aflCrsHeader' => [
                ['index' => 'status', 'label' => 'Status', 'sortable' => false], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'advances_liquidation_id', 'label' => 'AFL-REF', 'sortable' => false], 
                ['index' => 'prepared_by', 'label' => 'Prepared By', 'sortable' => false], 
                ['index' => 'amount_returned', 'label' => 'Return Amount', 'sortable' => false], 
                ['index' => 'created_at', 'label' => 'Returned Date'], 
                ['index' => 'action', 'label' => 'Action', 'sortable' => false]],

            'ecaCrsHeader' => [
                ['index' => 'status', 'label' => 'Status', 'sortable' => false], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'advances_liquidation_id', 'label' => 'AFL-REF', 'sortable' => false], 
                ['index' => 'prepared_by', 'label' => 'Prepared By', 'sortable' => false], 
                ['index' => 'amount_returned', 'label' => 'Return Amount', 'sortable' => false], 
                ['index' => 'created_at', 'label' => 'Returned Date'], 
                ['index' => 'action', 'label' => 'Action', 'sortable' => false]],

            'eventCrsHeader' => [
                ['index' => 'status', 'label' => 'Status', 'sortable' => false], 
                ['index' => 'reference', 'label' => 'Reference'], 
                ['index' => 'event_id', 'label' => 'Event-REF', 'sortable' => false], 
                ['index' => 'prepared_by', 'label' => 'Prepared By', 'sortable' => false], 
                ['index' => 'amount_returned', 'label' => 'Return Amount', 'sortable' => false], 
                ['index' => 'created_at', 'label' => 'Returned Date'], 
                ['index' => 'action', 'label' => 'Action', 'sortable' => false]],

            'pcvCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('pcv_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%");
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'aflCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('advances_liquidation_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%");
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'ecaCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('employee_advance_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%");
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('created_at', $this->dates);
                    }
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),

            'eventCrsRows' => CashReturn::query()
                ->where('branch_id', Auth::user()->branch_id)
                ->where('event_id', '!=', null)
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%");
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
            <div class="flex justify-end gap-4 mb-4">
                <x-ts-select.native wire:model.live="status" placeholder="All Statuses" :options="[
                    //ENUM('DRAFT','OPEN','CLOSED','CANCELLED')
                    ['name' => 'All', 'id' => null],
                    ['name' => 'DRAFT', 'id' => 'DRAFT'],
                    ['name' => 'FINAL', 'id' => 'FINAL'],
                    ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                ]"
                    select="label:name|value:id" />
                <x-ts-date wire:model.live="dates" range placeholder="Date range" />
            </div>
            <x-ts-table :headers="$pcvCrsHeader" :rows="$pcvCrsRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                    <div class="flex items-center gap-2">
                        @if ($row->status == 'DRAFT')
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
                        @if ($row->status == 'DRAFT')
                            <a href="{{ route('cash-return.pcv-crs.edit', ['id' => $row->id]) }}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{ route('cash-return.pcv-crs.view', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>

        <x-ts-tab.items tab="CRS (AFL)">
            <div class="flex justify-end gap-4 mb-4">
                <x-ts-select.native wire:model.live="status" placeholder="All Statuses" :options="[
                    ['name' => 'All', 'id' => null],
                    ['name' => 'DRAFT', 'id' => 'DRAFT'],
                    ['name' => 'FINAL', 'id' => 'FINAL'],
                    ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                ]"
                    select="label:name|value:id" />
                <x-ts-date wire:model.live="dates" range placeholder="Date range" />
            </div>
            <x-ts-table :headers="$aflCrsHeader" :rows="$aflCrsRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                    <div class="flex items-center gap-2">
                        @if ($row->status == 'DRAFT')
                            <x-ts-badge text="DRAFT" color="secondary" />
                        @elseif($row->status == 'FINAL')
                            <x-ts-badge :text="$row->status" color="green" />
                        @elseif($row->status == 'CANCELLED')
                            <x-ts-badge :text="$row->status" color="rose" />
                        @endif
                    </div>
                @endinteract
                @interact('column_advances_liquidation_id', $row)
                    <span class="font-mono">{{ $row->advancesForLiquidation->reference }}</span>
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
                        @if ($row->status == 'DRAFT')
                            <a href="{{ route('cash-return.afl-crs.edit', ['id' => $row->id]) }}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{ route('cash-return.afl-crs.view', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>

        <x-ts-tab.items tab="CRS (ECA)">
            <div class="flex justify-end gap-4 mb-4">
                <x-ts-select.native wire:model.live="status" placeholder="All Statuses" :options="[
                    ['name' => 'All', 'id' => null],
                    ['name' => 'DRAFT', 'id' => 'DRAFT'],
                    ['name' => 'FINAL', 'id' => 'FINAL'],
                    ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                ]"
                    select="label:name|value:id" />
                <x-ts-date wire:model.live="dates" range placeholder="Date range" />
            </div>
            <x-ts-table :headers="$ecaCrsHeader" :rows="$ecaCrsRows" striped :$sort paginate persistent loading filter>
                @interact('column_status', $row)
                    <div class="flex items-center gap-2">
                        @if ($row->status == 'DRAFT')
                            <x-ts-badge text="DRAFT" color="secondary" />
                        @elseif($row->status == 'FINAL')
                            <x-ts-badge :text="$row->status" color="green" />
                        @elseif($row->status == 'CANCELLED')
                            <x-ts-badge :text="$row->status" color="rose" />
                        @endif
                    </div>
                @endinteract
                @interact('column_advances_liquidation_id', $row)
                    <span class="font-mono">{{ $row->employeeCashAdvance->reference }}</span>
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
                        @if ($row->status == 'DRAFT')
                            <a href="{{ route('cash-return.employee-advances.edit', ['id' => $row->id]) }}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{ route('cash-return.employee-advances.view', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </x-ts-tab.items>
        
    </x-ts-tab>


    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="New CRS for PCV" href="{{ route('cash-return.pcv-crs.create') }}"
            navigate />
        {{-- <x-ts-dial.items icon="plus" label="New CRS for Event" href="{{ route('cash-return.event-crs.create')}}" navigate /> --}}
        <x-ts-dial.items icon="plus" label="New CRS for AFL" href="{{ route('cash-return.afl-crs.create') }}"
            navigate />
        {{-- <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover /> --}}
        <x-ts-dial.items icon="plus" label="New CRS for Cash Advances" href="{{ route('cash-return.employee-advances.create') }}"
            navigate />
    </x-ts-dial>

</div>
