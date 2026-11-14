<?php

use Livewire\Component;
use App\Models\Restaurant\ProductionOrder;
use App\Models\DataManagement\UnitOfMeasure;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    use Interactions;

    public int $orderId;
    public string $reference = '';
    public string $status = 'DRAFT';
    public ?string $notes = null;
    public string $preparedBy = '';
    public string $createdAt = '';
    public array $productionMenus = [];
    public array $groupedDetails = [];
    public array $consolidatedIngredients = [];
    public int $totalBatches = 0;
    public int $totalIngredients = 0;
    public bool $isFullyInStock = true;
    public int $outOfStockCount = 0;
    public int $insufficientCount = 0;

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
            'productionOrderDetails.menu',
        ])
            ->where('branch_id', $branchId)
            ->findOrFail($id);

        $this->reference = $order->reference;
        $this->status = $order->status;
        $this->notes = $order->notes;
        $this->preparedBy = trim(($order->preparedBy?->name ?? '') . ' ' . ($order->preparedBy?->last_name ?? ''));
        $this->createdAt = Carbon::parse($order->created_at)->format('M d, Y h:i A');

        // Preload unit symbols
        $uoms = UnitOfMeasure::pluck('unit_symbol', 'id')->toArray();

        // Queued menus
        $this->totalBatches = 0;
        $this->productionMenus = $order->productionMenus->map(function ($pm) {
            $this->totalBatches += (int) $pm->qty;
            return [
                'menu_id' => $pm->menu_id,
                'name' => $pm->menu?->menu_name ?? 'N/A',
                'code' => $pm->menu?->menu_code ?? 'REC-' . $pm->menu_id,
                'cat' => $pm->menu?->category?->category_name ?? 'General',
                'type' => $pm->menu?->menu_type ?? 'Ala Carte',
                'qty' => (int) $pm->qty,
            ];
        })->toArray();

        // Cardex stock balances for items in this production order
        $itemIds = $order->productionOrderDetails->pluck('item_id')->filter()->unique()->toArray();
        $cardexRecords = DB::table('cardex')
            ->select('item_id')
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_in ELSE 0 END) as total_in")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_out ELSE 0 END) as total_out")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'reserved' THEN qty_out ELSE 0 END) as total_reserved")
            ->where('source_branch_id', $branchId)
            ->whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        $stockMap = [];
        foreach ($itemIds as $itemId) {
            $cardex = $cardexRecords->get($itemId);
            $totalIn = (float) ($cardex->total_in ?? 0);
            $totalOut = (float) ($cardex->total_out ?? 0);
            $totalReserved = (float) ($cardex->total_reserved ?? 0);
            $balance = $totalIn - $totalOut;
            $available = $balance - $totalReserved;

            $stockMap[$itemId] = [
                'bal' => round($balance, 2),
                'avail' => round($available, 2),
            ];
        }

        // Consolidated ingredients
        $consolidated = [];
        $grouped = [];

        foreach ($order->productionOrderDetails as $detail) {
            $itemId = $detail->item_id;
            $itemCode = $detail->item?->item_code ?? 'ITEM-' . $itemId;
            $itemName = $detail->item?->item_description ?? 'N/A';
            $uomSymbol = $uoms[$detail->uom_id] ?? ($detail->item?->unit?->unit_symbol ?? 'N/A');
            $stock = $stockMap[$itemId] ?? ['bal' => 0, 'avail' => 0];
            $qty = (float) $detail->qty;
            $isManual = is_null($detail->menu_id);

            // 1. Accumulate into consolidated
            if (! isset($consolidated[$itemId])) {
                $consolidated[$itemId] = [
                    'item_id' => $itemId,
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'from_recipes' => 0,
                    'manual_add' => 0,
                    'total_required' => 0,
                    'uom_symbol' => $uomSymbol,
                    'balance' => $stock['bal'],
                    'available' => $stock['avail'],
                ];
            }

            if ($isManual) {
                $consolidated[$itemId]['manual_add'] += $qty;
            } else {
                $consolidated[$itemId]['from_recipes'] += $qty;
            }

            // 2. Accumulate into grouped
            $groupKey = $isManual ? 'MANUAL_ADDITIONS' : ($detail->menu_id);
            $groupTitle = $isManual ? 'Manual Additions (Direct Raw Items)' : ($detail->menu?->menu_name ?? 'Menu #' . $detail->menu_id);
            $groupCode = $isManual ? 'EXTRA' : ($detail->menu?->menu_code ?? 'REC-' . $detail->menu_id);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'key' => $groupKey,
                    'title' => $groupTitle,
                    'code' => $groupCode,
                    'is_manual' => $isManual,
                    'items' => [],
                ];
            }

            $grouped[$groupKey]['items'][] = [
                'item_id' => $itemId,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'qty' => $qty,
                'uom_symbol' => $uomSymbol,
                'available' => $stock['avail'],
            ];
        }

        // Calculate consolidated totals & stock status
        $this->outOfStockCount = 0;
        $this->insufficientCount = 0;

        foreach ($consolidated as &$row) {
            $row['total_required'] = $row['from_recipes'] + $row['manual_add'];

            if ($row['available'] <= 0) {
                $row['status'] = 'Out of Stock';
                $row['status_color'] = 'rose';
                $this->outOfStockCount++;
            } elseif ($row['available'] < $row['total_required']) {
                $row['status'] = 'Insufficient';
                $row['status_color'] = 'amber';
                $this->insufficientCount++;
            } else {
                $row['status'] = 'Available';
                $row['status_color'] = 'emerald';
            }
        }
        unset($row);

        $this->consolidatedIngredients = array_values($consolidated);
        $this->groupedDetails = array_values($grouped);
        $this->totalIngredients = count($this->consolidatedIngredients);
        $this->isFullyInStock = ($this->totalIngredients > 0 && $this->outOfStockCount === 0 && $this->insufficientCount === 0);
    }
};
?>

