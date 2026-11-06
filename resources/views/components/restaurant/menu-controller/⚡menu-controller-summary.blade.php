<?php

use App\Models\Business\RecipeController;
use App\Services\Restaurant\MenuControllerService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use WithPagination;

    public ?string $status = null;
    public ?array $dates = null;
    public ?string $search = null;
    public int $quantity = 10;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public function with(): array
    {
        return [
            'headers' => [
                ['index' => 'is_available', 'label' => 'Status'],
                ['index' => 'control_name', 'label' => 'Menu Schedule'],
                ['index' => 'start_date', 'label' => 'Effective Date'],
                ['index' => 'end_date', 'label' => 'End Date'],
                ['index' => 'created_by', 'label' => 'Prepared By', 'sortable' => false],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'rows' => RecipeController::query()
                ->with('preparedBy')
                ->where('branch_id', $this->branchId())
                ->when(in_array($this->status, ['active', 'inactive'], true), function (Builder $query): void {
                    $query->where('is_available', $this->status === 'active');
                })
                ->when(is_array($this->dates) && count($this->dates) === 2, function (Builder $query): void {
                    $query->whereBetween('start_date', $this->dates);
                })
                ->when($this->search, function (Builder $query): void {
                    $query->where('control_name', 'like', '%' . $this->search . '%');
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }

    public function setAvailability(int $id, bool $isAvailable, MenuControllerService $service): void
    {
        $service->setAvailability($id, $this->branchId(), $isAvailable);

        $this->toast()->success(
            'Updated',
            $isAvailable ? 'Menu schedule activated.' : 'Menu schedule deactivated.',
        )->send();
    }
    public function removeSchedule(int $id, MenuControllerService $service): void
    {
        $service->removeSchedule($id, $this->branchId());

        $this->toast()->success(
            'Deleted',
            'Menu schedule deleted successfully.',
        )->send();
    }

    private function branchId(): int
    {
        $branchId = auth()->user()?->branch_id;
        abort_unless($branchId, 403);

        return (int) $branchId;
    }
};
?>

<div class="space-y-5">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
        ['label' => 'Restaurant', 'link' => route('restaurant.menu-controller-summary'), 'icon' => 'cog'],
        ['label' => 'Menu Schedules', 'icon' => 'list-bullet'],
    ]" />

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Recipe Schedules</h1>
                <p class="mt-1 text-sm text-slate-500">Create and manage recipe schedule dates, days and availability.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-ts-input wire:model.live.debounce.300ms="search" placeholder="Search schedules" />
                <x-ts-select.native
                    wire:model.live="status"
                    placeholder="All statuses"
                    :options="[
                        ['name' => 'All statuses', 'id' => null],
                        ['name' => 'Active', 'id' => 'active'],
                        ['name' => 'Inactive', 'id' => 'inactive'],
                    ]"
                    select="label:name|value:id"
                />
                <x-ts-date wire:model.live="dates" range placeholder="Effective date range" />
                <x-ts-button text="New Schedule" icon="plus" color="green" :href="route('restaurant.menu-controller-create')" />
            </div>
        </header>

        <div class="p-4 sm:p-5">
            <x-ts-table :$headers :$rows :sort paginate loading striped filter expandable>
                @interact('column_is_available', $row)
                    @if($row->is_available)
                        <x-ts-badge text="ACTIVE" color="green" />
                    @else
                        <x-ts-badge text="INACTIVE" color="rose" />
                    @endif
                @endinteract
                @interact('column_start_date', $row)
                    {{ $row->start_date ? \Illuminate\Support\Carbon::parse($row->start_date)->format('M d, Y') : '—' }}
                @endinteract
                @interact('column_end_date', $row)
                    {{ $row->end_date ? \Illuminate\Support\Carbon::parse($row->end_date)->format('M d, Y') : 'No end date' }}
                @endinteract
                @interact('column_created_by', $row)
                    {{ $row->preparedBy?->full_name ?? 'Unknown' }}
                @endinteract
                @interact('column_action', $row)
                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                    <x-ts-dropdown.items text="View" icon="eye" :href="route('restaurant.menu-controller-view', ['id' => $row->id])" />
                    <x-ts-dropdown.items text="Edit" icon="pencil-square" :href="route('restaurant.menu-controller-edit', ['id' => $row->id])" />
                    <x-ts-dropdown.items
                        :text="$row->is_available ? 'Deactivate' : 'Activate'"
                        :color="$row->is_available ? 'rose' : 'green'"
                        icon="power"
                        wire:click="setAvailability({{ $row->id }}, {{ $row->is_available ? 'false' : 'true' }})"
                    />
                    <x-ts-dropdown.items text="Delete" icon="trash" wire:click="removeSchedule({{ $row->id }})" />
                </x-ts-dropdown>
                   
                @endinteract
                @interact('sub_table', $row)
                        @php
                            $subHeaders = [
                                ['index' => 'menu_image', 'label' => 'recipe Image'],
                                ['index' => 'menu_code', 'label' => 'Menu Code'],
                                ['index' => 'menu_type', 'label' => 'Menu Type'],
                                ['index' => 'recipe_type', 'label' => 'Recipe Type'],
                                ['index' => 'menu_description', 'label' => 'Description'],
                            ];
                            $subRows = $row->Branchrecipes->map(function ($branchRecipe) {
                                return [
                                    'menu_image' => $branchRecipe->recipe->menu_image,
                                    'menu_code' => $branchRecipe->recipe->menu_code,
                                    'menu_type' => $branchRecipe->recipe->menu_type,
                                    'menu_description' => $branchRecipe->recipe->menu_description,
                                ];
                            });
                        
                        @endphp
                           <x-ts-table :headers="$subHeaders" :rows="$subRows" striped conmpact>
                                @interact('column_menu_image', $subRow)
                                    @if($subRow['menu_image'])
                                        <img src="{{ asset('storage/' . $subRow['menu_image']) }}" alt="Recipe Image" class="h-16 w-16 rounded object-cover" />
                                    @else
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-xs font-bold text-emerald-700">
                                            {{ strtoupper(substr($row->menu_name, 0, 2)) }}
                                        </div>
                                    @endif
                                @endinteract
                            </x-ts-table>
                @endinteract
            </x-ts-table>
        </div>
    </section>
</div>
