<?php

use Livewire\Component;
use App\Models\Restaurant\ProductionOrder;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use Livewire\Attributes\Computed;



new class extends Component
{
    use WithPagination;
    use Interactions;

    public array $productionOrderHeader=
    [
        ['index' => 'status' , 'label' => 'status'],
        ['index' => 'reference' , 'label' => 'reference'],
        ['index' => 'created_at' , 'label' => 'order date'],
        ['index' => 'prepared_by' , 'label' => 'prepared by'],
        ['index' => 'action' , 'label' => 'action'],
    ];
#[Computed]
public function productionOrderRows()
{
    return ProductionOrder::query()
    ->with('preparedBy')
    ->orderBy('created_at')
    ->where('branch_id', auth()->user()->branch_id)
    ->paginate(10, pageName: 'name_page')
    ->withQueryString();

}

};
?>

<div class="space-y-5">
      <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
        ['label' => 'Restaurant', 'link' => route('restaurant.production-order.summary'), 'icon' => 'cog'],
        ['label' => 'Production Order', 'icon' => 'list-bullet'],
    ]" />
    <section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-200 dark:border-slate-800 px-5 py-5 lg:flex-row lg:items-center lg:justify-start">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-white">Production Order</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create and manage production orders.</p>
            </div>
        </header>

        <div class="p-3">
            <x-ts-table :headers="$productionOrderHeader" :rows="$this->productionOrderRows" paginate filter striped compact expandable>
                @interact('column_status', $row)
                    @if($row->status == 'DRAFT')
                        <x-ts-badge color="gray" text="{{$row->status}}"/>
                    @elseif($row->status == 'PENDING')
                        <x-ts-badge color="yellow" text="{{$row->status}}"/>
                    @elseif($row->status == 'COMPLETED')
                        <x-ts-badge color="green" text="{{$row->status}}"/>
                    @else
                        <x-ts-badge color="rose" text="{{$row->status}}"/>
                    @endif
                @endinteract
                @interact('column_created_at', $row)
                     {{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}
                @endinteract
                @interact('column_prepared_by', $row)
                    <div class="flex items-center gap-2">
                        <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                    </div>
                @endinteract
                @interact('column_action', $row)
                     <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        @if ($row->status == 'DRAFT')
                            <a href="{{ route('restaurant.production-order.edit', ['id' => $row->id]) }}" wire:navigate>
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{ route('restaurant.production-order.view', ['id' => $row->id]) }}" wire:navigate>
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                        </a>
                    </x-ts-dropdown>
                @endinteract
            </x-ts-table>
        </div>
    </section>
    
     <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="New Production " href="{{ route('restaurant.production-order.create')}}" navigate />
    </x-ts-dial>
</div>