<?php

namespace App\Services\DataManagement;

use App\Models\DataManagement\Category;
use App\Models\DataManagement\Classification;
use App\Models\DataManagement\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\DataManagement\Item;
use App\Models\DataManagement\UnitOfMeasure;
use App\Models\Settings\SystemParameter;
use App\Models\DataManagement\Price;
use App\Models\Business\Company;

class ItemService
{
    protected $itemModel;
    protected $measureModel;
    protected $systemParameterModel;
    protected $priceModel;
    protected $categoryModel;
    protected $classificationModel;
    protected $brandModel;
    protected $unitConversionService;
    protected $companyModel;

    public function __construct(
        Item $itemModel,
        UnitOfMeasure $measureModel,
        SystemParameter $systemParameterModel,
        Price $priceModel,
        Category $categoryModel,
        Classification $classificationModel,
        Brand $brandModel,
        UnitConversionService $unitConversionService,
        Company $companyModel
    ) {
        $this->itemModel = $itemModel;
        $this->measureModel = $measureModel;
        $this->systemParameterModel = $systemParameterModel;
        $this->priceModel = $priceModel;
        $this->categoryModel = $categoryModel;
        $this->classificationModel = $classificationModel;
        $this->brandModel = $brandModel;
        $this->unitConversionService = $unitConversionService;
        $this->companyModel = $companyModel;
    }

