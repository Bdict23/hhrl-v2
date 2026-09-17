<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use App\Models\DataManagement\Recipe;
use App\Models\DataManagement\Category;
use App\Models\DataManagement\Price;
use App\Models\Business\Employee;
use App\Services\DataManagement\UnitConversionService;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;
    use Interactions;

    public int $recipeId;
    public ?Recipe $recipeModel = null;

    // Form fields
    public $menu_name;
    public $menu_code;
    public $category_id;
    public $recipe_type = 'Ala carte';
    public $serving_size = 1;
    public $selling_price = 0.00;
    public $menu_description;
    public $menu_image;
    public $existing_image;
    public $reviewer_id;
    public $approver_id;
    public $status;
    public bool $isApprovedLocked = false;

    // Ingredients list
    public array $ingredients = [];

    // Modal state for multi-item selection
    public bool $itemPickerModal = false;
    public string $itemSearch = '';
    public $itemCategoryFilter = null;
    public array $selectedItemIds = [];

    protected function rules(): array
    {
        return [
            'menu_name'     => 'required|string|max:255',
            'menu_code'     => 'required|string|max:50|unique:menus,menu_code,' . $this->recipeId,
            'category_id'   => 'required|exists:categories,id',
            'recipe_type'   => 'required|in:Ala carte,Banquet',
            'serving_size'  => 'required|numeric|min:0.1',
            'selling_price' => 'nullable|numeric|min:0',
            'reviewer_id'   => 'nullable|exists:employees,id',
            'approver_id'   => 'nullable|exists:employees,id',
            'menu_image'    => 'nullable|image|max:2048',
            'ingredients'   => 'required|array|min:1',
            'ingredients.*.item_id' => 'required|exists:items,id',
            'ingredients.*.qty'     => 'required|numeric|min:0.0001',
            'ingredients.*.uom_id'  => 'required',
        ];
    }

    public function mount(int $id, UnitConversionService $conversionService): void
    {
        $this->recipeId = $id;
        $recipe = Recipe::with(['ingredients.item.unit', 'ingredients.item.cost', 'ingredients.unit', 'rate'])->findOrFail($id);
        $this->recipeModel = $recipe;

        $this->menu_name        = $recipe->menu_name;
        $this->menu_code        = $recipe->menu_code;
        $this->category_id      = $recipe->category_id;
        $this->recipe_type      = $recipe->recipe_type ?: 'Ala carte';
        $this->serving_size     = (float) ($recipe->serving_size > 0 ? $recipe->serving_size : 1.0);
        $this->selling_price    = (float) ($recipe->rate?->amount ?? 0.0);
        $this->menu_description = $recipe->menu_description;
        $this->existing_image   = $recipe->menu_image;
        $this->reviewer_id      = $recipe->reviewer_id;
        $this->approver_id      = $recipe->approver_id;
        $this->status           = $recipe->status;

        // Restriction: if approved (AVAILABLE), lock editing unless user explicitly unlocks or requests revision
        if ($recipe->status === 'AVAILABLE') {
            $this->isApprovedLocked = true;
        }

        // Hydrate ingredients
        foreach ($recipe->ingredients as $ingredient) {
            $item = $ingredient->item;
            if (!$item) continue;

            $compatibleUnits = $conversionService->getCompatibleUnitsForItem($item)->toArray();
            
            // Match the existing uom from compatible units
            $selectedUnit = null;
            $ingUom = $ingredient->unit ?: $item->unit;
            $ingUomId = $ingredient->uom_id ?: ($ingUom ? $ingUom->id : null);

            if ($ingUomId) {
                foreach ($compatibleUnits as $u) {
                    if ((string)$u['id'] === (string)$ingUomId) {
                        $selectedUnit = $u;
                        break;
                    }
                }
            }

            if (!$selectedUnit && $ingUom) {
                $ingSym = $conversionService->normalizeUnit($ingUom->measure_symbol ?: $ingUom->unit_symbol ?: '');
                foreach ($compatibleUnits as $u) {
                    if ($conversionService->normalizeUnit($u['symbol']) === $ingSym || $conversionService->normalizeUnit($u['key']) === $ingSym) {
                        $selectedUnit = $u;
                        break;
                    }
                }
            }

            if (!$selectedUnit && !empty($compatibleUnits)) {
                $selectedUnit = $compatibleUnits[0];
            }

            $defaultUomId = $selectedUnit['id'] ?? null;
            $defaultSymbol = $selectedUnit['symbol'] ?? '';

            $calc = $conversionService->calculateIngredientCost($item, (float)$ingredient->qty, $defaultUomId);

            $this->ingredients[] = [
                'item_id'          => $item->id,
                'item_code'        => $item->item_code,
                'item_description' => $item->item_description,
                'qty'              => (float) $ingredient->qty,
                'uom_id'           => $defaultUomId,
                'uom_symbol'       => $calc['target_unit_symbol'] ?: $defaultSymbol,
                'base_symbol'      => $calc['base_symbol'],
                'package_value'    => $calc['base_package_quantity'],
                'package_cost'     => (float) ($item->cost?->amount ?? 0.0),
                'available_units'  => $compatibleUnits,
                'qty_in_base'      => $calc['qty_converted_to_base'],
                'line_cost'        => $calc['cost'],
            ];
        }
    }

    public function unlockForRevision(): void
    {
        $this->isApprovedLocked = false;
        $this->status = 'FOR REVIEW';
        $this->toast()->info('Recipe Unlocked', 'Recipe is now in revision mode. Submitting will require re-approval.')->send();
    }

    public function updatingItemSearch(): void
    {
        $this->resetPage('pickerPage');
    }

    public function updatingItemCategoryFilter(): void
    {
        $this->resetPage('pickerPage');
    }

    public function openItemPicker(): void
    {
        if ($this->isApprovedLocked) {
            $this->banner()->warning('This recipe is approved and locked for editing. Unlock it to make changes.')->send();
            return;
        }
        $this->selectedItemIds = array_column($this->ingredients, 'item_id');
        $this->resetPage('pickerPage');
        $this->itemPickerModal = true;
    }

    public function addSelectedItems(UnitConversionService $conversionService): void
    {
        $existingIds = array_column($this->ingredients, 'item_id');
        $toAddIds = array_diff($this->selectedItemIds, $existingIds);

        if (!empty($toAddIds)) {
            $items = Item::with(['unit', 'cost'])
                ->whereIn('id', $toAddIds)
                ->get();

            foreach ($items as $item) {
                $compatibleUnits = $conversionService->getCompatibleUnitsForItem($item)->toArray();

                // Determine default unit from compatible units
                $selectedUnit = null;
                if ($item->unit) {
                    $itemSym = $conversionService->normalizeUnit($item->unit->measure_symbol ?: $item->unit->unit_symbol ?: '');
                    foreach ($compatibleUnits as $u) {
                        if ((string)$u['id'] === (string)$item->unit_id || $conversionService->normalizeUnit($u['symbol']) === $itemSym || $conversionService->normalizeUnit($u['key']) === $itemSym) {
                            $selectedUnit = $u;
                            break;
                        }
                    }
                }
                if (!$selectedUnit && !empty($compatibleUnits)) {
                    $selectedUnit = $compatibleUnits[0];
                }

                $defaultUomId = $selectedUnit['id'] ?? null;
                $defaultSymbol = $selectedUnit['symbol'] ?? '';

                $calc = $conversionService->calculateIngredientCost($item, 1, $defaultUomId);

                $this->ingredients[] = [
                    'item_id'          => $item->id,
                    'item_code'        => $item->item_code,
                    'item_description' => $item->item_description,
                    'qty'              => 1,
                    'uom_id'           => $defaultUomId,
                    'uom_symbol'       => $calc['target_unit_symbol'] ?: $defaultSymbol,
                    'base_symbol'      => $calc['base_symbol'],
                    'package_value'    => $calc['base_package_quantity'],
                    'package_cost'     => (float) ($item->cost?->amount ?? 0.0),
                    'available_units'  => $compatibleUnits,
                    'qty_in_base'      => $calc['qty_converted_to_base'],
                    'line_cost'        => $calc['cost'],
                ];
            }
        }

        $this->itemPickerModal = false;
        $this->toast()->success('Ingredients Added', count($toAddIds) . ' ingredient(s) added.')->send();
    }

    public function removeIngredient(int $index): void
    {
        if ($this->isApprovedLocked) return;
        unset($this->ingredients[$index]);
        $this->ingredients = array_values($this->ingredients);
    }

    /**
     * Real-time recalculation listener for nested ingredients changes
     */
    public function updated($property, $value = null): void
    {
        if (str_starts_with($property, 'ingredients.')) {
            $parts = explode('.', $property);
            if (isset($parts[1])) {
                $this->recalculateRow((int) $parts[1]);
            }
        }
    }

    public function recalculateRow(int $index): void
    {
        if (!isset($this->ingredients[$index])) {
            return;
        }

        $conversionService = app(UnitConversionService::class);
        $item = Item::with(['unit', 'cost'])->find($this->ingredients[$index]['item_id']);
        if (!$item) {
            return;
        }

        $qty = (float) ($this->ingredients[$index]['qty'] ?? 0);
        $uomId = $this->ingredients[$index]['uom_id'] ?? null;

        $calc = $conversionService->calculateIngredientCost($item, $qty, $uomId);
        $this->ingredients[$index]['qty_in_base'] = $calc['qty_converted_to_base'];
        $this->ingredients[$index]['line_cost']   = $calc['cost'];
        $this->ingredients[$index]['base_symbol'] = $calc['base_symbol'];

        $resolvedSymbol = $calc['target_unit_symbol'] ?? '';
        foreach ($this->ingredients[$index]['available_units'] as $u) {
            if ((string)$u['id'] === (string)$uomId) {
                $resolvedSymbol = $u['symbol'];
                break;
            }
        }
        $this->ingredients[$index]['uom_symbol'] = $resolvedSymbol;
    }

    public function getTotalCostProperty(): float
    {
        return array_sum(array_column($this->ingredients, 'line_cost'));
    }

    public function getCostPerServingProperty(): float
    {
        $servings = (float) ($this->serving_size > 0 ? $this->serving_size : 1.0);
        return round($this->totalCost / $servings, 2);
    }

    public function getFoodCostPercentProperty(): float
    {
        $price = (float) $this->selling_price;
        if ($price <= 0) return 0.0;
        return round(($this->costPerServing / $price) * 100, 1);
    }

    public function getGrossMarginProperty(): float
    {
        $price = (float) $this->selling_price;
        return round($price - $this->costPerServing, 2);
    }

    public function updateRecipe(?string $targetStatus = null): void
    {
        if ($this->isApprovedLocked) {
            $this->banner()->warning('Cannot save: This recipe is locked. Unlock it for revision first.')->send();
            return;
        }

        $this->validate();

        try {
            DB::transaction(function () use ($targetStatus) {
                $recipe = Recipe::findOrFail($this->recipeId);

                $imagePath = $this->existing_image;
                if ($this->menu_image) {
                    $imagePath = $this->menu_image->store('recipes', 'public');
                }

                $newStatus = $targetStatus ?: ($this->status === 'AVAILABLE' ? 'AVAILABLE' : 'PENDING');

                // 1. Update Recipe
                $recipe->update([
                    'menu_code'         => $this->menu_code,
                    'menu_name'         => $this->menu_name,
                    'category_id'       => $this->category_id,
                    'recipe_type'       => $this->recipe_type,
                    'menu_type'         => $this->recipe_type === 'Banquet' ? 'Banquet' : 'Ala Carte',
                    'menu_description'  => $this->menu_description,
                    'menu_image'        => $imagePath,
                    'serving_size'      => $this->serving_size,
                    'total_cost'        => $this->totalCost,
                    'status'            => $newStatus,
                    'reviewer_id'       => $this->reviewer_id,
                    'approver_id'       => $this->approver_id,
                ]);

                // 2. Sync Ingredients (delete existing and re-insert)
                $recipe->ingredients()->delete();
                foreach ($this->ingredients as $row) {
                    $item = Item::with('cost')->find($row['item_id']);
                    $recipe->ingredients()->create([
                        'item_id'        => $row['item_id'],
                        'qty'            => $row['qty'],
                        'cost'           => $row['line_cost'],
                        'uom_id'         => $row['uom_id'],
                        'price_level_id' => $item?->cost?->id,
                    ]);
                }

                // 3. Update Selling Price
                if ($this->selling_price > 0) {
                    Price::updateOrCreate(
                        [
                            'menu_id'    => $recipe->id,
                            'price_type' => 'RATE',
                        ],
                        [
                            'amount'     => $this->selling_price,
                            'company_id' => $recipe->company_id,
                            'branch_id'  => auth()->user()?->branch_id,
                        ]
                    );
                }

                // 4. Save Updated Approved Recipe Cost into price_levels table
                Price::create([
                    'menu_id'    => $recipe->id,
                    'price_type' => 'COST',
                    'amount'     => $this->totalCost,
                    'company_id' => $recipe->company_id,
                    'branch_id'  => auth()->user()?->branch_id,
                    'created_by' => auth()->user()?->emp_id ?: auth()->id(),
                ]);
            });

            $this->toast()->success('Recipe Updated', "Recipe '{$this->menu_name}' updated successfully!")->send();
            $this->redirect(route('restaurant.recipe-summary'), navigate: true);

        } catch (\Throwable $e) {
            $this->banner()->error('Error updating recipe: ' . $e->getMessage())->send();
        }
    }

    public function with(): array
    {
        $companyId = auth()->user()?->branch?->company_id;

        return [
            'categories' => Category::where('category_type', 'MENU')
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->get(),
            'employees'  => Employee::where('status', 'ACTIVE')->get(),
            'pickerItems'=> Item::with(['unit', 'cost', 'category'])
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->where('item_status', 'ACTIVE')
                ->when($this->itemSearch, fn($q) => $q->where(function ($sub) {
                    $sub->where('item_description', 'like', "%{$this->itemSearch}%")
                        ->orWhere('item_code', 'like', "%{$this->itemSearch}%");
                }))
                ->when($this->itemCategoryFilter, fn($q) => $q->where('category_id', $this->itemCategoryFilter))
                ->paginate(10, pageName: 'pickerPage'),
        ];
    }
};
?>

