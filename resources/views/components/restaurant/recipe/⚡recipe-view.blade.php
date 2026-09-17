<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\Price;
use App\Services\DataManagement\UnitConversionService;
use Carbon\Carbon;

new class extends Component
{
    use Interactions;

    public int $recipeId;
    public ?Recipe $recipe = null;

    public function mount(int $id): void
    {
        $this->recipeId = $id;
        $this->loadRecipe();
    }

    public function loadRecipe(): void
    {
        $this->recipe = Recipe::with([
            'category',
            'ingredients.item.unit',
            'ingredients.item.cost',
            'ingredients.unit',
            'rate',
            'preparer',
            'reviewer',
            'approver',
            'approvedByEmployee',
        ])->findOrFail($this->recipeId);
    }

    public function approveRecipe(): void
    {
        $empId = auth()->user()?->emp_id ?: auth()->id();

        $this->recipe->update([
            'status'        => 'AVAILABLE',
            'approver_id'   => $empId,
            'approved_by'   => $empId,
            'approved_date' => now(),
        ]);

        $this->loadRecipe();
        $this->toast()->success('Recipe Approved', "Recipe '{$this->recipe->menu_name}' has been successfully approved.")->send();
    }

    public function rejectRecipe(): void
    {
        $this->recipe->update([
            'status'        => 'REJECTED',
            'rejected_date' => now(),
        ]);

        $this->loadRecipe();
        $this->toast()->warning('Recipe Rejected', "Recipe '{$this->recipe->menu_name}' has been marked as rejected.")->send();
    }

    public function reopenForReview(): void
    {
        $this->recipe->update([
            'status' => 'FOR REVIEW',
        ]);

        $this->loadRecipe();
        $this->toast()->info('Status Changed', 'Recipe status changed to FOR REVIEW.')->send();
    }

    public function syncStandardCostToCurrent(): void
    {
        if (!$this->recipe) return;

        $conversionService = app(UnitConversionService::class);
        $metrics = $conversionService->calculateRecipeLiveMetrics($this->recipe);

        \Illuminate\Support\Facades\DB::transaction(function () use ($metrics, $conversionService) {
            // 1. Update recipe total_cost in menus
            $this->recipe->update([
                'total_cost' => $metrics['current_cost'],
            ]);

            // 2. Update each ingredient line cost in recipes table
            foreach ($this->recipe->ingredients as $ingredient) {
                $item = $ingredient->item;
                if (!$item) continue;
                $calc = $conversionService->calculateIngredientCost($item, (float)$ingredient->qty, $ingredient->uom_id);
                $ingredient->update([
                    'cost' => $calc['cost'],
                ]);
            }

            // 3. Record new synced baseline into price_levels table
            Price::create([
                'menu_id'    => $this->recipe->id,
                'price_type' => 'COST',
                'amount'     => $metrics['current_cost'],
                'company_id' => $this->recipe->company_id,
                'branch_id'  => auth()->user()?->branch_id,
                'created_by' => auth()->user()?->emp_id ?: auth()->id(),
            ]);
        });

        $this->loadRecipe();
        $this->toast()->success('Standard Cost Updated', 'Recipe baseline cost has been synchronized to current PO market prices.')->send();
    }

    public function with(): array
    {
        $conversionService = app(UnitConversionService::class);
        $metrics = $this->recipe ? $conversionService->calculateRecipeLiveMetrics($this->recipe) : null;

        return [
            'metrics'                  => $metrics,
            'ingredientsData'          => $metrics['ingredients'] ?? [],
            'approvedCost'             => $metrics['approved_cost'] ?? 0.0,
            'currentCost'              => $metrics['current_cost'] ?? 0.0,
            'varianceAmount'           => $metrics['variance_amount'] ?? 0.0,
            'variancePercent'          => $metrics['variance_percent'] ?? 0.0,
            'varianceStatus'           => $metrics['variance_status'] ?? 'STABLE',
            'servings'                 => $metrics['servings'] ?? 1.0,
            'approvedCostPerServing'   => $metrics['approved_cost_per_serving'] ?? 0.0,
            'currentCostPerServing'    => $metrics['current_cost_per_serving'] ?? 0.0,
            'sellingPrice'             => $metrics['selling_price'] ?? 0.0,
            'approvedFoodCostPercent'  => $metrics['approved_food_cost_percent'] ?? 0.0,
            'currentFoodCostPercent'   => $metrics['current_food_cost_percent'] ?? 0.0,
            'approvedGrossMargin'      => $metrics['approved_gross_margin'] ?? 0.0,
            'currentGrossMargin'       => $metrics['current_gross_margin'] ?? 0.0,
            'totalCost'                => $metrics['current_cost'] ?? 0.0, // fallback alias
            'costPerServing'           => $metrics['current_cost_per_serving'] ?? 0.0,
            'foodCostPercent'          => $metrics['current_food_cost_percent'] ?? 0.0,
            'grossMargin'              => $metrics['current_gross_margin'] ?? 0.0,
            'trajectory'               => $this->recipe ? $conversionService->getRecipeCostTrajectory($this->recipe) : ['labels' => [], 'series' => [], 'recent_changes' => []],
        ];
    }
};
?>