    public function createItem(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            $this->categoryModel->findOrFail($data['category_id'] ?? null);
            $categoryCount = $this->categoryModel->count() + 1;
            $categoryCode = $this->categoryModel->where('id', $data['category_id'] ?? null)->value('category_code') ?? 'CAT';
            $companyCode = $this->companyModel->where('id', $data['company_id'])->value('company_code') ?? 'COMP';
            // 1. Resolve measurement type name ('WEIGHT', 'VOLUME', 'UNIT', 'LENGTH')
            $measureTypeName = $this->systemParameterModel->where('id', $data['measure_type_id'])->value('name') ?? 'UNIT';
            $isUnitType = strtoupper(trim($measureTypeName)) === 'UNIT';

            // 2. Safe packaging quantity handling (e.g. 50 for a pack of 50 cups; defaults to 1 for discrete unit items)
            $rawVal = $data['measure_value'] ?? null;
            if (filled($rawVal) && (float)$rawVal > 0) {
                $measureValue = (float) $rawVal;
            } else {
                $measureValue = $isUnitType ? 1.00 : 0.00;
            }

            $measureSymbol = $data['measure_symbol'] ?? ($isUnitType ? 'pc' : '');

            // 3. Resolve unit description safely
            $symbolDescription = $this->systemParameterModel
                ->where('key', 'measure_symbol')
                ->where('name', $measureSymbol)
                ->value('description') ?? $measureSymbol;

            // 4. Get or create measure record
            $unitName = trim("{$measureValue} {$symbolDescription}");
            $unitSymbol = trim("{$measureValue} {$measureSymbol}");
            if ($isUnitType && $measureValue == 1.0) {
                $unitName = "1 {$symbolDescription}";
                $unitSymbol = "1 {$measureSymbol}";
            }

            $measure = $this->measureModel->firstOrCreate(
                [
                    'measure_type_id' => $data['measure_type_id'],
                    'measure_symbol'  => $measureSymbol,
                    'measure_value'   => $measureValue,
                ],
                [
                    'unit_name'        => $unitName,
                    'unit_description' => $symbolDescription,
                    'unit_symbol'      => $unitSymbol,
                ]
            );

            // 5. Create Item using mass assignment selection
            $itemData = Arr::only($data, [
                'item_description',
                'company_id',
                'item_barcode',
                'classification_id',
                'sub_class_id',
                'brand_id',
                'category_id',
                'orderpoint',
                'optimal_stock',
                'is_forsale',
                'created_by',
            ]);

            $itemData['uom_id'] = $measure->id;
            $itemData['measurement_type'] = strtoupper($measureTypeName);
            $itemData['item_code'] = strtoupper("{$companyCode}-{$categoryCode}-") . str_pad((string) $categoryCount, 4, '0', STR_PAD_LEFT);

            $item = $this->itemModel->create($itemData);

            // 6. Create Price via Relationship or Model
            if (filled($data['item_cost'] ?? null)) {
                $item->cost()->create([
                    'price_type' => 'COST',
                    'amount'     => $data['item_cost'],
                    'company_id' => $data['company_id'],
                    'branch_id'  => $data['branch_id'] ?? null,
                ]);
            }

            // 7. Generate unit conversion matrix
            $item->load('unit');
            $this->unitConversionService->generateConversionsForItem($item);

            return $item;
        });
    }

    public function updateItem(array $data): Item
    {
        return DB::transaction(function () use ($data) {

            $currentCategoryId = $this->itemModel->where('id', $data['item_id'])->value('category_id');
            $updateCategoryId = $data['category_id'] !== $currentCategoryId ? true : false;
            if ($updateCategoryId) {
                $this->categoryModel->findOrFail($data['category_id'] ?? null);
                $categoryCount = $this->categoryModel->count() + 1;
                $categoryCode = $this->categoryModel->where('id', $data['category_id'] ?? null)->value('category_code') ?? 'CAT';
                $companyCode = $this->companyModel->where('id', $data['company_id'])->value('company_code') ?? 'COMP';
            }
            // 1. Resolve measurement type name ('WEIGHT', 'VOLUME', 'UNIT', 'LENGTH')
            $measureTypeName = $this->systemParameterModel->where('id', $data['measure_type_id'])->value('name') ?? 'UNIT';
            $isUnitType = strtoupper(trim($measureTypeName)) === 'UNIT';

            // 2. Safe packaging quantity handling (e.g. 50 for a pack of 50 cups; defaults to 1 for discrete unit items)
            $rawVal = $data['measure_value'] ?? null;
            if (filled($rawVal) && (float)$rawVal > 0) {
                $measureValue = (float) $rawVal;
            } else {
                $measureValue = $isUnitType ? 1.00 : 0.00;
            }

            $measureSymbol = $data['measure_symbol'] ?? ($isUnitType ? 'pc' : '');

            // 3. Resolve unit description safely
            $symbolDescription = $this->systemParameterModel
                ->where('key', 'measure_symbol')
                ->where('name', $measureSymbol)
                ->value('description') ?? $measureSymbol;

            // 4. Get or create measure record
            $unitName = trim("{$measureValue} {$symbolDescription}");
            $unitSymbol = trim("{$measureValue} {$measureSymbol}");
            if ($isUnitType && $measureValue == 1.0) {
                $unitName = "1 {$symbolDescription}";
                $unitSymbol = "1 {$measureSymbol}";
            }

            $measure = $this->measureModel->firstOrCreate(
                [
                    'measure_type_id' => $data['measure_type_id'],
                    'measure_symbol'  => $measureSymbol,
                    'measure_value'   => $measureValue,
                ],
                [
                    'unit_name'        => $unitName,
                    'unit_description' => $symbolDescription,
                    'unit_symbol'      => $unitSymbol,
                ]
            );

            // 5. Update Item using mass assignment selection
            $itemData = Arr::only($data, [
                'item_description',
                'company_id',
                'item_barcode',
                'classification_id',
                'sub_class_id',
                'brand_id',
                'category_id',
                'orderpoint',
                'optimal_stock',
                'is_forsale',
                'created_by',
            ]);

            $itemData['uom_id'] = $measure->id;
            $itemData['measurement_type'] = strtoupper($measureTypeName);
            if ($updateCategoryId) {
                $itemData['item_code'] = strtoupper("{$companyCode}-{$categoryCode}-") . str_pad((string) $categoryCount, 4, '0', STR_PAD_LEFT);
            }

            $item = $this->itemModel->findOrFail($data['item_id']);
            $item->update($itemData);

            // 6. Record Cost Price if changed (maintaining historical audit trail)
            if (filled($data['item_cost'] ?? null)) {
                $newCost = (float) $data['item_cost'];
                $currentCost = $item->cost ? (float) $item->cost->amount : null;
                if ($currentCost === null || abs($currentCost - $newCost) > 0.0001) {
                    $this->priceModel->create([
                        'price_type' => 'COST',
                        'item_id'    => $item->id,
                        'amount'     => $newCost,
                        'company_id' => $data['company_id'],
                        'branch_id'  => $data['branch_id'] ?? null,
                    ]);
                }
            }

            // 7. Refresh unit conversion matrix
            $item->load('unit');
            $this->unitConversionService->generateConversionsForItem($item);

            return $item;
        });
    }

    public function changeItemStatus(int $id): Item
    {
        $item = $this->itemModel->findOrFail($id);

        $item->item_status = $item->item_status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
        $item->save();

        return $item;
    }
    public function addNewCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = $this->categoryModel->create($data);
            ;
            return $category;
        });
    }
    public function updateCategory(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $category = $this->categoryModel->findOrFail($data['id']);
            $itemData = Arr::only($data, [
                'category_name',
                'category_description',
                'category_code',
                'updated_by',
            ]);
            $category->update($itemData);
            ;
            return $category;
        });
    }
    public function changeCategoryStatus(int $id): Category
    {
        $category = $this->categoryModel->findOrFail($id);
        $category->status = ($category->status === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        $category->save();
        return $category;
    }

    public function addNewClassification(array $data): Classification
    {
        return DB::transaction(function () use ($data) {
            $classification = $this->classificationModel->create($data);
            ;
            return $classification;
        });
    }

    public function updateClassification(array $data): Classification
    {
        return DB::transaction(function () use ($data) {
            $classification = $this->classificationModel->findOrFail($data['id']);
            $itemData = Arr::only($data, [
                'classification_name',
                'classification_description',
                'updated_by',
            ]);
            $classification->update($itemData);
            ;
            return $classification;
        });
    }

    public function changeClassificationStatus(int $id): Classification
    {
        $classification = $this->classificationModel->findOrFail($id);
        $classification->status = ($classification->status === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        $classification->save();
        return $classification;
    }

    public function addNewSubClass(array $data): Classification
    {
        return DB::transaction(function () use ($data) {
            $subClass = $this->classificationModel->create($data);
            ;
            return $subClass;
        });
    }

    public function updateSubClass(array $data): Classification
    {
        return DB::transaction(function () use ($data) {
            $subClass = $this->classificationModel->findOrFail($data['id']);
            $itemData = Arr::only($data, [
                'class_parent',
                'classification_name',
                'classification_description',
                'updated_by',
            ]);
            $subClass->update($itemData);
            ;
            return $subClass;
        });
    }

    public function changeSubClassificationStatus(int $id): Classification
    {
        $subClass = $this->classificationModel->findOrFail($id);
        $subClass->status = ($subClass->status === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        $subClass->save();
        return $subClass;
    }

    public function addNewBrand(array $data): Brand
    {
        return DB::transaction(function () use ($data) {
            $brand = $this->brandModel->create($data);
            ;
            return $brand;
        });
    }

    public function updateBrand(array $data): Brand
    {
        return DB::transaction(function () use ($data) {
            $brand = $this->brandModel->findOrFail($data['id']);
            $itemData = Arr::only($data, [
                'brand_name',
                'brand_description',
                'updated_by',
            ]);
            $brand->update($itemData);
            ;
            return $brand;
        });
    }

    public function changeBrandStatus(int $id): Brand
    {
        $brand = $this->brandModel->findOrFail($id);
        $brand->status = ($brand->status === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        $brand->save();
        return $brand;
    }

    /**
     * Get historical cost changes for an item formatted for TallStackUI chart and audit logs.
     */
    public function getItemCostHistory(Item $item): array
    {
        $prices = $item->costHistory()->get();

        $labels = [];
        $data = [];
        $logs = [];
        $prevAmount = null;

        foreach ($prices as $p) {
            $dateStr = $p->created_at ? $p->created_at->format('M d, Y') : 'Initial';
            $labels[] = $dateStr;
            $amt = (float) $p->amount;
            $data[] = $amt;

            $diff = $prevAmount !== null ? round($amt - $prevAmount, 2) : 0.0;
            $pct = ($prevAmount !== null && $prevAmount > 0) ? round(($diff / $prevAmount) * 100, 1) : 0.0;

            $logs[] = [
                'id'           => $p->id,
                'date'         => $dateStr,
                'created_at'   => $p->created_at,
                'amount'       => $amt,
                'variance'     => $diff,
                'variance_pct' => $pct,
                'supplier'     => $p->supplier?->supplier_name ?? 'Standard / Manual',
            ];

            $prevAmount = $amt;
        }

        // If only 1 price record exists, create a 2-point baseline so the line chart can render cleanly
        $chartLabels = $labels;
        $chartData = $data;
        if (count($chartData) === 1) {
            $firstDate = $labels[0] ?? 'Initial';
            $todayStr = now()->format('M d, Y');
            if ($firstDate !== $todayStr) {
                $chartLabels = [$firstDate, $todayStr];
                $chartData = [$chartData[0], $chartData[0]];
            } else {
                $chartLabels = ['Baseline', 'Current'];
                $chartData = [$chartData[0], $chartData[0]];
            }
        }

        return [
            'labels'       => $chartLabels,
            'series'       => [
                ['name' => 'PO Cost', 'data' => $chartData],
            ],
            'raw_count'    => count($prices),
            'logs'         => array_reverse($logs),
            'min_cost'     => !empty($data) ? min($data) : 0.0,
            'max_cost'     => !empty($data) ? max($data) : 0.0,
            'current_cost' => !empty($data) ? end($data) : 0.0,
        ];
    }


    public static function cardexRecords(int $branchId, int $companyId)
    {
        $itemRawData = Item::where('company_id', $companyId)
            ->where('item_status', 1)
            ->with('unit', 'cost', 'brand', 'classification', 'subClassification', 'category')
            ->get();
        $itemIds = $itemRawData->pluck('id')->filter()->unique()->toArray();
        $cardexRecords = DB::table('cardex')
            ->select('item_id')
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_in ELSE 0 END) as total_in")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'final' THEN qty_out ELSE 0 END) as total_out")
            ->selectRaw("SUM(CASE WHEN LOWER(status) = 'reserved' THEN qty_out ELSE 0 END) as total_reserved")
            ->where('source_branch_id', $branchId)
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
                'reserved' => round($totalReserved, 2),
            ];
        }

        $uoms = UnitOfMeasure::pluck('unit_symbol', 'id')->toArray();
        $consolidated = [];

        foreach ($itemRawData as $detail) {
            $itemId = $detail->id;
            $itemCode = $detail->item_code ?? 'ITEM-' . $itemId;
            $itemName = $detail->item_description ?? 'N/A';
            $itemStatus = $detail->item_status ?? 'N/A';
            $itemCost = (float) ($detail->cost?->amount ?? 0);
            $uomSymbol = $uoms[$detail->uom_id] ?? ($detail->unit?->unit_symbol ?? 'N/A');
            $itemUomSymbol = $uoms[$detail->uom_id ?? 0] ?? ($detail->unit?->unit_symbol ?? 'N/A');
            $stock = $stockMap[$itemId] ?? ['bal' => 0, 'avail' => 0, 'reserved' => 0];
            $exactCardexAvail = (float) $stock['avail'];
            $cost = (float) ($detail->cost?->amount ?? 0);
            $brand = $detail->brand?->brand_name ?? 'N/A';
            $classification = $detail->classification?->classification_name ?? 'N/A';
            $subclass = $detail->subClassification?->classification_name ?? 'N/A';
            $orderpoint = (float) ($detail->orderpoint ?? 0);
            $optimal_stock = (float) ($detail->optimal_stock ?? 0);
            $barcode = $detail->item_barcode ?? 'N/A';
            $is_forsale = (bool) ($detail->is_forsale ?? false);
            $category = $detail->category?->category_name ?? 'N/A';


            $consolidated[] = [
                'item_id' => $itemId,
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'item_description' => $itemName,
                'item_status' => $itemStatus,
                'item_cost' => $itemCost,
                'uom_id' => $detail->uom_id,
                'total_required' => 0,
                'uom_symbol' => $uomSymbol,
                'item_uom' => $itemUomSymbol,
                'balance' => $stock['bal'],
                'available' => $exactCardexAvail,
                'reserved' => $stock['reserved'],
                'brand' => $brand,
                'classification' => $classification,
                'subclass' => $subclass,
                'orderpoint' => $orderpoint,
                'optimal_stock' => $optimal_stock,
                'barcode' => $barcode,
                'is_forsale' => $is_forsale,
                'cost' => $cost,
            ];
        }

        return array_values($consolidated);
    }
}
