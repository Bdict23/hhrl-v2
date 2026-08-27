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



class ItemService
{
    protected $itemModel;
    protected $measureModel;
    protected $systemParameterModel;
    protected $priceModel;
    protected $categoryModel;
    protected $classificationModel;
    protected $brandModel;

    public function __construct(
        Item $itemModel,
        UnitOfMeasure $measureModel,
        SystemParameter $systemParameterModel,
        Price $priceModel,
        Category $categoryModel,
        Classification $classificationModel,
        Brand $brandModel
    ) {
        $this->itemModel = $itemModel;
        $this->measureModel = $measureModel;
        $this->systemParameterModel = $systemParameterModel;
        $this->priceModel = $priceModel;
        $this->categoryModel = $categoryModel;
        $this->classificationModel = $classificationModel;
        $this->brandModel = $brandModel;
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

    public function updateItem(array $data): Item
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
            $item = $this->itemModel->findOrFail($data['item_id']);
            $item->update($itemData);


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
            $category = $this->categoryModel->create($data);;
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
                'updated_by',
            ]);
            $category->update($itemData);;
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
            $classification = $this->classificationModel->create($data);;
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
            $classification->update($itemData);;
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
            $subClass = $this->classificationModel->create($data);;
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
            $subClass->update($itemData);;
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
            $brand = $this->brandModel->create($data);;
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
            $brand->update($itemData);;
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
}