<div class="mb-12 space-y-6">
    <!-- Navigation Breadcrumbs -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                ['label' => 'Restaurant', 'link' => route('restaurant.recipe-summary'), 'icon' => 'building-storefront'],
                ['label' => 'Recipes', 'link' => route('restaurant.recipe-summary'), 'icon' => 'book-open'],
                ['label' => $recipe->menu_name ?? 'Recipe View', 'icon' => 'eye'],
            ]" />
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $recipe->menu_name }}</h1>
                <span class="font-mono text-xs px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-semibold">
                    {{ $recipe->menu_code }}
                </span>
                <x-ts-badge :text="$recipe->status" :color="$recipe->status === 'AVAILABLE' ? 'green' : ($recipe->status === 'FOR APPROVAL' ? 'purple' : ($recipe->status === 'REJECTED' ? 'rose' : 'amber'))" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ts-button href="{{ route('restaurant.recipe-summary') }}" color="secondary" light navigate>
                Back to List
            </x-ts-button>

            @if(abs($varianceAmount) >= 0.50)
                <x-ts-button wire:click="syncStandardCostToCurrent" icon="arrow-path" color="teal" outline>
                    Sync Standard Cost ({{ $varianceAmount > 0 ? '+' : '' }}₱{{ number_format($varianceAmount, 2) }})
                </x-ts-button>
            @endif

            @if($recipe->status !== 'AVAILABLE' || auth()->user()?->can_manage_recipes)
                <x-ts-button href="{{ route('restaurant.recipe-edit', ['id' => $recipe->id]) }}" icon="pencil-square" color="slate" light navigate>
                    Edit Recipe
                </x-ts-button>
            @endif

            @if(in_array($recipe->status, ['PENDING', 'FOR REVIEW', 'FOR APPROVAL']))
                <x-ts-button wire:click="approveRecipe" icon="check-circle" color="emerald">
                    Approve Recipe
                </x-ts-button>
                <x-ts-button wire:click="rejectRecipe" icon="x-circle" color="rose" light>
                    Reject
                </x-ts-button>
            @elseif($recipe->status === 'AVAILABLE')
                <x-ts-button wire:click="reopenForReview" icon="arrow-path" color="amber" light>
                    Reopen for Review
                </x-ts-button>
            @endif
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <x-ts-card class="border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600">
                    <x-ts-icon name="calculator" class="w-6 h-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">Live PO Cost</span>
                        @if($varianceStatus === 'INCREASED')
                            <x-ts-badge text="+₱{{ number_format($varianceAmount, 2) }} (+{{ $variancePercent }}%)" color="rose" sm />
                        @elseif($varianceStatus === 'DECREASED')
                            <x-ts-badge text="-₱{{ number_format(abs($varianceAmount), 2) }} (-{{ abs($variancePercent) }}%)" color="green" sm />
                        @else
                            <x-ts-badge text="Stable" color="emerald" light sm />
                        @endif
                    </div>
                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">₱ {{ number_format($currentCost, 2) }}</span>
                    <span class="text-[11px] text-gray-400 block mt-0.5">Approved: ₱ {{ number_format($approvedCost, 2) }}</span>
                </div>
            </div>
        </x-ts-card>

        <x-ts-card class="border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600">
                    <x-ts-icon name="cake" class="w-6 h-6" />
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">Cost / Serving</span>
                    <span class="text-xl font-bold text-blue-600 dark:text-blue-400">₱ {{ number_format($currentCostPerServing, 2) }}</span>
                    <span class="text-[11px] text-gray-400 block">Approved: ₱ {{ number_format($approvedCostPerServing, 2) }} ({{ number_format($servings, 0) }} servings)</span>
                </div>
            </div>
        </x-ts-card>

        <x-ts-card class="border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600">
                    <x-ts-icon name="currency-dollar" class="w-6 h-6" />
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">Selling Price</span>
                    <span class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        @if($sellingPrice > 0)
                            ₱ {{ number_format($sellingPrice, 2) }}
                        @else
                            <span class="text-sm text-gray-400">Not Set</span>
                        @endif
                    </span>
                    @if($sellingPrice > 0)
                        <span class="text-[11px] {{ $currentGrossMargin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }} block">
                            Margin: ₱ {{ number_format($currentGrossMargin, 2) }}
                        </span>
                    @endif
                </div>
            </div>
        </x-ts-card>

        <x-ts-card class="border border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600">
                    <x-ts-icon name="chart-pie" class="w-6 h-6" />
                </div>
                <div>
                    <span class="text-xs font-medium text-gray-500 block uppercase tracking-wider">Food Cost %</span>
                    <div class="flex items-center gap-2">
                        <span class="text-xl font-bold {{ $currentFoodCostPercent <= 35 ? 'text-emerald-600' : ($currentFoodCostPercent <= 45 ? 'text-amber-600' : 'text-rose-500') }}">
                            {{ $currentFoodCostPercent }}%
                        </span>
                        @if($approvedFoodCostPercent > 0 && abs($currentFoodCostPercent - $approvedFoodCostPercent) >= 0.1)
                            <span class="text-xs text-gray-400">(Base: {{ $approvedFoodCostPercent }}%)</span>
                        @endif
                    </div>
                    <span class="text-[11px] text-gray-400 block">Target: 28% - 35%</span>
                </div>
            </div>
        </x-ts-card>
    </div>

    <!-- Cost Trajectory Chart Card -->
    <x-ts-card class="border border-gray-100 dark:border-gray-800 shadow-xs">
        <x-slot:header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-ts-icon name="chart-bar" class="w-5 h-5 text-primary-500" />
                        Historical Cost Trajectory & PO Fluctuation Trend
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Compares real-time PO delivery pricing against the approved culinary baseline over time
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 font-semibold border border-emerald-200 dark:border-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Live PO: ₱ {{ number_format($currentCost, 2) }}
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 font-semibold border border-blue-200 dark:border-blue-800">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> Approved Base: ₱ {{ number_format($approvedCost, 2) }}
                    </span>
                </div>
            </div>
        </x-slot:header>

        <div class="p-2">
            <x-ts-chart :labels="$trajectory['labels']"
                        :series="$trajectory['series']"
                        line
                        grid
                        legend
                        tooltip
                        markers
                        prefix="₱ "
                        height="260" />
        </div>

        @if(!empty($trajectory['recent_changes']))
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2.5 flex items-center gap-1.5">
                    <x-ts-icon name="truck" class="w-4 h-4 text-gray-400" />
                    Recent PO Delivery Changes Impacting Recipe
                </h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-500 font-semibold border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="py-2 px-3">Date</th>
                                <th class="py-2 px-3">Ingredient</th>
                                <th class="py-2 px-3">Supplier</th>
                                <th class="py-2 px-3 text-right">PO Package Price</th>
                                <th class="py-2 px-3 text-right">Recipe Line Impact</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($trajectory['recent_changes'] as $change)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                                    <td class="py-2 px-3 font-mono text-gray-500">{{ $change['date'] }}</td>
                                    <td class="py-2 px-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $change['item_name'] }}</span>
                                        <span class="text-gray-400 text-[11px] ml-1">({{ $change['item_code'] }})</span>
                                    </td>
                                    <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $change['supplier'] }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold">₱ {{ number_format($change['new_cost'], 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">₱ {{ number_format($change['recipe_impact'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-ts-card>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Ingredients Section -->
        <div class="lg:col-span-2 space-y-6">
            <x-ts-card header="Recipe Ingredients Breakdown">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900 text-xs font-semibold text-gray-600 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-3">Ingredient</th>
                                <th class="py-3 px-3">Recipe Portion</th>
                                <th class="py-3 px-3">Base Equivalent</th>
                                <th class="py-3 px-3">Inventory Package</th>
                                <th class="py-3 px-3 text-right">Approved</th>
                                <th class="py-3 px-3 text-right">Current Live</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($ingredientsData as $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="py-3 px-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100 block">{{ $row['item']->item_description }}</span>
                                        <span class="font-mono text-xs text-gray-400">{{ $row['item']->item_code }}</span>
                                    </td>
                                    <td class="py-3 px-3 font-semibold text-gray-800 dark:text-gray-200">
                                        {{ number_format($row['qty'], 2) }} {{ $row['unit_symbol'] }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="flex items-center">
                                            <span class="font-bold text-gray-900 dark:text-gray-100 text-sm font-mono">
                                                {{ number_format($row['base_qty'], 4) }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 ml-1.5 shadow-xs">
                                                {{ $row['base_symbol'] }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-400 mt-0.5 font-medium">
                                            {{ number_format($row['qty'], 2) }} {{ $row['unit_symbol'] }} &rarr; {{ number_format($row['base_qty'], 4) }} {{ $row['base_symbol'] }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-xs text-gray-500">
                                        <span class="block text-gray-700 dark:text-gray-300">{{ $row['package_val'] }} {{ $row['base_symbol'] }}</span>
                                        <span>@ ₱{{ number_format($row['package_cost'], 2) }} (₱{{ number_format($row['unit_cost'], 2) }}/{{ $row['base_symbol'] }})</span>
                                    </td>
                                    <td class="py-3 px-3 text-right font-mono text-gray-600 dark:text-gray-400">
                                        ₱ {{ number_format($row['approved_line_cost'], 2) }}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <span class="font-bold text-gray-900 dark:text-gray-100">
                                                ₱ {{ number_format($row['line_cost'], 2) }}
                                            </span>
                                            @if($row['line_status'] === 'INCREASED')
                                                <span class="inline-flex items-center text-[10px] text-rose-600 dark:text-rose-400 font-bold bg-rose-50 dark:bg-rose-950/60 px-1.5 py-0.5 rounded border border-rose-200/60 dark:border-rose-900/60">
                                                    +₱{{ number_format($row['line_variance'], 2) }}
                                                </span>
                                            @elseif($row['line_status'] === 'DECREASED')
                                                <span class="inline-flex items-center text-[10px] text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-200/60 dark:border-emerald-900/60">
                                                    -₱{{ number_format(abs($row['line_variance']), 2) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400">No ingredients associated with this recipe.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/60 font-semibold border-t-2 border-gray-200 dark:border-gray-800">
                            <tr>
                                <td colspan="4" class="py-3 px-3 text-right text-gray-700 dark:text-gray-300">Total Recipe Batch Cost:</td>
                                <td class="py-3 px-3 text-right font-mono text-sm text-gray-600 dark:text-gray-400">₱ {{ number_format($approvedCost, 2) }}</td>
                                <td class="py-3 px-3 text-right text-base font-bold text-emerald-600 dark:text-emerald-400">
                                    ₱ {{ number_format($currentCost, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-ts-card>

            @if($recipe->menu_description)
                <x-ts-card header="Preparation & Cooking Notes">
                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">
                        {{ $recipe->menu_description }}
                    </p>
                </x-ts-card>
            @endif
        </div>

        <!-- Right Side: Details & Audit Trail -->
        <div class="space-y-6">
            <!-- Dish Image Card -->
            <x-ts-card>
                @if($recipe->menu_image)
                    <img src="{{ asset('storage/'.$recipe->menu_image) }}" alt="{{ $recipe->menu_name }}" class="w-full h-48 object-cover rounded-xl shadow-sm mb-4" />
                @else
                    <div class="w-full h-40 rounded-xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 mb-4">
                        <x-ts-icon name="photo" class="w-12 h-12 stroke-1" />
                    </div>
                @endif

                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-gray-500">Category:</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $recipe->category?->category_name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-gray-500">Recipe Type:</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $recipe->recipe_type ?: 'Ala carte' }}</span>
                    </div>
                    <div class="flex justify-between py-1.5 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-gray-500">Yield:</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ number_format($servings, 0) }} portion(s)</span>
                    </div>
                </div>
            </x-ts-card>

            <!-- Approval & Audit Trail Card -->
            <x-ts-card header="Governance & Approval Flow">
                <div class="space-y-3.5 text-xs">
                    <!-- Preparer -->
                    <div class="flex items-start gap-2.5 pb-2.5 border-b border-gray-100 dark:border-gray-800">
                        <x-ts-icon name="user" class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <span class="font-semibold text-gray-700 dark:text-gray-300 block">Created / Prepared By</span>
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $recipe->preparer?->full_name ?? ('User #' . $recipe->created_by) }}
                            </span>
                            <span class="text-[11px] text-gray-400 block">{{ Carbon::parse($recipe->created_at)->format('M d, Y h:i A') }}</span>
                        </div>
                    </div>

                    <!-- Reviewer -->
                    <div class="flex items-start gap-2.5 pb-2.5 border-b border-gray-100 dark:border-gray-800">
                        <x-ts-icon name="clipboard-document-check" class="w-4 h-4 text-gray-400 mt-0.5" />
                        <div>
                            <span class="font-semibold text-gray-700 dark:text-gray-300 block">Reviewer</span>
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $recipe->reviewer?->full_name ?? ($recipe->reviewer_id ? 'Employee #'.$recipe->reviewer_id : 'Not assigned') }}
                            </span>
                            @if($recipe->reviewed_date)
                                <span class="text-[11px] text-gray-400 block">{{ Carbon::parse($recipe->reviewed_date)->format('M d, Y h:i A') }}</span>
                            @endif
                        </div>
                    </div>

                    <!-- Approver -->
                    <div class="flex items-start gap-2.5">
                        <x-ts-icon name="check-badge" class="w-4 h-4 {{ $recipe->approved_date ? 'text-emerald-500' : 'text-gray-400' }} mt-0.5" />
                        <div>
                            <span class="font-semibold text-gray-700 dark:text-gray-300 block">Approver & Sign-off</span>
                            <span class="text-gray-600 dark:text-gray-400">
                                {{ $recipe->approvedByEmployee?->full_name ?? $recipe->approver?->full_name ?? ($recipe->approved_by ? 'Emp #'.$recipe->approved_by : 'Pending Sign-off') }}
                            </span>
                            @if($recipe->approved_date)
                                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 block">
                                    Approved on {{ Carbon::parse($recipe->approved_date)->format('M d, Y h:i A') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </x-ts-card>
        </div>
    </div>
</div>
