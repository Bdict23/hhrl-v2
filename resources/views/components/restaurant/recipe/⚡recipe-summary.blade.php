<?php

use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\Category;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use WithPagination;
    use Interactions;

    public ?string $status = null;
    public ?int $categoryId = null;
    public ?int $quantity = 10;
    public ?string $search = null;
    public array $sort = [
        'column' => 'created_at',
        'direction' => 'desc',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    public function approveRecipe(int $id): void
    {
        $recipe = Recipe::findOrFail($id);
        $empId = auth()->user()?->emp_id ?: auth()->id();

        $recipe->update([
            'status'        => 'AVAILABLE',
            'approver_id'   => $empId,
            'approved_by'   => $empId,
            'approved_date' => now(),
        ]);

        $this->toast()->success('Recipe Approved', "Recipe '{$recipe->menu_name}' is now approved and active.")->send();
    }

    public function rejectRecipe(int $id): void
    {
        $recipe = Recipe::findOrFail($id);

        $recipe->update([
            'status'        => 'REJECTED',
            'rejected_date' => now(),
        ]);

        $this->toast()->warning('Recipe Rejected', "Recipe '{$recipe->menu_name}' has been marked as rejected.")->send();
    }

    public function with(): array
    {
        $companyId = auth()->user()?->branch?->company_id;

        return [
            'headers' => [
                ['index' => 'menu_image', 'label' => 'Image', 'sortable' => false],
                ['index' => 'menu_code', 'label' => 'Code'],
                ['index' => 'menu_name', 'label' => 'Recipe Name'],
                ['index' => 'category_id', 'label' => 'Category', 'sortable' => false],
                ['index' => 'recipe_type', 'label' => 'Type'],
                ['index' => 'total_cost', 'label' => 'Cost (PHP)'],
                ['index' => 'rate', 'label' => 'Price (PHP)', 'sortable' => false],
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'created_at', 'label' => 'Created'],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'categories' => Category::where('category_type', 'MENU')
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->get(),
            'rows' => Recipe::query()
                ->with(['category', 'approver', 'rate', 'ingredients.item.cost', 'ingredients.item.unit', 'ingredients.unit'])
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->when($this->search, function (Builder $query) {
                    $query->where(function ($sub) {
                        $sub->where('menu_name', 'like', "%{$this->search}%")
                            ->orWhere('menu_code', 'like', "%{$this->search}%");
                    });
                })
                ->when($this->status, function (Builder $query) {
                    $query->where('status', $this->status);
                })
                ->when($this->categoryId, function (Builder $query) {
                    $query->where('category_id', $this->categoryId);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }
};
?>

<div>
    <div class="mb-10 space-y-4">
        <!-- Breadcrumbs and Action Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                    ['label' => 'Restaurant', 'link' => route('restaurant.recipe-summary'), 'icon' => 'building-storefront'],
                    ['label' => 'Recipe Management', 'icon' => 'book-open'],
                ]" />
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">Recipe Catalog & Costing</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Manage restaurant dishes, dynamic ingredient cost formulations, and recipe approval workflows.</p>
            </div>
            <div>
                <x-ts-button href="{{ route('restaurant.recipe-create') }}" icon="plus" color="emerald" navigate>
                    Create New Recipe
                </x-ts-button>
            </div>
        </div>

        <!-- Filter Controls Bar -->
        <x-ts-card class="border border-gray-100 dark:border-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-center">
                <div class="md:col-span-2">
                    <x-ts-input wire:model.live.debounce.300ms="search" placeholder="Search recipe name or code..." icon="magnifying-glass" clearable />
                </div>
                <div>
                    <x-ts-select.native wire:model.live="status" placeholder="All Statuses" :options="[
                        ['label' => 'All Statuses', 'value' => null],
                        ['label' => 'AVAILABLE (Approved)', 'value' => 'AVAILABLE'],
                        ['label' => 'FOR APPROVAL', 'value' => 'FOR APPROVAL'],
                        ['label' => 'FOR REVIEW', 'value' => 'FOR REVIEW'],
                        ['label' => 'PENDING (Draft)', 'value' => 'PENDING'],
                        ['label' => 'REJECTED', 'value' => 'REJECTED'],
                        ['label' => 'UNAVAILABLE', 'value' => 'UNAVAILABLE'],
                    ]" select="label:label|value:value" />
                </div>
                <div>
                    <x-ts-select.native wire:model.live="categoryId" placeholder="All Categories" :options="collect($categories)->map(fn($c) => ['label' => $c->category_name, 'value' => $c->id])->prepend(['label' => 'All Categories', 'value' => null])->toArray()" select="label:label|value:value" />
                </div>
            </div>
        </x-ts-card>

        <!-- Recipes Table -->
        <x-ts-table :$headers :$rows :$sort paginate loading striped filter compact>
            @interact('column_menu_image', $row)
                @if($row->menu_image)
                    <x-ts-avatar image="{{ asset('storage/'.$row->menu_image) }}" md square />
                @else
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                        {{ strtoupper(substr($row->menu_name, 0, 2)) }}
                    </div>
                @endif
            @endinteract

            @interact('column_menu_code', $row)
                <span class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300">
                    {{ $row->menu_code ?: 'N/A' }}
                </span>
            @endinteract

            @interact('column_menu_name', $row)
                <div>
                    <a href="{{ route('restaurant.recipe-view', ['id' => $row->id]) }}" class="font-medium text-gray-900 dark:text-gray-100 hover:text-emerald-600 transition-colors">
                        {{ $row->menu_name }}
                    </a>
                    @if($row->serving_size > 1)
                        <span class="text-xs text-gray-400 block">Yield: {{ number_format($row->serving_size, 0) }} servings</span>
                    @endif
                </div>
            @endinteract

            @interact('column_category_id', $row)
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                    {{ $row->category?->category_name ?? 'General' }}
                </span>
            @endinteract

            @interact('column_recipe_type', $row)
                <x-ts-badge :text="$row->recipe_type ?: 'Ala Carte'" color="slate" light outline sm />
            @endinteract

            @interact('column_total_cost', $row)
                @php
                    $variance = $row->getCostVariance();
                @endphp
                <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5">
                        <span class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                            ₱ {{ number_format((float)$row->total_cost, 2) }}
                        </span>
                        @if($variance['variance_status'] === 'INCREASED')
                            <x-ts-badge text="+₱{{ number_format($variance['variance_amount'], 2) }}" color="rose" sm outline title="PO deliveries increased ingredient cost" />
                        @elseif($variance['variance_status'] === 'DECREASED')
                            <x-ts-badge text="-₱{{ number_format(abs($variance['variance_amount']), 2) }}" color="green" sm outline title="Favorable PO cost savings" />
                        @endif
                    </div>
                    <div class="text-[11px] text-gray-400">
                        Current: ₱ {{ number_format($variance['current_cost'], 2) }}
                    </div>
                </div>
            @endinteract

            @interact('column_rate', $row)
                <span class="font-medium text-gray-800 dark:text-gray-200">
                    @if($row->rate)
                        ₱ {{ number_format((float)$row->rate->amount, 2) }}
                    @else
                        <span class="text-gray-400 text-xs">Unset</span>
                    @endif
                </span>
            @endinteract

            @interact('column_status', $row)
                <div class="flex items-center">
                    @if($row->status === 'AVAILABLE')
                        <x-ts-badge text="AVAILABLE" color="green" sm />
                    @elseif($row->status === 'FOR APPROVAL')
                        <x-ts-badge text="FOR APPROVAL" color="purple" sm />
                    @elseif($row->status === 'FOR REVIEW')
                        <x-ts-badge text="FOR REVIEW" color="amber" sm />
                    @elseif($row->status === 'PENDING')
                        <x-ts-badge text="PENDING" color="gray" sm />
                    @elseif($row->status === 'REJECTED')
                        <x-ts-badge text="REJECTED" color="rose" sm />
                    @else
                        <x-ts-badge :text="$row->status" color="slate" sm />
                    @endif
                </div>
            @endinteract

            @interact('column_created_at', $row)
                <span class="text-xs text-gray-500">
                    {{ Carbon::parse($row->created_at)->format('M d, Y') }}
                </span>
            @endinteract

            @interact('column_action', $row)
                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                    <a href="{{ route('restaurant.recipe-view', ['id' => $row->id]) }}">
                        <x-ts-dropdown.items text="View Recipe" icon="eye" />
                    </a>

                    @if($row->status !== 'AVAILABLE' || auth()->user()?->can_manage_recipes)
                        <a href="{{ route('restaurant.recipe-edit', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="Edit Recipe" icon="pencil-square" separator />
                        </a>
                    @endif

                    @if(in_array($row->status, ['PENDING', 'FOR REVIEW', 'FOR APPROVAL']))
                        <x-ts-dropdown.items text="Approve Recipe" icon="check-circle" color="green" separator wire:click="approveRecipe({{ $row->id }})" />
                        <x-ts-dropdown.items text="Reject Recipe" icon="x-circle" color="rose" wire:click="rejectRecipe({{ $row->id }})" />
                    @endif
                </x-ts-dropdown>
            @endinteract
        </x-ts-table>
    </div>

    <!-- Floating Action Dial -->
    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="New Recipe" href="{{ route('restaurant.recipe-create') }}" navigate />
        <x-ts-dial.items icon="arrow-path" label="Refresh" wire:click="$refresh" />
    </x-ts-dial>
</div>
