<?php

use Livewire\Component;
use App\Models\Inventory\Cardex;
use App\Models\Restaurant\ProductionOrder;
use App\Models\Restaurant\ProductionOrderDetail;
use App\Models\Restaurant\ProductionOrderRecipe as ProductionOrderMenu;
use App\Models\DataManagement\Recipe as Menu;
use App\Models\DataManagement\Item;
use App\Models\DataManagement\UnitOfMeasure;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    use Interactions;

    public int $orderId;
    public string $reference = '';
    public string $prepared_by = '';
    public string $saveAs = 'DRAFT';
    public string $orderStatus = 'DRAFT';
    public ?string $notes = null;

    public array $initialSelectedRecipes = [];
    public array $initialManualIngredients = [];

    public function mount(int $id)
    {
        $this->orderId = $id;
        $user = auth()->user();
        $branchId = $user->branch_id;

        $order = ProductionOrder::with([
            'preparedBy',
            'productionMenus.menu.category',
            'productionOrderDetails.item.unit',
            'productionOrderDetails.uom',
        ])
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        $this->reference = $order->reference;
        $this->orderStatus = $order->status;
        $this->saveAs = $order->status === 'FINAL' || $order->status === 'PENDING' ? 'FINAL' : 'DRAFT';
        $this->notes = $order->notes;
        $this->prepared_by = trim(($order->preparedBy?->name ?? '') . ' ' . ($order->preparedBy?->last_name ?? ''));

        $uoms = UnitOfMeasure::pluck('unit_symbol', 'id')->toArray();

        // Preload existing selected recipes
        $this->initialSelectedRecipes = $order->productionMenus->map(function ($pm) {
            return [
                'id' => $pm->menu_id,
                'name' => $pm->menu?->menu_name ?? 'N/A',
                'code' => $pm->menu?->menu_code ?? 'REC-' . $pm->menu_id,
                'cat' => $pm->menu?->category?->category_name ?? 'General',
                'type' => $pm->menu?->menu_type ?? 'Ala Carte',
                'qty' => (int) $pm->qty,
            ];
        })->values()->toArray();

        // Preload manual ingredients (menu_id is null)
        $this->initialManualIngredients = $order->productionOrderDetails
            ->whereNull('menu_id')
            ->map(function ($detail) use ($uoms) {
                return [
                    'item_id' => $detail->item_id,
                    'item_code' => $detail->item?->item_code ?? 'ITEM-' . $detail->item_id,
                    'item_name' => $detail->item?->item_description ?? 'N/A',
                    'uom_id' => $detail->uom_id,
                    'uom_symbol' => $uoms[$detail->uom_id] ?? ($detail->item?->unit?->unit_symbol ?? 'N/A'),
                    'qty' => (float) $detail->qty,
                ];
            })->values()->toArray();
    }

    public function with(): array
    {
        $user = auth()->user();
        $branchId = $user->branch_id;
        $companyId = $user->branch->company_id;

        $uoms = UnitOfMeasure::pluck('unit_symbol', 'id')->toArray();

        // 1. Single consolidated Cardex stock balance query for the branch
        $cardexRecords = DB::table('cardex')
            ->select('item_id')
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_in ELSE 0 END) as total_in")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_out ELSE 0 END) as total_out")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'reserved' THEN qty_out ELSE 0 END) as total_reserved")
            ->where('source_branch_id', $branchId)
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        // 2. Active items catalog (compact fields)
        $rawItems = Item::select('id', 'item_code', 'item_description', 'uom_id')
            ->where('item_status', 'ACTIVE')
            ->where('company_id', $companyId)
            ->get();

        $stockMap = [];
        $itemsList = [];

        foreach ($rawItems as $item) {
            $cardex = $cardexRecords->get($item->id);
            $totalIn = (float) ($cardex->total_in ?? 0);
            $totalOut = (float) ($cardex->total_out ?? 0);
            $totalReserved = (float) ($cardex->total_reserved ?? 0);
            $balance = $totalIn - $totalOut;
            $available = $balance - $totalReserved;

            $stockMap[$item->id] = [
                'b' => round($balance, 2),
                'a' => round($available, 2),
            ];

            $itemsList[] = [
                'id' => $item->id,
                'code' => $item->item_code ?? 'ITEM-' . $item->id,
                'name' => $item->item_description ?? 'N/A',
                'uom_id' => $item->uom_id,
                'uom' => $uoms[$item->uom_id] ?? 'N/A',
                'bal' => round($balance, 2),
                'avail' => round($available, 2),
            ];
        }

        // 3. Available recipes catalog with ingredients
        $rawRecipes = Menu::with(['category', 'ingredients.item'])
            ->where('company_id', $companyId)
            ->where('status', 'AVAILABLE')
            ->get();

        $recipesList = [];
        foreach ($rawRecipes as $recipe) {
            $ingredientsList = [];
            foreach ($recipe->ingredients as $ingredient) {
                $ingredientsList[] = [
                    'item_id' => $ingredient->item_id,
                    'item_code' => $ingredient->item?->item_code ?? 'ITEM-' . $ingredient->item_id,
                    'item_name' => $ingredient->item?->item_description ?? 'N/A',
                    'base_qty' => (float) $ingredient->qty,
                    'uom_id' => $ingredient->uom_id,
                    'uom' => $uoms[$ingredient->uom_id] ?? 'N/A',
                ];
            }

            $recipesList[] = [
                'id' => $recipe->id,
                'name' => $recipe->menu_name,
                'code' => $recipe->menu_code ?? 'REC-' . $recipe->id,
                'type' => $recipe->menu_type ?? 'Ala Carte',
                'cat' => $recipe->category?->category_name ?? 'General',
                'ings' => $ingredientsList,
            ];
        }

        return [
            'catalogRecipes' => $recipesList,
            'catalogItems' => $itemsList,
            'cardexStockMap' => $stockMap,
        ];
    }

    public function updateProductionOrder(array $payload)
    {
        $saveAs = $payload['saveAs'] ?? 'DRAFT';
        $notes = $payload['notes'] ?? null;
        $selectedRecipes = $payload['selectedRecipes'] ?? [];
        $manualIngredients = $payload['manualIngredients'] ?? [];

        if (! in_array($saveAs, ['DRAFT', 'FINAL'], true)) {
            $this->toast()->error('Validation Error', 'Invalid Save As status selected.')->send();
            return;
        }

        if (empty($selectedRecipes) || ! is_array($selectedRecipes)) {
            $this->toast()->error('Validation Error', 'At least one recipe must be added to the production order.')->send();
            return;
        }

        foreach ($selectedRecipes as $r) {
            if (empty($r['id']) || ! is_numeric($r['id']) || ($r['qty'] ?? 0) < 1) {
                $this->toast()->error('Validation Error', 'Invalid recipe selection or quantity.')->send();
                return;
            }
        }

        $user = auth()->user();
        $branchId = $user->branch_id;
        $companyId = $user->branch->company_id;

        $order = ProductionOrder::where('branch_id', $branchId)->findOrFail($this->orderId);

        $recipeIds = array_column($selectedRecipes, 'id');
        $recipes = Menu::with('ingredients.item')
            ->where('company_id', $companyId)
            ->whereIn('id', $recipeIds)
            ->get()
            ->keyBy('id');

        $overallRequired = [];
        $recipeDetailsToInsert = [];
        $manualDetailsToInsert = [];

        foreach ($selectedRecipes as $sel) {
            $rec = $recipes->get($sel['id']);
            if (! $rec) continue;

            $multiplier = (int) $sel['qty'];
            foreach ($rec->ingredients as $ing) {
                $qtyNeeded = (float) $ing->qty * $multiplier;
                if (! isset($overallRequired[$ing->item_id])) {
                    $overallRequired[$ing->item_id] = [
                        'item_id' => $ing->item_id,
                        'qty' => 0,
                        'uom_id' => $ing->uom_id,
                    ];
                }
                $overallRequired[$ing->item_id]['qty'] += $qtyNeeded;

                $recipeDetailsToInsert[] = [
                    'menu_id' => $rec->id,
                    'item_id' => $ing->item_id,
                    'qty' => $qtyNeeded,
                    'uom_id' => $ing->uom_id,
                ];
            }
        }

        foreach ($manualIngredients as $manual) {
            if (empty($manual['item_id']) || ($manual['qty'] ?? 0) <= 0) continue;

            $itemId = (int) $manual['item_id'];
            $qty = (float) $manual['qty'];
            $uomId = (int) ($manual['uom_id'] ?? 0);

            if (! isset($overallRequired[$itemId])) {
                $overallRequired[$itemId] = [
                    'item_id' => $itemId,
                    'qty' => 0,
                    'uom_id' => $uomId,
                ];
            }
            $overallRequired[$itemId]['qty'] += $qty;

            $manualDetailsToInsert[] = [
                'menu_id' => null,
                'item_id' => $itemId,
                'qty' => $qty,
                'uom_id' => $uomId,
            ];
        }

        // NOTE: We do NOT block updating if inventory is insufficient!
        // Production orders represent kitchen demand and must save so Purchase Orders can reference them and procure deficits.

        DB::beginTransaction();
        try {
            $order->status = ($saveAs === 'FINAL') ? 'PENDING' : 'DRAFT';
            $order->notes = $notes;
            $order->updated_at = Carbon::now('Asia/Manila');
            $order->save();

            // Clear old menus and details
            ProductionOrderMenu::where('production_order_id', $order->id)->delete();
            ProductionOrderDetail::where('production_order_id', $order->id)->delete();

            // Insert updated menus
            foreach ($selectedRecipes as $selected) {
                ProductionOrderMenu::create([
                    'branch_id' => $branchId,
                    'production_order_id' => $order->id,
                    'menu_id' => $selected['id'],
                    'qty' => (int) $selected['qty'],
                    'created_at' => Carbon::now('Asia/Manila'),
                    'updated_at' => Carbon::now('Asia/Manila'),
                ]);
            }

            // Insert updated details (NOTE: no branch_id on production_order_details)
            foreach (array_merge($recipeDetailsToInsert, $manualDetailsToInsert) as $detail) {
                $detail['production_order_id'] = $order->id;
                $detail['created_at'] = Carbon::now('Asia/Manila');
                $detail['updated_at'] = Carbon::now('Asia/Manila');
                ProductionOrderDetail::create($detail);
            }

            DB::commit();

            $statusText = ($saveAs === 'FINAL') ? 'updated and ready for procurement/production' : 'updated as draft';
            $this->toast()->success('Production Order Updated', "Production Order {$order->reference} {$statusText}.")->send();

            $this->redirect(route('restaurant.production-order.summary'), navigate: true);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->toast()->error('Error Updating Production Order', $e->getMessage())->send();
        }
    }
};
?>