<div class="min-h-screen bg-slate-50/60 dark:bg-slate-950 pb-16 font-sans text-slate-800 dark:text-slate-100"
     x-data="{ activeTab: 'consolidated' }"
     x-cloak>

    <div class="space-y-4 px-4 py-4 sm:px-6">

        {{-- BREADCRUMBS & LIVE STATUS --}}
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                ['label' => 'Restaurant ERP', 'link' => route('restaurant.production-order.summary'), 'icon' => 'building-storefront'],
                ['label' => 'Production Orders', 'link' => route('restaurant.production-order.summary'), 'icon' => 'clipboard-document-list'],
                ['label' => 'View ' . $reference, 'icon' => 'eye'],
            ]" />
            
            <div class="flex items-center gap-2">
                @if($status === 'COMPLETED')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-800 border border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        Status: COMPLETED
                    </span>
                @elseif($status === 'PENDING' || $status === 'FINAL')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Status: FINAL / PENDING
                    </span>
                @elseif($status === 'CANCELLED')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Status: CANCELLED
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Status: DRAFT
                    </span>
                @endif
            </div>
        </div>

        {{-- COMMAND TOOLBAR (ERP TOP CONTROLS) --}}
        <header class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm p-4 relative overflow-hidden">
            <div class="absolute inset-y-0 left-0 w-1.5 bg-blue-600"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pl-2">
                {{-- Action Group --}}
                <div class="flex flex-wrap items-center gap-2.5 print:hidden">
                    {{-- Summary Button --}}
                    <a href="{{ route('restaurant.production-order.summary') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 transition shadow-2xs">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        <span>Back to Summary</span>
                    </a>

                    {{-- Edit Button (If Draft) --}}
                    @if($status === 'DRAFT')
                        <a href="{{ route('restaurant.production-order.edit', ['id' => $orderId]) }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2 text-xs font-extrabold rounded-xl text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 shadow-md shadow-blue-500/25 active:scale-[0.98] transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            <span>Edit Order</span>
                        </a>
                    @endif

                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 hidden sm:block"></div>

                    {{-- Print Ticket --}}
                    <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 transition shadow-2xs cursor-pointer">
                        <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        <span>Print Batch Slip</span>
                    </button>
                </div>

                {{-- Production Info Chips --}}
                <div class="flex flex-wrap lg:flex-nowrap items-center gap-3">
                    {{-- Reference --}}
                    <div class="flex items-center gap-1.5 bg-slate-900 dark:bg-slate-800 text-white px-3.5 py-1.5 rounded-xl text-xs shrink-0 shadow-xs border border-transparent dark:border-slate-700">
                        <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider">REF</span>
                        <span class="font-mono font-bold tracking-wide">{{ $reference }}</span>
                    </div>

                    {{-- Prepared By --}}
                    <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-xl text-xs shrink-0">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="font-bold text-slate-700 dark:text-slate-300 truncate">{{ $preparedBy ?: 'N/A' }}</span>
                    </div>

                    {{-- Date --}}
                    <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded-xl text-xs shrink-0">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $createdAt }}</span>
                    </div>
                </div>
            </div>

            {{-- Optional Notes Display --}}
            @if($notes)
                <div class="mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-start gap-2 text-xs text-slate-600 dark:text-slate-300 bg-slate-50/70 dark:bg-slate-800/60 p-2.5 rounded-xl">
                    <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    <div>
                        <strong class="font-bold text-slate-800 dark:text-white">Order Notes:</strong>
                        <span>{{ $notes }}</span>
                    </div>
                </div>
            @endif
        </header>

        {{-- EXECUTIVE KPI METRIC TILES --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
            {{-- Tile 1 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold shrink-0 border border-blue-100 dark:border-blue-900/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Recipes Queued</p>
                    <p class="text-lg font-black text-slate-900 dark:text-white leading-tight">{{ count($productionMenus) }}</p>
                </div>
            </div>

            {{-- Tile 2 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold shrink-0 border border-indigo-100 dark:border-indigo-900/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Total Batches</p>
                    <p class="text-lg font-black text-indigo-900 dark:text-indigo-300 leading-tight">{{ $totalBatches }}</p>
                </div>
            </div>

            {{-- Tile 3 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold shrink-0 border border-purple-100 dark:border-purple-900/60">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Total Ingredients</p>
                    <p class="text-lg font-black text-slate-900 dark:text-white leading-tight">{{ $totalIngredients }}</p>
                </div>
            </div>

            {{-- Tile 4 --}}
            <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-2xs flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold shrink-0 border {{ $isFullyInStock ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800' }}">
                    @if($isFullyInStock)
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    @endif
                </div>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Stock Availability</p>
                    <p class="text-xs font-black leading-tight {{ $isFullyInStock ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400' }}">
                        @if($totalIngredients === 0)
                            No Items
                        @elseif($isFullyInStock)
                            100% In Stock
                        @else
                            {{ $outOfStockCount }} Out / {{ $insufficientCount }} Low
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- QUEUED RECIPES TABLE --}}
        <section class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-3.5 bg-slate-900 dark:bg-slate-950 text-white flex items-center justify-between border-b border-transparent dark:border-slate-800">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <h2 class="font-bold text-xs uppercase tracking-wider">Target Recipe Formulations</h2>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-500/30 text-blue-300 border border-blue-400/30">{{ count($productionMenus) }} Menus</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 font-bold uppercase text-[10px] border-b border-slate-200 dark:border-slate-700/80">
                        <tr>
                            <th class="p-3">Recipe Code</th>
                            <th class="p-3">Recipe / Menu Name</th>
                            <th class="p-3">Category</th>
                            <th class="p-3">Menu Type</th>
                            <th class="p-3 text-right">Batch Multiplier</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($productionMenus as $menu)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                                <td class="p-3 font-mono font-bold text-slate-500 dark:text-slate-400">{{ $menu['code'] }}</td>
                                <td class="p-3 font-bold text-slate-800 dark:text-white">{{ $menu['name'] }}</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">{{ $menu['cat'] }}</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">{{ $menu['type'] }}</td>
                                <td class="p-3 text-right">
                                    <span class="font-mono font-black text-xs px-2.5 py-1 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                        × {{ $menu['qty'] }} Batches
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400 font-bold text-xs">
                                    No menus registered in this production order.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- INGREDIENT BREAKDOWN SECTION (TWO VIEW MODES) --}}
        <section class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-3 bg-slate-900 dark:bg-slate-950 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 p-1 rounded-xl bg-slate-800/90 border border-slate-700/80">
                    <button type="button"
                            x-on:click="activeTab = 'consolidated'"
                            :class="activeTab === 'consolidated' ? 'bg-blue-600 text-white shadow-xs font-bold' : 'text-slate-300 hover:text-white font-medium'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Consolidated Ingredients</span>
                        <span class="px-1.5 py-0.2 rounded text-[10px] bg-slate-700 text-slate-200 font-mono font-bold">{{ count($consolidatedIngredients) }}</span>
                    </button>

                    <button type="button"
                            x-on:click="activeTab = 'grouped'"
                            :class="activeTab === 'grouped' ? 'bg-blue-600 text-white shadow-xs font-bold' : 'text-slate-300 hover:text-white font-medium'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs transition cursor-pointer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Grouped by Recipe</span>
                    </button>
                </div>

                <div class="text-[11px] text-slate-400 font-medium">
                    <span x-show="activeTab === 'consolidated'">Total raw material requirements for this batch order</span>
                    <span x-show="activeTab === 'grouped'">Breakdown partitioned by individual menu formulation</span>
                </div>
            </div>

            {{-- TAB A: CONSOLIDATED INGREDIENTS --}}
            <div x-show="activeTab === 'consolidated'" class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-800 dark:bg-slate-900 text-slate-200 font-bold uppercase text-[10px] tracking-wider border-b border-slate-700 dark:border-slate-800">
                        <tr>
                            <th class="p-3">Item Code</th>
                            <th class="p-3">Ingredient Description</th>
                            <th class="p-3 text-right">From Recipes</th>
                            <th class="p-3 text-right">Manual Add</th>
                            <th class="p-3 text-right">Total Req.</th>
                            <th class="p-3">Unit</th>
                            <th class="p-3 text-right">Cardex Stock</th>
                            <th class="p-3 text-center">Stock Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($consolidatedIngredients as $row)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition">
                                <td class="p-3 font-mono font-bold text-slate-600 dark:text-slate-400">{{ $row['item_code'] }}</td>
                                <td class="p-3 font-semibold text-slate-800 dark:text-white">{{ $row['item_name'] }}</td>
                                <td class="p-3 text-right font-mono text-slate-500 dark:text-slate-400">{{ number_format($row['from_recipes'], 2) }}</td>
                                <td class="p-3 text-right font-mono text-blue-700 dark:text-blue-400">
                                    {{ $row['manual_add'] > 0 ? number_format($row['manual_add'], 2) : '-' }}
                                </td>
                                <td class="p-3 text-right font-mono font-black text-slate-900 dark:text-white text-xs">{{ number_format($row['total_required'], 2) }}</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400 font-medium">{{ $row['uom_symbol'] }}</td>
                                <td class="p-3 text-right font-mono text-slate-600 dark:text-slate-300 font-bold">{{ number_format($row['available'], 2) }}</td>
                                <td class="p-3 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold
                                        @if($row['status'] === 'Available') bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800
                                        @elseif($row['status'] === 'Insufficient') bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800
                                        @else bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 @endif">
                                        <span class="w-1.5 h-1.5 rounded-full
                                            @if($row['status'] === 'Available') bg-emerald-500
                                            @elseif($row['status'] === 'Insufficient') bg-amber-500
                                            @else bg-rose-500 @endif"></span>
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-12 text-center text-slate-400 font-bold text-xs">
                                    No ingredients found for this production order.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- TAB B: GROUPED BY RECIPE --}}
            <div x-show="activeTab === 'grouped'" class="p-4 space-y-4">
                @foreach($groupedDetails as $group)
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-2xs">
                        <div class="p-3 bg-slate-100 dark:bg-slate-800/80 flex items-center justify-between border-b border-slate-200 dark:border-slate-700/80">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600">{{ $group['code'] }}</span>
                                <h4 class="font-extrabold text-xs text-slate-800 dark:text-white">{{ $group['title'] }}</h4>
                            </div>
                            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">{{ count($group['items']) }} Ingredients</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 font-semibold uppercase text-[10px] border-b border-slate-100 dark:border-slate-700/60">
                                    <tr>
                                        <th class="p-2.5">Item Code</th>
                                        <th class="p-2.5">Item Description</th>
                                        <th class="p-2.5 text-right">Scaled Req. Qty</th>
                                        <th class="p-2.5">Unit</th>
                                        <th class="p-2.5 text-right">Cardex Avail.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach($group['items'] as $item)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition">
                                            <td class="p-2.5 font-mono font-bold text-slate-500 dark:text-slate-400">{{ $item['item_code'] }}</td>
                                            <td class="p-2.5 font-semibold text-slate-800 dark:text-white">{{ $item['item_name'] }}</td>
                                            <td class="p-2.5 text-right font-mono font-black text-slate-900 dark:text-white">{{ number_format($item['qty'], 2) }}</td>
                                            <td class="p-2.5 text-slate-500 dark:text-slate-400">{{ $item['uom_symbol'] }}</td>
                                            <td class="p-2.5 text-right font-mono font-bold {{ $item['available'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                {{ number_format($item['available'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

    </div>
</div>

