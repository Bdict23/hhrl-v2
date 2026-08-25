<?php

namespace App\Services\DataManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\DataManagement\Item;
use App\Models\DataManagement\UnitOfMeasure;
use App\Models\Settings\SystemParameter;
use App\Models\DataManagement\Price;



class ItemService
{
    protected $itemModel;
    protected $measureModel;
    protected $systemParameterModel;
    protected $priceModel;

    public function __construct(
        Item $itemModel,
        UnitOfMeasure $measureModel,
        SystemParameter $systemParameterModel,
        Price $priceModel
    ) {
        $this->itemModel = $itemModel;
        $this->measureModel = $measureModel;
        $this->systemParameterModel = $systemParameterModel;
        $this->priceModel = $priceModel;
    }

    public function createItem(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            // 1. Safe default handling for measure attributes
            $measureValue = $data['measure_value'] ?? 0.00;
            $measureSymbol = $data['measure_symbol'] ?? '';
            // 2. Resolve unit description safely
            $symbolDescription = $this->systemParameterModel
                ->where('key', 'measure_symbol')
                ->where('name', $measureSymbol)
                ->value('description') ?? $measureSymbol;

            // 3. Get or create measure record
            $measure = $this->measureModel->firstOrCreate(
                [
                    'measure_type_id' => $data['measure_type_id'],
                    'measure_symbol'  => $measureSymbol,
                    'measure_value'   => $measureValue,
                ],
                [
                    'unit_name'        => trim("{$measureValue} {$symbolDescription}"),
                    'unit_description' => $symbolDescription,
                    'unit_symbol'      => trim("{$measureValue} {$measureSymbol}"),
                ]
            );

            // 4. Create Item using mass assignment selection
            $itemData = Arr::only($data, [
                'item_code',
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

            $item = $this->itemModel->create($itemData);

            // 5. Create Price via Relationship or Model
            if (filled($data['item_cost'] ?? null)) {
                $item->cost()->create([
                    'price_type' => 'COST',
                    'amount'     => $data['item_cost'],
                    'company_id' => $data['company_id'],
                    'branch_id'  => $data['branch_id'] ?? null,
                ]);
            }

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
}