{{-- SINGLE ROOT HTML ELEMENT FOR LIVEWIRE 4 WITH FULL DARK MODE SUPPORT --}}
<div wire:key="production-order-edit-root-{{ $orderId }}" class="min-h-screen bg-slate-50/60 dark:bg-slate-950 pb-16 font-sans text-slate-800 dark:text-slate-100">

    {{-- CLIENT-SIDE APP LOGIC (DEFINED ON WINDOW) --}}
    <script>
    if (typeof window.initProductionOrderApp !== 'function') {
        window.initProductionOrderApp = function(config) {
            return {
                catalogRecipes: config.recipes || [],
                catalogItems: config.items || [],
                cardexStockMap: config.cardex || {},
                reference: config.reference || '',
                preparedBy: config.prepared_by || '',
                saveAs: config.saveAs || 'DRAFT',
                notes: config.notes || '',

                selectedRecipes: config.initialSelectedRecipes || [],
                highlightedRecipeId: null,
                manualIngredients: config.initialManualIngredients || [],

                activeTab: 'selected',
                recipeSearch: '',
                overallSearch: '',
                overallFilter: 'all',

                showAddRecipeModal: false,
                addRecipeSearch: '',
                showAddIngredientModal: false,
                addIngredientSearch: '',
                isSaving: false,

                init() {
                    if (this.selectedRecipes.length > 0) {
                        this.highlightedRecipeId = this.selectedRecipes[0].id;
                    }
                },

                get currentRecipe() {
                    if (!this.highlightedRecipeId) return null;
                    return this.selectedRecipes.find(r => r.id === this.highlightedRecipeId) || null;
                },

                get totalBatches() {
                    return this.selectedRecipes.reduce((sum, r) => sum + (parseInt(r.qty) || 0), 0);
                },

                get filteredCatalogRecipes() {
                    const q = this.addRecipeSearch.trim().toLowerCase();
                    if (!q) return this.catalogRecipes;
                    return this.catalogRecipes.filter(r =>
                        (r.name && r.name.toLowerCase().includes(q)) ||
                        (r.code && r.code.toLowerCase().includes(q)) ||
                        (r.cat && r.cat.toLowerCase().includes(q))
                    );
                },

                get filteredCatalogItems() {
                    const q = this.addIngredientSearch.trim().toLowerCase();
                    if (!q) return this.catalogItems;
                    return this.catalogItems.filter(item =>
                        (item.name && item.name.toLowerCase().includes(q)) ||
                        (item.code && item.code.toLowerCase().includes(q))
                    );
                },

                get filteredSidebarRecipes() {
                    const q = this.recipeSearch.trim().toLowerCase();
                    if (!q) return this.selectedRecipes;
                    return this.selectedRecipes.filter(r =>
                        (r.name && r.name.toLowerCase().includes(q)) ||
                        (r.code && r.code.toLowerCase().includes(q))
                    );
                },

                get selectedRecipeIngredients() {
                    const recipe = this.currentRecipe;
                    if (!recipe) return [];
                    const fullRecipe = this.catalogRecipes.find(r => r.id === recipe.id);
                    if (!fullRecipe || !fullRecipe.ings) return [];

                    const qtyRequested = Math.max(1, parseFloat(recipe.qty) || 1);

                    return fullRecipe.ings.map(ing => {
                        const baseQty = parseFloat(ing.base_qty) || 0;
                        const reqQty = baseQty * qtyRequested;
                        const stock = this.cardexStockMap[ing.item_id] || { b: 0, a: 0 };
                        const avail = parseFloat(stock.a) || 0;
                        const bal = parseFloat(stock.b) || 0;

                        let status = 'Available';
                        let statusColor = 'emerald';
                        if (avail <= 0) {
                            status = 'Out of Stock';
                            statusColor = 'rose';
                        } else if (avail < reqQty) {
                            status = 'Insufficient';
                            statusColor = 'amber';
                        }

                        return {
                            item_id: ing.item_id,
                            item_code: ing.item_code,
                            item_name: ing.item_name,
                            base_qty: baseQty,
                            qty: reqQty,
                            uom_symbol: ing.uom,
                            balance: bal,
                            available: avail,
                            status: status,
                            statusColor: statusColor
                        };
                    });
                },

                get overallStats() {
                    const list = this.overallIngredientsListUnfiltered;
                    let ready = 0, insufficient = 0, outOfStock = 0;
                    list.forEach(i => {
                        if (i.statusKey === 'available') ready++;
                        else if (i.statusKey === 'insufficient') insufficient++;
                        else outOfStock++;
                    });
                    return {
                        total: list.length,
                        ready,
                        insufficient,
                        outOfStock,
                        isFullyReady: (list.length > 0 && insufficient === 0 && outOfStock === 0)
                    };
                },

                get overallIngredientsListUnfiltered() {
                    const map = {};

                    // 1. Consolidate recipe ingredients
                    this.selectedRecipes.forEach(sel => {
                        const fullRecipe = this.catalogRecipes.find(r => r.id === sel.id);
                        if (!fullRecipe || !fullRecipe.ings) return;
                        const recipeQty = Math.max(1, parseFloat(sel.qty) || 1);

                        fullRecipe.ings.forEach(ing => {
                            const reqQty = (parseFloat(ing.base_qty) || 0) * recipeQty;
                            if (!map[ing.item_id]) {
                                const stock = this.cardexStockMap[ing.item_id] || { b: 0, a: 0 };
                                map[ing.item_id] = {
                                    item_id: ing.item_id,
                                    item_code: ing.item_code,
                                    item_name: ing.item_name,
                                    from_recipes: 0,
                                    manual_add: 0,
                                    total_required: 0,
                                    uom_symbol: ing.uom,
                                    balance: parseFloat(stock.b) || 0,
                                    available: parseFloat(stock.a) || 0
                                };
                            }
                            map[ing.item_id].from_recipes += reqQty;
                        });
                    });

                    // 2. Consolidate manual additions
                    this.manualIngredients.forEach(manual => {
                        const manualQty = parseFloat(manual.qty) || 0;
                        if (!map[manual.item_id]) {
                            const stock = this.cardexStockMap[manual.item_id] || { b: 0, a: 0 };
                            map[manual.item_id] = {
                                item_id: manual.item_id,
                                item_code: manual.item_code,
                                item_name: manual.item_name,
                                from_recipes: 0,
                                manual_add: 0,
                                total_required: 0,
                                uom_symbol: manual.uom_symbol,
                                balance: parseFloat(stock.b) || 0,
                                available: parseFloat(stock.a) || 0
                            };
                        }
                        map[manual.item_id].manual_add += manualQty;
                    });

                    return Object.values(map).map(row => {
                        row.total_required = row.from_recipes + row.manual_add;

                        if (row.available <= 0) {
                            row.statusKey = 'out_of_stock';
                            row.status = 'Out of Stock';
                            row.statusColor = 'rose';
                        } else if (row.available < row.total_required) {
                            row.statusKey = 'insufficient';
                            row.status = 'Insufficient';
                            row.statusColor = 'amber';
                        } else {
                            row.statusKey = 'available';
                            row.status = 'Available';
                            row.statusColor = 'emerald';
                        }
                        return row;
                    });
                },

                get overallIngredientsList() {
                    const rows = this.overallIngredientsListUnfiltered;
                    const q = this.overallSearch.trim().toLowerCase();
                    return rows.filter(row => {
                        if (this.overallFilter !== 'all' && row.statusKey !== this.overallFilter) {
                            return false;
                        }
                        if (q) {
                            const matchCode = row.item_code && row.item_code.toLowerCase().includes(q);
                            const matchName = row.item_name && row.item_name.toLowerCase().includes(q);
                            if (!matchCode && !matchName) return false;
                        }
                        return true;
                    });
                },

                selectRecipe(id) {
                    this.highlightedRecipeId = id;
                    this.activeTab = 'selected';
                },

                addRecipe(recipeId) {
                    const existing = this.selectedRecipes.find(r => r.id === recipeId);
                    if (existing) {
                        existing.qty = (parseFloat(existing.qty) || 0) + 1;
                    } else {
                        const rec = this.catalogRecipes.find(r => r.id === recipeId);
                        if (rec) {
                            this.selectedRecipes.push({
                                id: rec.id,
                                name: rec.name,
                                code: rec.code,
                                cat: rec.cat,
                                type: rec.type,
                                qty: 1
                            });
                        }
                    }
                    this.highlightedRecipeId = recipeId;
                    this.showAddRecipeModal = false;
                    this.addRecipeSearch = '';
                },

                adjustRecipeQty(recipeId, delta) {
                    const recipe = this.selectedRecipes.find(r => r.id === recipeId);
                    if (recipe) {
                        const current = parseInt(recipe.qty) || 1;
                        recipe.qty = Math.max(1, current + delta);
                    }
                },

                updateRecipeQty(recipeId, newQty) {
                    const recipe = this.selectedRecipes.find(r => r.id === recipeId);
                    if (recipe) {
                        const parsed = parseInt(newQty);
                        recipe.qty = (isNaN(parsed) || parsed < 1) ? 1 : parsed;
                    }
                },

                removeRecipe(recipeId) {
                    this.selectedRecipes = this.selectedRecipes.filter(r => r.id !== recipeId);
                    if (this.highlightedRecipeId === recipeId) {
                        this.highlightedRecipeId = this.selectedRecipes.length > 0 ? this.selectedRecipes[0].id : null;
                    }
                },

                addManualIngredient(item) {
                    const existing = this.manualIngredients.find(m => m.item_id === item.id);
                    if (existing) {
                        existing.qty = (parseFloat(existing.qty) || 0) + 1;
                    } else {
                        this.manualIngredients.push({
                            item_id: item.id,
                            item_code: item.code,
                            item_name: item.name,
                            uom_id: item.uom_id,
                            uom_symbol: item.uom,
                            qty: 1
                        });
                    }
                    this.showAddIngredientModal = false;
                    this.addIngredientSearch = '';
                    this.activeTab = 'overall';
                },

                adjustManualQty(itemId, delta) {
                    const manual = this.manualIngredients.find(m => m.item_id === itemId);
                    if (manual) {
                        const next = Math.max(0, (parseFloat(manual.qty) || 0) + delta);
                        if (next <= 0) {
                            this.removeManualIngredient(itemId);
                        } else {
                            manual.qty = next;
                        }
                    }
                },

                updateManualQty(itemId, newQty) {
                    const manual = this.manualIngredients.find(m => m.item_id === itemId);
                    if (manual) {
                        const parsed = parseFloat(newQty);
                        if (isNaN(parsed) || parsed <= 0) {
                            this.removeManualIngredient(itemId);
                        } else {
                            manual.qty = parsed;
                        }
                    }
                },

                removeManualIngredient(itemId) {
                    this.manualIngredients = this.manualIngredients.filter(m => m.item_id !== itemId);
                },

                submitOrder() {
                    if (this.isSaving) return;

                    if (this.selectedRecipes.length === 0) {
                        alert('Please add at least one recipe to the production order.');
                        return;
                    }

                    this.isSaving = true;

                    const payload = {
                        saveAs: this.saveAs,
                        notes: this.notes,
                        selectedRecipes: this.selectedRecipes.map(r => ({ id: r.id, qty: r.qty })),
                        manualIngredients: this.manualIngredients.map(m => ({ item_id: m.item_id, qty: m.qty, uom_id: m.uom_id }))
                    };

                    this.$wire.updateProductionOrder(payload).finally(() => {
                        this.isSaving = false;
                    });
                }
            };
        };
    }
    </script>

    {{-- MAIN CLIENT INTERACTIVE CONTAINER --}}
    <div x-data="initProductionOrderApp({
            reference: @js($reference),
            prepared_by: @js($prepared_by),
            saveAs: @js($saveAs),
            notes: @js($notes ?? ''),
            recipes: @js($catalogRecipes),
            items: @js($catalogItems),
            cardex: @js($cardexStockMap),
            initialSelectedRecipes: @js($initialSelectedRecipes),
            initialManualIngredients: @js($initialManualIngredients)
         })"
         x-on:set-save-as.window="saveAs = $event.detail"
         x-cloak
         class="space-y-4 px-4 py-4 sm:px-6">

        {{-- BREADCRUMBS & LIVE STATUS --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                ['label' => 'Restaurant ERP', 'link' => route('restaurant.production-order.summary'), 'icon' => 'building-storefront'],
                ['label' => 'Production Orders', 'link' => route('restaurant.production-order.summary'), 'icon' => 'clipboard-document-list'],
                ['label' => 'Edit ' . $reference, 'icon' => 'pencil-square'],
            ]" />
            
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-xs transition"
                      :class="saveAs === 'FINAL' ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' : 'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700'">
                    <span class="w-2 h-2 rounded-full" :class="saveAs === 'FINAL' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
                    <span x-text="'Target Status: ' + saveAs"></span>
                </span>
            </div>
        </div>

        {{-- HIGH-IMPACT COMMAND TOOLBAR (ERP TOP CONTROLS) --}}
        <header class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm p-4 relative overflow-hidden">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-blue-600"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pl-2">
                {{-- Action Group --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    {{-- TallStackUI Save As Dropdown --}}
                    <x-ts-dropdown position="bottom-start">
                        <x-slot:action>
                            <button type="button"
                                    x-on:click="show = !show"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition active:scale-[0.98] shadow-xs cursor-pointer">
                                <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                <span x-text="'SAVE AS: ' + saveAs"></span>
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </x-slot:action>
                        <x-ts-dropdown.items icon="document-text" text="DRAFT (Editable Staged Order)" x-on:click="$dispatch('set-save-as', 'DRAFT'); show = false" />
                        <x-ts-dropdown.items icon="check-badge" text="FINAL (Ready for Production)" separator x-on:click="$dispatch('set-save-as', 'FINAL'); show = false" />
                    </x-ts-dropdown>

                    {{-- Primary Update Button --}}
                    <button type="button"
                            x-on:click="submitOrder()"
                            x-bind:disabled="isSaving"
                            class="inline-flex items-center gap-2 px-5 py-2 text-xs font-extrabold rounded-xl text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 shadow-md shadow-blue-500/25 active:scale-[0.98] transition disabled:opacity-50 cursor-pointer">
                        <svg x-show="!isSaving" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <svg x-show="isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                        <span x-show="!isSaving">Update Production Order</span>
                        <span x-show="isSaving">Updating Order...</span>
                    </button>

                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>

                    {{-- Summary Button --}}
                    <a href="{{ route('restaurant.production-order.summary') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition shadow-2xs">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        <span>Summary</span>
                    </a>

                    {{-- Print Button --}}
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Print</span>
                    </button>
                </div>

                {{-- Production Info & Notes Group --}}
                <div class="flex flex-wrap lg:flex-nowrap items-center gap-3 flex-1 max-w-2xl">
                    {{-- Reference Chip --}}
                    <div class="flex items-center gap-1.5 bg-slate-900 dark:bg-slate-950 text-white px-3.5 py-1.5 rounded-xl text-xs shrink-0 shadow-xs border border-slate-800 dark:border-slate-800">
                        <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">REF</span>
                        <span class="font-mono font-bold tracking-wide text-blue-400 dark:text-blue-300" x-text="reference"></span>
                    </div>

                    {{-- Prepared By Chip --}}
                    <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-xl text-xs shrink-0">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="font-bold text-slate-700 dark:text-slate-200 truncate max-w-[140px]" x-text="preparedBy"></span>
                    </div>

                    {{-- Notes Input --}}
                    <div class="flex-1 min-w-[200px]">
                        <input type="text"
                               x-model="notes"
                               placeholder="Kitchen production notes / instructions..."
                               class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900/40 transition shadow-2xs" />
                    </div>
                </div>
            </div>
        </header>

        {{-- EXECUTIVE KPI METRIC TILES --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            {{-- Tile 1 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold shrink-0 border border-blue-100 dark:border-blue-900/50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Selected Recipes</p>
                    <p class="text-lg font-black text-slate-900 dark:text-white leading-tight" x-text="selectedRecipes.length"></p>
                </div>
            </div>

            {{-- Tile 2 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold shrink-0 border border-indigo-100 dark:border-indigo-900/50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Batches</p>
                    <p class="text-lg font-black text-indigo-900 dark:text-indigo-300 leading-tight" x-text="totalBatches"></p>
                </div>
            </div>

            {{-- Tile 3 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold shrink-0 border border-purple-100 dark:border-purple-900/50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Ingredients</p>
                    <p class="text-lg font-black text-slate-900 dark:text-white leading-tight" x-text="overallStats.total"></p>
                </div>
            </div>

            {{-- Tile 4 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold shrink-0 border"
                     :class="{
                         'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800': overallStats.isFullyReady,
                         'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800': !overallStats.isFullyReady && overallStats.outOfStock === 0 && overallStats.insufficient > 0,
                         'bg-rose-50 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 border-rose-200 dark:border-rose-800': overallStats.outOfStock > 0 || (overallStats.total === 0)
                     }">
                    <svg x-show="overallStats.isFullyReady" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <svg x-show="!overallStats.isFullyReady" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Stock Availability</p>
                    <p class="text-xs font-black leading-tight"
                       :class="{
                           'text-emerald-700 dark:text-emerald-400': overallStats.isFullyReady,
                           'text-amber-700 dark:text-amber-400': !overallStats.isFullyReady && overallStats.outOfStock === 0 && overallStats.insufficient > 0,
                           'text-rose-700 dark:text-rose-400': overallStats.outOfStock > 0 || overallStats.total === 0
                       }">
                        <template x-if="overallStats.total === 0"><span>No Items</span></template>
                        <template x-if="overallStats.total > 0 && overallStats.isFullyReady"><span>100% In Stock</span></template>
                        <template x-if="overallStats.total > 0 && !overallStats.isFullyReady">
                            <span x-text="overallStats.outOfStock + ' Out / ' + overallStats.insufficient + ' Low'"></span>
                        </template>
                    </p>
                </div>
            </div>
        </div>

        {{-- MAIN TWO-COLUMN SPLIT WORKBENCH --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            {{-- LEFT SIDEBAR: SELECTED RECIPES QUEUE (4 Cols) --}}
            <aside class="lg:col-span-4 bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col h-[700px] overflow-hidden">
                {{-- Queue Header --}}
                <div class="p-3.5 bg-slate-900 dark:bg-slate-950 text-white flex items-center justify-between border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <h2 class="font-bold text-xs uppercase tracking-wider">Production Queue</h2>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-400/30" x-text="selectedRecipes.length"></span>
                    </div>

                    <button type="button"
                            x-on:click="showAddRecipeModal = true"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm transition active:scale-[0.98] cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>+ Add Recipe</span>
                    </button>
                </div>

                {{-- Local Search in Queue --}}
                <div class="p-2.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40">
                    <div class="relative">
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text"
                               x-model="recipeSearch"
                               placeholder="Filter queue by recipe name..."
                               class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 pl-8 pr-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition shadow-2xs" />
                    </div>
                </div>

                {{-- Scrollable List of Queued Recipes --}}
                <div class="flex-1 overflow-y-auto p-3 space-y-2.5 divide-y divide-transparent">
                    <template x-for="recipe in filteredSidebarRecipes" :key="recipe.id">
                        <div class="rounded-xl border p-3 transition cursor-pointer relative group"
                             :class="highlightedRecipeId === recipe.id ? 'bg-blue-50/60 dark:bg-blue-950/40 border-blue-500 dark:border-blue-500 ring-2 ring-blue-500/20 shadow-xs' : 'bg-white dark:bg-slate-800/70 border-slate-200 dark:border-slate-700/80 hover:border-slate-300 dark:hover:border-slate-600 hover:bg-slate-50/70 dark:hover:bg-slate-800'"
                             x-on:click="selectRecipe(recipe.id)">
                            
                            {{-- Active Left Marker --}}
                            <div class="absolute inset-y-2 left-1 w-1 rounded-full bg-blue-600 transition"
                                 x-show="highlightedRecipeId === recipe.id"></div>

                            <div class="flex items-start justify-between gap-2 pl-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300" x-text="recipe.code"></span>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-400 truncate" x-text="recipe.cat"></span>
                                    </div>
                                    <h4 class="font-bold text-xs text-slate-900 dark:text-white truncate leading-snug" x-text="recipe.name"></h4>
                                </div>

                                {{-- Remove Recipe Button --}}
                                <button type="button"
                                        x-on:click.stop="removeRecipe(recipe.id)"
                                        class="text-slate-300 dark:text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                        title="Remove Recipe">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            {{-- Card Footer: Stepper & Multiplier --}}
                            <div class="mt-2.5 pt-2 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between pl-2">
                                <div class="text-[11px] text-slate-400 dark:text-slate-400 font-medium">
                                    <span>Batches:</span>
                                </div>

                                {{-- Numeric Stepper (No Livewire requests) --}}
                                <div class="inline-flex items-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5 shadow-2xs"
                                     x-on:click.stop>
                                    <button type="button"
                                            x-on:click="adjustRecipeQty(recipe.id, -1)"
                                            class="w-6 h-6 rounded flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold text-xs transition cursor-pointer">
                                        -
                                    </button>
                                    <input type="number"
                                           min="1"
                                           :value="recipe.qty"
                                           x-on:change="updateRecipeQty(recipe.id, $event.target.value)"
                                           class="w-10 text-center font-mono font-bold text-xs border-0 bg-transparent p-0 text-slate-800 dark:text-slate-100 focus:ring-0" />
                                    <button type="button"
                                            x-on:click="adjustRecipeQty(recipe.id, 1)"
                                            class="w-6 h-6 rounded flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold text-xs transition cursor-pointer">
                                        +
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Empty Queue State --}}
                    <div x-show="selectedRecipes.length === 0" class="h-full flex flex-col items-center justify-center p-6 text-center text-slate-400 dark:text-slate-500">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-400 flex items-center justify-center mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                        </div>
                        <p class="font-bold text-xs text-slate-700 dark:text-slate-300">No Recipes Selected</p>
                        <p class="text-[11px] text-slate-400 dark:text-slate-400 mt-1 max-w-[200px]">Click "+ Add Recipe" above to select menus for this batch order.</p>
                        <button type="button"
                                x-on:click="showAddRecipeModal = true"
                                class="mt-4 px-3.5 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold text-xs hover:bg-blue-100 dark:hover:bg-blue-900/60 transition border border-blue-200 dark:border-blue-800 cursor-pointer">
                            + Select Recipes Now
                        </button>
                    </div>
                </div>
            </aside>

            {{-- RIGHT WORKSPACE: TWO DYNAMIC TABS (8 Cols) --}}
            <main class="lg:col-span-8 bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm flex flex-col h-[700px] overflow-hidden">
                {{-- Modern Segmented Tab Navigation Header --}}
                <div class="p-3 bg-slate-900 dark:bg-slate-950 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-800/90 dark:bg-slate-900 border border-slate-700/80">
                        {{-- Tab 1 Button --}}
                        <button type="button"
                                x-on:click="activeTab = 'selected'"
                                :class="activeTab === 'selected' ? 'bg-blue-600 text-white shadow-xs font-bold' : 'text-slate-300 hover:text-white font-medium'"
                                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <span>Selected Recipe Ingredients</span>
                            <template x-if="currentRecipe">
                                <span class="px-1.5 py-0.2 rounded text-[10px] bg-blue-500/40 text-blue-100 truncate max-w-[120px]" x-text="currentRecipe.name"></span>
                            </template>
                        </button>

                        {{-- Tab 2 Button --}}
                        <button type="button"
                                x-on:click="activeTab = 'overall'"
                                :class="activeTab === 'overall' ? 'bg-blue-600 text-white shadow-xs font-bold' : 'text-slate-300 hover:text-white font-medium'"
                                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span>Overall Ingredients</span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-700 dark:bg-slate-800 text-slate-200 font-mono font-bold" x-text="overallIngredientsListUnfiltered.length"></span>
                        </button>
                    </div>

                    {{-- Context Label --}}
                    <div class="text-[11px] text-slate-400 font-medium hidden sm:block">
                        <span x-show="activeTab === 'selected'">Showing single recipe breakdown</span>
                        <span x-show="activeTab === 'overall'">Consolidated raw requirements across all recipes</span>
                    </div>
                </div>

                {{-- TAB 1 CONTENT: SELECTED RECIPE INGREDIENTS --}}
                <div x-show="activeTab === 'selected'" class="flex-1 flex flex-col overflow-hidden">
                    {{-- Recipe Detail Bar --}}
                    <div class="p-3 bg-blue-50/70 dark:bg-blue-950/30 border-b border-blue-100 dark:border-blue-900/40 flex flex-wrap items-center justify-between gap-3"
                         x-show="currentRecipe">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                            <div>
                                <h3 class="font-extrabold text-xs text-blue-950 dark:text-blue-200">
                                    <span x-text="currentRecipe?.name"></span>
                                    <span class="text-blue-600 dark:text-blue-400 font-mono font-bold" x-text="'(' + (currentRecipe?.code || '') + ')'"></span>
                                </h3>
                                <p class="text-[11px] text-blue-800/80 dark:text-blue-300/80">
                                    Base formulation scaled for <strong class="font-bold text-blue-900 dark:text-blue-100" x-text="(currentRecipe?.qty || 1) + ' Batches'"></strong>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-blue-900 dark:text-blue-200 bg-white dark:bg-slate-800 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800 shadow-2xs font-mono"
                                  x-text="'Batch Multiplier: ×' + (currentRecipe?.qty || 1)"></span>
                        </div>
                    </div>

                    {{-- Table 1 --}}
                    <div class="flex-1 overflow-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="sticky top-0 bg-slate-800 dark:bg-slate-950 text-slate-200 dark:text-slate-300 font-bold uppercase text-[10px] tracking-wider border-b border-slate-700 dark:border-slate-800 z-10">
                                <tr>
                                    <th class="p-3">Item Code</th>
                                    <th class="p-3">Ingredient Description</th>
                                    <th class="p-3 text-right">Base Qty / Batch</th>
                                    <th class="p-3 text-right">Scaled Req. Qty</th>
                                    <th class="p-3">Unit</th>
                                    <th class="p-3 text-right">Cardex Available</th>
                                    <th class="p-3 text-center">Stock Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="ing in selectedRecipeIngredients" :key="ing.item_id">
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                                        <td class="p-3 font-mono font-bold text-slate-600 dark:text-slate-400" x-text="ing.item_code"></td>
                                        <td class="p-3 font-semibold text-slate-800 dark:text-slate-100" x-text="ing.item_name"></td>
                                        <td class="p-3 text-right font-mono text-slate-500 dark:text-slate-400" x-text="ing.base_qty.toFixed(2)"></td>
                                        <td class="p-3 text-right font-mono font-black text-slate-900 dark:text-white text-xs" x-text="ing.qty.toFixed(2)"></td>
                                        <td class="p-3 text-slate-500 dark:text-slate-400 font-medium" x-text="ing.uom_symbol"></td>
                                        <td class="p-3 text-right font-mono text-slate-600 dark:text-slate-300 font-bold" x-text="ing.available.toFixed(2)"></td>
                                        <td class="p-3 text-center">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                                                  :class="{
                                                      'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800': ing.status === 'Available',
                                                      'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800': ing.status === 'Insufficient',
                                                      'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800': ing.status === 'Out of Stock'
                                                  }">
                                                <span class="w-1.5 h-1.5 rounded-full"
                                                      :class="{
                                                          'bg-emerald-500': ing.status === 'Available',
                                                          'bg-amber-500': ing.status === 'Insufficient',
                                                          'bg-rose-500': ing.status === 'Out of Stock'
                                                      }"></span>
                                                <span x-text="ing.status"></span>
                                            </span>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="!currentRecipe">
                                    <td colspan="7" class="p-16 text-center text-slate-400 dark:text-slate-500">
                                        <div class="max-w-sm mx-auto">
                                            <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No Recipe Highlighted</p>
                                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Select or add a recipe in the left sidebar queue to view its isolated formulation.</p>
                                        </div>
                                    </td>
                                </tr>

                                <tr x-show="currentRecipe && selectedRecipeIngredients.length === 0">
                                    <td colspan="7" class="p-12 text-center text-slate-400 dark:text-slate-500">
                                        <p class="font-bold text-xs">No ingredients registered for this recipe.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 2 CONTENT: OVERALL INGREDIENTS (CONSOLIDATED) --}}
                <div x-show="activeTab === 'overall'" class="flex-1 flex flex-col overflow-hidden">
                    {{-- Filter and Action Header --}}
                    <div class="p-3 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2.5">
                            {{-- Search --}}
                            <div class="relative w-48 sm:w-60">
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text"
                                       x-model="overallSearch"
                                       placeholder="Search code or description..."
                                       class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 pl-8 pr-3 py-1.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" />
                            </div>

                            {{-- Filter Buttons --}}
                            <div class="flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-0.5 text-[11px] font-semibold">
                                <button type="button"
                                        x-on:click="overallFilter = 'all'"
                                        :class="overallFilter === 'all' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                        class="px-2.5 py-1 rounded-lg transition cursor-pointer" x-text="'All (' + overallStats.total + ')'"></button>
                                <button type="button"
                                        x-on:click="overallFilter = 'available'"
                                        :class="overallFilter === 'available' ? 'bg-emerald-600 text-white shadow-xs' : 'text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/30'"
                                        class="px-2.5 py-1 rounded-lg transition cursor-pointer" x-text="'In Stock (' + overallStats.ready + ')'"></button>
                                <button type="button"
                                        x-on:click="overallFilter = 'insufficient'"
                                        :class="overallFilter === 'insufficient' ? 'bg-amber-600 text-white shadow-xs' : 'text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/30'"
                                        class="px-2.5 py-1 rounded-lg transition cursor-pointer" x-text="'Low (' + overallStats.insufficient + ')'"></button>
                                <button type="button"
                                        x-on:click="overallFilter = 'out_of_stock'"
                                        :class="overallFilter === 'out_of_stock' ? 'bg-rose-600 text-white shadow-xs' : 'text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30'"
                                        class="px-2.5 py-1 rounded-lg transition cursor-pointer" x-text="'Deficit (' + overallStats.outOfStock + ')'"></button>
                            </div>
                        </div>

                        {{-- Manual Add Action --}}
                        <button type="button"
                                x-on:click="showAddIngredientModal = true"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 shadow-xs transition cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>+ Add Manual Item</span>
                        </button>
                    </div>

                    {{-- Table 2 Consolidated --}}
                    <div class="flex-1 overflow-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="sticky top-0 bg-slate-800 dark:bg-slate-950 text-slate-200 dark:text-slate-300 font-bold uppercase text-[10px] tracking-wider border-b border-slate-700 dark:border-slate-800 z-10">
                                <tr>
                                    <th class="p-3">Item Code</th>
                                    <th class="p-3">Item Description</th>
                                    <th class="p-3 text-right">From Recipes</th>
                                    <th class="p-3 text-center">Manual Add</th>
                                    <th class="p-3 text-right">Total Req.</th>
                                    <th class="p-3">Unit</th>
                                    <th class="p-3 text-right">Cardex Stock</th>
                                    <th class="p-3 text-center">Stock Status</th>
                                    <th class="p-3 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <template x-for="row in overallIngredientsList" :key="row.item_id">
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                                        <td class="p-3 font-mono font-bold text-slate-600 dark:text-slate-400" x-text="row.item_code"></td>
                                        <td class="p-3 font-semibold text-slate-800 dark:text-slate-100" x-text="row.item_name"></td>
                                        <td class="p-3 text-right font-mono text-slate-500 dark:text-slate-400" x-text="row.from_recipes.toFixed(2)"></td>
                                        
                                        {{-- Manual Add Cell --}}
                                        <td class="p-3 text-center">
                                            <template x-if="row.manual_add > 0">
                                                <div class="inline-flex items-center gap-1 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 rounded-lg p-0.5">
                                                    <button type="button" x-on:click="adjustManualQty(row.item_id, -1)" class="w-5 h-5 rounded hover:bg-blue-100 dark:hover:bg-blue-900/60 text-blue-800 dark:text-blue-300 font-bold cursor-pointer">-</button>
                                                    <input type="number"
                                                           step="0.01"
                                                           min="0"
                                                           :value="row.manual_add"
                                                           x-on:change="updateManualQty(row.item_id, $event.target.value)"
                                                           class="w-14 text-center font-mono font-bold text-blue-900 dark:text-blue-200 border-0 bg-transparent p-0 text-xs focus:ring-0" />
                                                    <button type="button" x-on:click="adjustManualQty(row.item_id, 1)" class="w-5 h-5 rounded hover:bg-blue-100 dark:hover:bg-blue-900/60 text-blue-800 dark:text-blue-300 font-bold cursor-pointer">+</button>
                                                </div>
                                            </template>
                                            <template x-if="row.manual_add === 0">
                                                <span class="text-slate-300 dark:text-slate-600 font-mono">-</span>
                                            </template>
                                        </td>

                                        <td class="p-3 text-right font-mono font-black text-slate-900 dark:text-white text-xs" x-text="row.total_required.toFixed(2)"></td>
                                        <td class="p-3 text-slate-500 dark:text-slate-400 font-medium" x-text="row.uom_symbol"></td>
                                        <td class="p-3 text-right font-mono text-slate-600 dark:text-slate-300 font-bold" x-text="row.available.toFixed(2)"></td>

                                        <td class="p-3 text-center">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                                                  :class="{
                                                      'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800': row.status === 'Available',
                                                      'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800': row.status === 'Insufficient',
                                                      'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800': row.status === 'Out of Stock'
                                                  }">
                                                <span class="w-1.5 h-1.5 rounded-full"
                                                      :class="{
                                                          'bg-emerald-500': row.status === 'Available',
                                                          'bg-amber-500': row.status === 'Insufficient',
                                                          'bg-rose-500': row.status === 'Out of Stock'
                                                      }"></span>
                                                <span x-text="row.status"></span>
                                            </span>
                                        </td>

                                        <td class="p-3 text-center">
                                            <template x-if="row.manual_add > 0">
                                                <button type="button"
                                                        x-on:click="removeManualIngredient(row.item_id)"
                                                        class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer"
                                                        title="Remove Manual Addition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </template>
                                            <template x-if="row.manual_add === 0">
                                                <span class="text-slate-200 dark:text-slate-700">-</span>
                                            </template>
                                        </td>
                                    </tr>
                                </template>

                                <tr x-show="overallIngredientsList.length === 0">
                                    <td colspan="9" class="p-12 text-center text-slate-400 dark:text-slate-500">
                                        <p class="font-bold text-xs">No ingredients match the search or filter.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>

        {{-- MODAL 1: ADD RECIPE TO QUEUE --}}
        <div x-show="showAddRecipeModal"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 dark:bg-black/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-transition.opacity>
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden"
                 x-on:click.outside="showAddRecipeModal = false">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-900 dark:bg-slate-950 text-white">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        <h3 class="font-bold text-sm">Select Recipe for Production</h3>
                    </div>
                    <button type="button" x-on:click="showAddRecipeModal = false" class="text-slate-400 hover:text-white text-xl font-bold leading-none cursor-pointer">&times;</button>
                </div>
                <div class="p-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-850">
                    <input type="text"
                           x-model="addRecipeSearch"
                           placeholder="Type recipe name, menu code, or category..."
                           class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs" />
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase text-[10px] border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="p-2.5">Code</th>
                                <th class="p-2.5">Recipe Name</th>
                                <th class="p-2.5">Category</th>
                                <th class="p-2.5 text-center">Ingredients</th>
                                <th class="p-2.5 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="recipe in filteredCatalogRecipes" :key="recipe.id">
                                <tr class="hover:bg-blue-50/60 dark:hover:bg-blue-950/40 transition">
                                    <td class="p-2.5 font-mono font-bold text-slate-500 dark:text-slate-400" x-text="recipe.code"></td>
                                    <td class="p-2.5 font-bold text-slate-800 dark:text-slate-100" x-text="recipe.name"></td>
                                    <td class="p-2.5 text-slate-500 dark:text-slate-400" x-text="recipe.cat"></td>
                                    <td class="p-2.5 text-center font-mono font-bold text-slate-600 dark:text-slate-300" x-text="recipe.ings.length"></td>
                                    <td class="p-2.5 text-center">
                                        <button type="button"
                                                x-on:click="addRecipe(recipe.id)"
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition cursor-pointer">
                                            + Select
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 flex justify-end">
                    <button type="button" x-on:click="showAddRecipeModal = false" class="px-4 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer">Close</button>
                </div>
            </div>
        </div>

        {{-- MODAL 2: ADD MANUAL INGREDIENT --}}
        <div x-show="showAddIngredientModal"
             class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 dark:bg-black/75 backdrop-blur-xs flex items-center justify-center p-4"
             x-transition.opacity>
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 max-w-2xl w-full max-h-[85vh] flex flex-col overflow-hidden"
                 x-on:click.outside="showAddIngredientModal = false">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-900 dark:bg-slate-950 text-white">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <div>
                            <h3 class="font-bold text-sm">Add Ingredient Manually</h3>
                            <p class="text-[10px] text-slate-400">Items added here appear in Overall Ingredients.</p>
                        </div>
                    </div>
                    <button type="button" x-on:click="showAddIngredientModal = false" class="text-slate-400 hover:text-white text-xl font-bold leading-none cursor-pointer">&times;</button>
                </div>
                <div class="p-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-850">
                    <input type="text"
                           x-model="addIngredientSearch"
                           placeholder="Type item description or item code..."
                           class="w-full text-xs rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 shadow-2xs" />
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase text-[10px] border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="p-2.5">Code</th>
                                <th class="p-2.5">Item Description</th>
                                <th class="p-2.5">Unit</th>
                                <th class="p-2.5 text-right">Cardex Avail.</th>
                                <th class="p-2.5 text-center">Status</th>
                                <th class="p-2.5 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <template x-for="item in filteredCatalogItems" :key="item.id">
                                <tr class="hover:bg-blue-50/60 dark:hover:bg-blue-950/40 transition">
                                    <td class="p-2.5 font-mono font-bold text-slate-500 dark:text-slate-400" x-text="item.code"></td>
                                    <td class="p-2.5 font-bold text-slate-800 dark:text-slate-100" x-text="item.name"></td>
                                    <td class="p-2.5 text-slate-500 dark:text-slate-400 font-medium" x-text="item.uom"></td>
                                    <td class="p-2.5 text-right font-mono font-bold"
                                        :class="item.avail > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                        x-text="item.avail.toFixed(2)"></td>
                                    <td class="p-2.5 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                              :class="item.avail > 0 ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300' : 'bg-rose-100 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300'"
                                              x-text="item.avail > 0 ? 'In Stock' : 'Out of Stock'"></span>
                                    </td>
                                    <td class="p-2.5 text-center">
                                        <button type="button"
                                                x-on:click="addManualIngredient(item)"
                                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition cursor-pointer">
                                            + Select
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-850 flex justify-end">
                    <button type="button" x-on:click="showAddIngredientModal = false" class="px-4 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer">Close</button>
                </div>
            </div>
        </div>

    </div>
</div>