<div class="mb-12 space-y-6">
    <x-ts-banner wire close />

    <!-- Navigation Breadcrumbs -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                ['label' => 'Restaurant', 'link' => route('restaurant.recipe-summary'), 'icon' => 'building-storefront'],
                ['label' => 'Recipes', 'link' => route('restaurant.recipe-summary'), 'icon' => 'book-open'],
                ['label' => 'Edit: ' . $menu_name, 'icon' => 'pencil-square'],
            ]" />
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Edit Recipe: {{ $menu_name }}</h1>
                <x-ts-badge :text="$status" :color="$status === 'AVAILABLE' ? 'green' : ($status === 'FOR APPROVAL' ? 'purple' : 'gray')" />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-ts-button href="{{ route('restaurant.recipe-summary') }}" color="secondary" light navigate>
                Back to Summary
            </x-ts-button>

            @if($isApprovedLocked)
                <x-ts-button wire:click="unlockForRevision" icon="lock-open" color="amber">
                    Unlock for Revision
                </x-ts-button>
            @else
                <x-ts-button wire:click="updateRecipe('PENDING')" color="slate" light>
                    Save Changes
                </x-ts-button>
                <x-ts-button wire:click="updateRecipe('FOR APPROVAL')" color="emerald">
                    Submit for Approval
                </x-ts-button>
            @endif
        </div>
    </div>

    @if($isApprovedLocked)
        <div class="bg-amber-50 dark:bg-amber-950/40 border-l-4 border-amber-500 p-4 rounded-r-lg flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-ts-icon name="lock-closed" class="w-6 h-6 text-amber-600" />
                <div>
                    <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-200">Recipe is Approved & Locked</h4>
                    <p class="text-xs text-amber-700 dark:text-amber-300">To maintain inventory and costing integrity, this recipe is locked. Click "Unlock for Revision" to edit.</p>
                </div>
            </div>
            <x-ts-button wire:click="unlockForRevision" color="amber" sm>Unlock</x-ts-button>
        </div>
    @endif

    <!-- Main Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left Section: Recipe Master Info -->
        <div class="lg:col-span-2 space-y-6">
            <x-ts-card header="Recipe Details">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-ts-input label="Recipe Code *" wire:model="menu_code" :disabled="$isApprovedLocked" />
                    <x-ts-input label="Recipe Name *" wire:model="menu_name" :disabled="$isApprovedLocked" />
                    
                    <div>
                        <x-ts-select.native label="Menu Category *" wire:model="category_id" :disabled="$isApprovedLocked" :options="collect($categories)->map(fn($c) => ['label' => $c->category_name, 'value' => $c->id])->prepend(['label' => 'Select Category', 'value' => ''])->toArray()" select="label:label|value:value" />
                    </div>

                    <div>
                        <x-ts-select.native label="Recipe Type *" wire:model="recipe_type" :disabled="$isApprovedLocked" :options="[
                            ['label' => 'Ala Carte', 'value' => 'Ala carte'],
                            ['label' => 'Banquet', 'value' => 'Banquet'],
                        ]" select="label:label|value:value" />
                    </div>

                    <x-ts-number label="Yield / Servings *" wire:model.live="serving_size" :disabled="$isApprovedLocked" min="0.1" step="1" />
                    <x-ts-currency label="Target Selling Price (PHP)" wire:model.live.debounce.300ms="selling_price" :disabled="$isApprovedLocked" currency="PHP" decimal/>

                    <div>
                        <x-ts-select.native label="Assign Reviewer" wire:model="reviewer_id" :disabled="$isApprovedLocked" :options="collect($employees)->map(fn($e) => ['label' => $e->full_name . ' (' . $e->position_name . ')', 'value' => $e->id])->prepend(['label' => 'Select Reviewer (Optional)', 'value' => ''])->toArray()" select="label:label|value:value" />
                    </div>

                    <div>
                        <x-ts-select.native label="Assign Approver" wire:model="approver_id" :disabled="$isApprovedLocked" :options="collect($employees)->map(fn($e) => ['label' => $e->full_name . ' (' . $e->position_name . ')', 'value' => $e->id])->prepend(['label' => 'Select Approver (Optional)', 'value' => ''])->toArray()" select="label:label|value:value" />
                    </div>

                    <div class="md:col-span-2">
                        @if($existing_image)
                            <div class="mb-2 flex items-center gap-3">
                                <img src="{{ asset('storage/'.$existing_image) }}" class="w-16 h-16 object-cover rounded-lg border" />
                                <span class="text-xs text-gray-500">Current dish photo</span>
                            </div>
                        @endif
                        <x-ts-upload label="Replace Recipe Image" wire:model="menu_image" :disabled="$isApprovedLocked" hint="Upload dish image (Max 2MB)" accept="image/*" />
                    </div>

                    <div class="md:col-span-2">
                        <x-ts-textarea label="Preparation / Cooking Notes" wire:model="menu_description" :disabled="$isApprovedLocked" rows="3" />
                    </div>
                </div>
            </x-ts-card>

            <!-- Ingredients Table Card -->
            <x-ts-card>
                <x-slot:header>
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Recipe Ingredients</h2>
                            <p class="text-xs text-gray-500">Formulated ingredient portions with real-time UOM unit cost conversions.</p>
                        </div>
                        @if(!$isApprovedLocked)
                            <x-ts-button wire:click="openItemPicker" icon="plus" color="emerald" sm>
                                Add Ingredients
                            </x-ts-button>
                        @endif
                    </div>
                </x-slot:header>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs font-semibold text-gray-600 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                            <tr>
                                <th class="py-3 px-3">Item</th>
                                <th class="py-3 px-2">Package Reference</th>
                                <th class="py-3 px-2 w-32">Portion Qty</th>
                                <th class="py-3 px-2 w-44">Recipe Unit</th>
                                <th class="py-3 px-3 min-w-44">Base Eqv. (Fixed Unit)</th>
                                <th class="py-3 px-3 text-right">Line Cost</th>
                                <th class="py-3 px-2 text-center w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($ingredients as $index => $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors" wire:key="edit-ing-{{ $row['item_id'] }}">
                                    <!-- Item details -->
                                    <td class="py-3 px-3">
                                        <span class="font-medium text-gray-900 dark:text-gray-100 block">{{ $row['item_description'] }}</span>
                                        <span class="font-mono text-xs text-gray-400">{{ $row['item_code'] }}</span>
                                    </td>

                                    <!-- Packaging & Base Cost info -->
                                    <td class="py-3 px-2 text-xs text-gray-500">
                                        <span class="block text-gray-700 dark:text-gray-300 font-medium">
                                            {{ $row['package_value'] }} {{ $row['base_symbol'] }}
                                        </span>
                                        <span>@ ₱ {{ number_format($row['package_cost'], 2) }}</span>
                                    </td>

                                    <!-- Quantity Input -->
                                    <td class="py-3 px-2">
                                        <input type="number" step="any" min="0" 
                                               @disabled($isApprovedLocked)
                                               wire:model.live.debounce.150ms="ingredients.{{ $index }}.qty" 
                                               class="w-full text-right text-sm font-semibold rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 disabled:bg-gray-100 disabled:opacity-75 focus:ring-emerald-500 focus:border-emerald-500 py-1.5 px-2.5 shadow-sm" 
                                               placeholder="0.00" />
                                    </td>

                                    <!-- Unit Dropdown -->
                                    <td class="py-3 px-2">
                                        <select wire:model.live="ingredients.{{ $index }}.uom_id" 
                                                @disabled($isApprovedLocked)
                                                class="w-full text-xs font-medium rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 disabled:bg-gray-100 disabled:opacity-75 py-1.5 px-2 focus:ring-emerald-500 focus:border-emerald-500 shadow-sm">
                                            @foreach($row['available_units'] as $unit)
                                                <option value="{{ $unit['id'] }}">{{ $unit['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    <!-- Base Equivalent with Fixed Base Symbol -->
                                    <td class="py-3 px-3">
                                        <div class="flex items-center">
                                            <span class="font-bold text-gray-900 dark:text-gray-100 text-sm font-mono">
                                                {{ number_format((float)$row['qty_in_base'], 4) }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 ml-1.5 shadow-xs">
                                                {{ $row['base_symbol'] }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-400 mt-0.5 font-medium">
                                            {{ (float)$row['qty'] }} {{ $row['uom_symbol'] ?? '' }} &rarr; {{ number_format((float)$row['qty_in_base'], 4) }} {{ $row['base_symbol'] }}
                                        </div>
                                    </td>

                                    <!-- Computed Line Cost -->
                                    <td class="py-3 px-3 text-right">
                                        <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                            ₱ {{ number_format((float)$row['line_cost'], 2) }}
                                        </span>
                                    </td>

                                    <!-- Delete action -->
                                    <td class="py-3 px-2 text-center">
                                        @if(!$isApprovedLocked)
                                            <button type="button" wire:click="removeIngredient({{ $index }})" class="text-rose-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors" title="Remove ingredient">
                                                <x-ts-icon name="trash" class="w-4 h-4" />
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ts-card>
        </div>

        <!-- Right Section: Financial Summary Dashboard -->
        <div class="space-y-6">
            <x-ts-card header="Financial Cost Breakdown">
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-sm text-gray-500">Total Batch Cost:</span>
                        <span class="text-xl font-bold text-gray-900 dark:text-gray-100">
                            ₱ {{ number_format($this->totalCost, 2) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                        <div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 block">Cost Per Serving:</span>
                            <span class="text-xs text-gray-400">Batch Cost / {{ number_format($this->serving_size, 0) }} servings</span>
                        </div>
                        <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                            ₱ {{ number_format($this->costPerServing, 2) }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center pb-3 border-b border-gray-100 dark:border-gray-800">
                        <span class="text-sm text-gray-500">Selling Price:</span>
                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                            ₱ {{ number_format((float)$this->selling_price, 2) }}
                        </span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800/60 p-3.5 rounded-xl space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Food Cost %:</span>
                            @if($this->foodCostPercent > 0)
                                @if($this->foodCostPercent <= 35)
                                    <x-ts-badge text="{{ $this->foodCostPercent }}% (Optimal)" color="green" sm />
                                @elseif($this->foodCostPercent <= 45)
                                    <x-ts-badge text="{{ $this->foodCostPercent }}% (Moderate)" color="amber" sm />
                                @else
                                    <x-ts-badge text="{{ $this->foodCostPercent }}% (High Cost)" color="rose" sm />
                                @endif
                            @else
                                <span class="text-xs text-gray-400">Enter selling price</span>
                            @endif
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full {{ $this->foodCostPercent <= 35 ? 'bg-emerald-500' : ($this->foodCostPercent <= 45 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ min(100, $this->foodCostPercent) }}%"></div>
                        </div>
                        <span class="text-[11px] text-gray-400 block">Industry target: 28% - 35%</span>
                    </div>

                    <div class="flex justify-between items-center pt-2">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Gross Margin:</span>
                        <span class="text-base font-bold {{ $this->grossMargin >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500' }}">
                            ₱ {{ number_format($this->grossMargin, 2) }}
                        </span>
                    </div>
                </div>

                <x-slot:footer>
                    @if(!$isApprovedLocked)
                        <div class="space-y-2">
                            <x-ts-button wire:click="updateRecipe('FOR APPROVAL')" color="emerald" class="w-full justify-center">
                                Submit for Approval
                            </x-ts-button>
                            <x-ts-button wire:click="updateRecipe('PENDING')" color="slate" light class="w-full justify-center">
                                Save as Draft
                            </x-ts-button>
                        </div>
                    @else
                        <x-ts-button wire:click="unlockForRevision" color="amber" class="w-full justify-center">
                            Unlock for Revision
                        </x-ts-button>
                    @endif
                </x-slot:footer>
            </x-ts-card>

            <!-- Dynamic Cost Formula Explainer -->
            <x-ts-card class="bg-gradient-to-br from-emerald-50/60 to-teal-50/30 dark:from-emerald-950/30 dark:to-transparent border border-emerald-100 dark:border-emerald-900/50 shadow-xs">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-emerald-100/80 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 shrink-0 mt-0.5">
                        <x-ts-icon name="light-bulb" class="w-4 h-4" />
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold text-emerald-900 dark:text-emerald-200 uppercase tracking-wider">Dynamic Costing Formula</h4>
                            <span class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 bg-emerald-100/70 dark:bg-emerald-900/40 px-2 py-0.5 rounded-full">1:1 Ratio</span>
                        </div>
                        <div class="p-2.5 bg-white/80 dark:bg-gray-900/60 rounded-md border border-emerald-200/60 dark:border-emerald-800/60 font-mono text-xs text-gray-700 dark:text-gray-300 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500 text-[11px]">Base Unit Cost:</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">Package Cost / Base Qty</span>
                            </div>
                            <div class="flex items-center justify-between pt-1 border-t border-dashed border-gray-200 dark:border-gray-800">
                                <span class="text-gray-500 text-[11px]">Line Item Cost:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">Portion Qty × Ratio × Base Unit Cost</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-emerald-700/90 dark:text-emerald-300/80">
                            Strict category scoping applied: Weight (kg/g/mg/lbs/oz), Volume (L/mL/gal/tbsp/tsp), Unit (pc/doz), Length (m/cm).
                        </p>
                    </div>
                </div>
            </x-ts-card>
        </div>
    </div>

    <!-- Multi-Item Selection Modal -->
    <x-ts-modal title="Select Ingredients" wire="itemPickerModal" size="5xl" center>
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <x-ts-input wire:model.live.debounce.300ms="itemSearch" placeholder="Search items by code or name..." icon="magnifying-glass" clearable />
                </div>
                <div>
                    <x-ts-select.native wire:model.live="itemCategoryFilter" placeholder="All Categories" :options="collect($categories)->map(fn($c) => ['label' => $c->category_name, 'value' => $c->id])->prepend(['label' => 'All Categories', 'value' => null])->toArray()" select="label:label|value:value" />
                </div>
            </div>

            <div class="max-h-96 overflow-y-auto border border-gray-100 dark:border-gray-800 rounded-lg">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 text-xs font-semibold text-gray-500 border-b border-gray-100 dark:border-gray-800">
                        <tr>
                            <th class="py-2.5 px-3 w-10">Select</th>
                            <th class="py-2.5 px-3">Item Code</th>
                            <th class="py-2.5 px-3">Description</th>
                            <th class="py-2.5 px-3">Category</th>
                            <th class="py-2.5 px-3">Package / UOM</th>
                            <th class="py-2.5 px-3 text-right">Cost (PHP)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($pickerItems as $item)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-2.5 px-3">
                                    <input type="checkbox" wire:model="selectedItemIds" value="{{ $item->id }}" class="rounded text-emerald-600 focus:ring-emerald-500 border-gray-300" />
                                </td>
                                <td class="py-2.5 px-3 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $item->item_code }}</td>
                                <td class="py-2.5 px-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->item_description }}</td>
                                <td class="py-2.5 px-3 text-xs text-gray-500">{{ $item->category?->category_name ?? 'N/A' }}</td>
                                <td class="py-2.5 px-3 text-xs text-gray-600 dark:text-gray-300">
                                    {{ $item->unit?->unit_symbol ?: 'N/A' }}
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-emerald-600">
                                    ₱ {{ number_format((float)($item->cost?->amount ?? 0), 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-xs text-gray-400">
                                    No items found matching search criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Selection counter -->
            <div class="mt-3 flex flex-col sm:flex-row items-center justify-between gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                <div class="text-xs text-gray-500">
                    <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ count($selectedItemIds) }}</span> item(s) selected across all pages.
                </div>
                <div class="w-full sm:w-auto text-xs">
                    {{ $pickerItems->links() }}
                </div>
            </div>
        </div>

        <x-slot:footer>
            <x-ts-button color="secondary" wire:click="$set('itemPickerModal', false)" light>Cancel</x-ts-button>
            <x-ts-button color="emerald" wire:click="addSelectedItems">Add Selected to Recipe</x-ts-button>
        </x-slot:footer>
    </x-ts-modal>
</div>
