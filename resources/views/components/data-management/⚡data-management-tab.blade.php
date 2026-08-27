<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Builder;
use TallStackUi\Traits\Interactions;
use App\Models\Settings\SystemParameter;
use Illuminate\Validation\Rule;




use App\Models\DataManagement\Item;
use App\Models\DataManagement\Brand;
use App\Models\DataManagement\Category;
use App\Models\DataManagement\Classification;
use App\Models\DataManagement\UnitOfMeasure;
use App\Services\DataManagement\ItemService; 


new class extends Component
{
    use WithPagination;
    use Interactions;

    //items declarations
    public ?int $quantity = 8;
    public ?string $search = null;
    public $mainTab = 'Items';
    public  $itemPropTab = 'Categories';
    public  $itemStatus = null;
    public  $categoryStatus = null;
    public  $classificationStatus = null;
    public  $subClassStatus = null;
    public  $unitMeasureStatus = null;
    public  $brandStatus = null;
    public array $sort = ['column' => 'created_at', 'direction' => 'desc',];

    // MODAL
    public 
        $addItemModal = false,
        $editItemModal=false,
        $addItemCategoryModal=false,
        $editItemCategoryModal=false,
        $addItemClassificationModal=false,
        $editItemClassificationModal=false,
        $addItemBrandModal,
        $editItemBrandModal,
        $addItemSubClassModal,
        $editItemSubClassModal;

    // ITEM REGISTRATION DECLARATION
        public 
            $itemCode,
            $itemName,
            $itemBarcode,
            $itemCost,
            $itemOrderPoint,
            $optimalStock,
            $itemCategory,
            $itemBrand,
            $itemClass,
            $itemSubClass,
            $measureType,
            $measureValue,
            $measureSymbol,
            $isForSale=false;
        
    // ITEM UPDATE DECLARATION
        public 
            $itemCodeEdit,
            $itemNameEdit,
            $itemBarcodeEdit,
            $itemCostEdit,
            $itemOrderPointEdit,
            $optimalStockEdit,
            $itemCategoryEdit,
            $itemBrandEdit,
            $itemClassEdit,
            $itemSubClassEdit,
            $measureTypeEdit,
            $measureValueEdit,
            $measureSymbolEdit,
            $item_id,
            $isForSaleEdit=false;

    // CATEGORY REGISTRATION FORM
        public $addCategoryName,$addCategoryDescription;
    // UPDATE CATEGORY FORM
        public $editCategoryName,$editCategoryDescription,$category_id;
    // CLASSIFICATION REGISTRATION
        public $addClassificationName,$addClassificationDescription;
    // UPDATE CLASSIFICATION
        public $editClassificationName,$editClassificationDescription,$classification_id;
    
    // SUBCLASSIFICATION REGISTRATION
        public $addSubClassName,$addSubClassDescription,$addClassParent_id;
    // UPDATE SUBCLASSIFICATION
        public $editSubClassName,$editSubClassDescription,$SubClass_id,$editClassParent_id;
    
    // BRAND REGISTRATION
        public $addBrandName,$addBrandDescription;
    // UPDATE BRAND
        public $editBrandName,$editBrandDescription,$brand_id;
    //
    public function saveItemAction()
    {
        $this->validate([
            'itemCode'       => 'required|unique:items,item_code',
            'itemName'       => 'required',
            'itemOrderPoint' => 'required|numeric',
            'itemCategory'   => 'required|exists:categories,id',
            'itemBrand'      => 'nullable|exists:brands,id',
            'itemClass'      => 'required|exists:classifications,id',
            'itemSubClass'   => 'nullable|exists:classifications,id',
            'measureType'    => 'required|exists:system_parameters,id',
            'measureSymbol'  => 'required',
            'measureValue'   => [
                Rule::requiredIf(fn () => !$this->isUnitType()),
                'nullable',
                'numeric',
            ],
        ]);
        $this->addItemModal = false;
         $this->dialog()
        ->question('Save Item?', 'Are you sure to save this item?')
        ->confirm(
            'Confirm',
            'storeItem', //pass a functio to call
            )
        ->cancel('Cancel', 'cancelledItemRegister')
        ->send();
    }

    public function updateItemAction()
    {
      $this->validate([
            'itemCodeEdit'       => 'required|unique:items,item_code,'. $this->item_id,
            'itemNameEdit'       => 'required',
            'itemOrderPointEdit' => 'required|numeric',
            'itemCategoryEdit'   => 'required|exists:categories,id',
            'itemBrandEdit'      => 'nullable|exists:brands,id',
            'itemClassEdit'      => 'required|exists:classifications,id',
            'itemSubClassEdit'   => 'nullable|exists:classifications,id',
            'measureTypeEdit'    => 'required|exists:system_parameters,id',
            'measureSymbolEdit'  => 'required',
            'measureValueEdit'   => [
                Rule::requiredIf(fn () => !$this->isUnitType()),
                'nullable',
                'numeric',
            ],
        ]);
        $this->editItemModal = false;
         $this->dialog()
        ->question('Update Item?', 'Are you sure to update this item?')
        ->confirm(
            'Confirm',
            'updateItem', //pass a functio to call
            )
        ->cancel('Cancel', 'cancelledItemRegister')
        ->send();  
    }

    // RE-SHOW MODAL
    public function cancelledItemRegister(): void
    {
        $this->addItemModal = true;
    }

    //APPLY ACTION AND SAVE TO DATABASE
    public function storeItem( ItemService $service)
    {
        try {
            $payload = [
                'item_code'         => $this->itemCode,
                'item_description'  => $this->itemName,
                'item_barcode'      => $this->itemBarcode,
                'company_id'        => auth()->user()->branch->company_id,
                'classification_id' => $this->itemClass,
                'sub_class_id'      => $this->itemSubClass,
                'brand_id'          => $this->itemBrand,
                'category_id'       => $this->itemCategory,
                'orderpoint'        => $this->itemOrderPoint,
                'optimal_stock'     => $this->optimalStock,
                'measure_type_id'   => $this->measureType,
                'measure_value'     => $this->measureValue,
                'measure_symbol'    => $this->measureSymbol,
                'is_forsale'        => $this->isForSale,
                'created_by'        =>  auth()->user()->emp_id,
                'item_cost'         => $this->itemCost,
                'branch_id'         => auth()->user()->branch_id,

            ];
            $item = $service->createItem($payload);
            $this->reset([
                'itemCode',
                'itemName',
                'itemBarcode',
                'itemClass',
                'itemSubClass',
                'itemBrand',
                'itemCategory',
                'itemOrderPoint',
                'optimalStock',
                'measureType',
                'measureValue',
                'itemCost',
                'isForSale']);
            $this->toast()->success('Success', "Item {$item->item_description} created successfully!")->send();

        } catch (\Exception $e) {
            $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();

        }
    }

    //APPLY ACTION TO UPDATE ITEM
     public function updateItem( ItemService $service)
    {
        try {
            $payload = [
                'item_code'         => $this->itemCodeEdit,
                'item_description'  => $this->itemNameEdit,
                'item_barcode'      => $this->itemBarcodeEdit,
                'company_id'        => auth()->user()->branch->company_id,
                'classification_id' => $this->itemClassEdit,
                'sub_class_id'      => $this->itemSubClassEdit,
                'brand_id'          => $this->itemBrandEdit,
                'category_id'       => $this->itemCategoryEdit,
                'orderpoint'        => $this->itemOrderPointEdit,
                'optimal_stock'     => $this->optimalStockEdit,
                'measure_type_id'   => $this->measureTypeEdit,
                'measure_value'     => $this->measureValueEdit,
                'measure_symbol'    => $this->measureSymbolEdit,
                'is_forsale'        => $this->isForSaleEdit,
                'created_by'        =>  auth()->user()->emp_id,
                'item_cost'         => $this->itemCostEdit,
                'branch_id'         => auth()->user()->branch_id,
                'item_id'           => $this->item_id,

            ];
            $item = $service->updateItem($payload);
            $this->reset([
                'itemCodeEdit',
                'itemNameEdit',
                'itemBarcodeEdit',
                'itemClassEdit',
                'itemSubClassEdit',
                'itemBrandEdit',
                'itemCategoryEdit',
                'itemOrderPointEdit',
                'optimalStockEdit',
                'measureTypeEdit',
                'measureValueEdit',
                'itemCostEdit',
                'item_id',
                'isForSaleEdit']);
            $this->toast()->success('Success', "Item {$item->item_description} updated successfully!")->send();

        } catch (\Exception $e) {
            $this->toast()->error('Error', 'Something went wrong while updating: ' . $e->getMessage())->send();
        }
    }
    public function updateCategory()
    {
        $this->validate([
                'editCategoryName' => 'required|string|max:50',
                'editCategoryDescription' => 'nullable|string|max:50',
            ]);
            try {
                $service = app(ItemService::class);
                $payload = [
                    'category_name' => $this->editCategoryName,
                    'category_description' => $this->editCategoryDescription,
                    'updated_by' => auth()->user()->emp_id,
                    'id' => $this->category_id,
                ];
                $service->updateCategory($payload);
                $this->editItemCategoryModal = false;
                $this->toast()->success('Success', "Category updated successfully!")->send();
                $this->reset(['editCategoryName','editCategoryDescription','category_id']);
            } catch (\Throwable $th) {
                $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
            }
    }
    public function updateClassification()
    {
        $this->validate([
                'editClassificationName' => 'required|string|max:50',
                'editClassificationDescription' => 'nullable|string|max:50',
            ]);
            try {
                $service = app(ItemService::class);
                $payload = [
                    'classification_name' => $this->editClassificationName,
                    'classification_description' => $this->editClassificationDescription,
                    'updated_by' => auth()->user()->emp_id,
                    'id' => $this->classification_id,
                ];
                $service->updateClassification($payload);
                $this->editItemClassificationModal = false;
                $this->toast()->success('Success', "Classification updated successfully!")->send();
                $this->reset(['editClassificationName','editClassificationDescription','classification_id']);
            } catch (\Throwable $e) {
                $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
            }
    }
    public function updateSubClass()
    {
        $this->validate([
                'editClassParent_id' => 'required|exists:classifications,id',
                'editSubClassName' => 'required|string|max:50',
                'editSubClassDescription' => 'nullable|string|max:50',
            ]);
            try {
                $service = app(ItemService::class);
                $payload = [
                    'class_parent' => $this->editClassParent_id,
                    'classification_name' => $this->editSubClassName,
                    'classification_description' => $this->editSubClassDescription,
                    'updated_by' => auth()->user()->emp_id,
                    'id' => $this->SubClass_id,
                ];
                $service->updateSubClass($payload);
                $this->editItemSubClassModal = false;
                $this->toast()->success('Success', "Sub-Class updated successfully!")->send();
                $this->reset(['editSubClassName','editBrandDescription','SubClass_id','editClassParent_id']);
            } catch (\Throwable $e) {
                $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
            }
    }
    public function updateBrand()
    {
        $this->validate([
                'editBrandName' => 'required|string|max:50',
                'editBrandDescription' => 'nullable|string|max:50',
            ]);
            try {
                $service = app(ItemService::class);
                $payload = [
                    'brand_name' => $this->editBrandName,
                    'brand_description' => $this->editBrandDescription,
                    'updated_by' => auth()->user()->emp_id,
                    'id' => $this->brand_id,
                ];
                $service->updateBrand($payload);
                $this->editItemBrandModal = false;
                $this->toast()->success('Success', "Brand updated successfully!")->send();
                $this->reset(['editBrandName','editBrandDescription','brand_id']);
            } catch (\Throwable $e) {
                $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
            }
    }

    //SET ITEM TO ACTIVE OR INACTIVE
    public function changeItemStatus(int $id)
    {
        try {
             $service = app(ItemService::class);
        $data = $service->changeItemStatus((int)$id);
        $this->toast()->success(
                        'Item Status changed',
                        "Item status successfully changed."
                    )->send();

        } catch (\Throwable $e) {
           $this->toast()
                ->error('Action Failed', 'An error occurred while changing status: ' . $e->getMessage())
                ->send();
        }
       
    }
    public function changeCategoryStatus(int $id)
    {
        try {
            $service = app(ItemService::class);
            $data = $service->changeCategoryStatus((int)$id);
            $this->toast()->success(
                        'Category Status changed',
                        "Category status successfully changed."
                    )->send();
        } catch (\Throwable $e) {
           $this->toast()
                ->error('Action Failed', 'An error occurred while changing status: ' . $e->getMessage())
                ->send();
        }
       
    }
    public function changeClassificationStatus(int $id)
    {
        try {
            $service = app(ItemService::class);
            $data = $service->changeClassificationStatus((int)$id);
            $this->toast()->success(
                        'Classification Status changed',
                        "Classification status successfully changed."
                    )->send();
        } catch (\Throwable $e) {
           $this->toast()
                ->error('Action Failed', 'An error occurred while changing status: ' . $e->getMessage())
                ->send();
        }
       
    }

    public function changeSubClassificationStatus(int $id)
    {
        try {
            $service = app(ItemService::class);
            $data = $service->changeSubClassificationStatus((int)$id);
            $this->toast()->success(
                        'Sub-Classification Status changed',
                        "Sub-Classification status successfully changed."
                    )->send();
        } catch (\Throwable $e) {
           $this->toast()
                ->error('Action Failed', 'An error occurred while changing status: ' . $e->getMessage())
                ->send();
        }
       
    }

    public function changeBrandStatus(int $id)
    {
        try {
            $service = app(ItemService::class);
            $data = $service->changeBrandStatus((int)$id);
            $this->toast()->success(
                        'Brand Status changed',
                        "Brand status successfully changed."
                    )->send();
        } catch (\Throwable $e) {
           $this->toast()
                ->error('Action Failed', 'An error occurred while changing status: ' . $e->getMessage())
                ->send();
        }
       
    }

    // ADD NEW CATEGORY
    public function storeCategory()
    {
        // 1. Validated data ensures only sanitized inputs are passed
        $validated = $this->validate([
            'addCategoryName'        => ['required', 'string', 'max:50'],
            'addCategoryDescription' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            // 2. Cache current user reference to reduce repetitive magic calls
            $user = auth()->user();
            $service = app(ItemService::class);

            $data = $service->addNewCategory([
                'category_name'        => $validated['addCategoryName'],
                'category_description' => $validated['addCategoryDescription'],
                'category_type'        => 'ITEM',
                'company_id'           => $user->branch?->company_id,
                'created_by'           => $user->emp_id,
            ]);

            // 3. UI State Assignments
            if ($this->mainTab === 'Items') {
                if ($this->addItemModal) {
                    $this->itemCategory = $data->id;
                } elseif ($this->editItemModal) {
                    $this->itemCategoryEdit = $data->id;
                }
                 $this->banner()
                ->success("Category '{$data->category_name}' created successfully!")
                ->close()
                ->leave(seconds: 3)
                ->send();
                }else{
                        $this->toast()->success('Success', "Category created successfully!")->send();
                }

            // 4. Reset properties and send notification
            $this->reset(['addCategoryName', 'addCategoryDescription']);
            
           

        } catch (\Throwable $e) {
            // 5. Log the actual exception for developers; show a generic error to the user
            logger()->error('Failed to store category: ' . $e->getMessage(), ['exception' => $e]);

            $this->banner()
                ->error('Something went wrong while saving the category. Please try again.')
                ->send();
        } finally {
            // 6. Ensure the modal closes regardless of success or failure
            $this->addItemCategoryModal = false;
        }
    }
    // ADD NEW CLASSIFICATION
    public function storeClassification()
    {
        $this->validate([
                'addClassificationName' => 'required|string|max:50',
                'addClassificationDescription' => 'nullable|string|max:50',
            ]);
        try {
            $user = auth()->user();
            $service = app(ItemService::class);
            $payload = [
                'classification_name'         => $this->addClassificationName,
                'classification_description'  => $this->addClassificationDescription,
                'company_id'                  => $user->branch->company_id,
                'created_by'                  =>  $user->emp_id,
            ];
            $data = $service->addNewClassification($payload);
            if($this->mainTab == 'Items')
            {
                if($this->addItemModal)
                {$this->itemClass = $data->id;}
                elseif($this->editItemModal) {$this->itemClassEdit = $data->id;}
                $this->banner() 
                ->success('Classification created successfully!')
                ->close()
                ->leave(seconds: 3)
                ->send();
            }else{
                $this->toast()->success('Success', "Classification created successfully!")->send();
            }
            $this->reset(['addClassificationName', 'addClassificationDescription']);

            $this->addItemClassificationModal = false;
        } catch (\Throwable $e) {
            logger()->error('Failed to store category: ' . $e->getMessage(), ['exception' => $e]);
            $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
        }
    }

    // ADD NEW SUBCLASSIFICATION
    public function storeSubClass()
    {
        $this->validate([
                'addClassParent_id' => 'required|exists:classifications,id',
                'addSubClassName' => 'required|string|max:50',
                'addSubClassDescription' => 'nullable|string|max:50',
            ]);
        try {
            $user = auth()->user();
            $service = app(ItemService::class);
            $payload = [
                'class_parent'                => $this->addClassParent_id,
                'classification_name'         => $this->addSubClassName,
                'classification_description'  => $this->addSubClassDescription,
                'company_id'                  => $user->branch->company_id,
                'created_by'                  =>  $user->emp_id,
            ];
            $data = $service->addNewSubClass($payload);
            if($this->mainTab == 'Items')
            {
                if($this->addItemModal)
                {$this->itemSubClass = $data->id;}
                elseif($this->editItemModal) {$this->itemSubClassEdit = $data->id;}
                $this->banner() 
                ->success('Sub-Classification created successfully!')
                ->close()
                ->leave(seconds: 3)
                ->send();
            }else{
                $this->toast()->success('Success', "Sub-Classification created successfully!")->send();
            }
            $this->reset(['addClassParent_id', 'addSubClassName','addSubClassDescription']);

        } catch (\Throwable $e) {
            logger()->error('Failed to store Sub-Classification: ' . $e->getMessage(), ['exception' => $e]);
            $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
        }finally{
            $this->addItemSubClassModal = false;
        }
    }

    // ADD NEW BRAND
    public function storeBrand()
    {
        $this->validate([
                'addBrandName' => 'required|string|max:50',
                'addBrandDescription' => 'nullable|string|max:50',
            ]);
        try {
            $user = auth()->user();
            $service = app(ItemService::class);
            $payload = [
                'brand_name'         => $this->addBrandName,
                'brand_description'  => $this->addBrandDescription,
                'company_id'         => $user->branch->company_id,
                'created_by'         =>  $user->emp_id,
            ];
            $data = $service->addNewBrand($payload);
            if($this->mainTab == 'Items')
            {
                if($this->addItemModal)
                {$this->itemBrand = $data->id;}
                elseif($this->editItemModal) {$this->itemBrandEdit = $data->id;}
                $this->banner() 
                ->success('Brand created successfully!')
                ->close()
                ->leave(seconds: 3)
                ->send();
            }else{
                $this->toast()->success('Success', "Brand created successfully!")->send();
            }
            $this->reset(['addBrandName', 'addBrandDescription']);

        } catch (\Throwable $e) {
            logger()->error('Failed to store brand: ' . $e->getMessage(), ['exception' => $e]);
            $this->banner()->error('Something went wrong while saving:'. $e->getMessage())->send();
        }finally{
            $this->addItemBrandModal = false;
        }
    }

    //COMPUTED
        #[Computed]
        public function itemRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Items')
            {
                return Item::query()
                    ->with(['brand','classification','subClassification','category','unit','cost'])
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('item_description', 'like', "%{$this->search}%");
                    })
                    ->when($this->itemStatus, function (Builder $query) {
                        return $query->where('item_status', $this->itemStatus);
                    })
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                    return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function categoriesRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Item Properties' && $this->itemPropTab == 'Categories' )
            {
                return Category::query()
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('category_name', 'like', "%{$this->search}%");
                    })
                    ->when($this->categoryStatus, function (Builder $query) {
                        return $query->where('status', $this->categoryStatus);
                    })
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->where('category_type', 'ITEM')
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function classificationRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Item Properties' && $this->itemPropTab == 'Classification'  )
            {
                return Classification::query()
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('classification_name', 'like', "%{$this->search}%");
                    })
                    ->when($this->classificationStatus, function (Builder $query) {
                        return $query->where('status', $this->classificationStatus);
                    })
                    ->where('class_parent',  null)
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function subClassificationRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Item Properties' && $this->itemPropTab == 'Sub-classification'  )
            {
                return Classification::query()
                    ->with(['classificationParent'])
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('classification_name', 'like', "%{$this->search}%");
                    })
                    ->where('class_parent', 'IS NOT', null)
                    ->when($this->subClassStatus, function (Builder $query) {
                        return $query->where('status', $this->subClassStatus);
                    })
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function unitMeasureRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Item Properties' && $this->itemPropTab == 'Unit Measure'  )
            {
                return UnitOfMeasure::query()
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('unit_name', 'like', "%{$this->search}%")->orWhere('unit_symbol', 'like',"%{$this->search}%");
                    })
                    ->when($this->unitMeasureStatus, function (Builder $query) {
                        return $query->where('status', $this->unitMeasureStatus);
                    })
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function brandRows(): LengthAwarePaginator
        {
            if($this->mainTab == 'Item Properties' && $this->itemPropTab == 'Brands'  )
            {
                return Brand::query()
                    ->when($this->search, function (Builder $query) {
                        return  $query->where('brand_name', 'like', "%{$this->search}%");
                    })
                    ->when($this->brandStatus, function (Builder $query) {
                        return $query->where('status', $this->brandStatus);
                    })
                    ->where('company_id', Auth::user()->branch->company_id)
                    ->orderBy(...array_values($this->sort))
                    ->paginate($this->quantity)
                    ->withQueryString();
            }else{
                return new LengthAwarePaginator([], 0, $this->quantity ?? 10);
            }
        }

        #[Computed]
        public function isUnitType(): bool
        {
            $typeId = $this->measureType ?: $this->measureTypeEdit;

            if (!$typeId) {
                return false;
            }

            return SystemParameter::where('id', $typeId)
                ->where('name', 'UNIT')
                ->exists();
        }
    // END OF COMPUTED

    public function updatedMeasureType($value): void
    {
        if ($this->isUnitType) {
            $this->measureValue = null;
        }
    }
        public function updatedMeasureTypeEdit($value): void
    {
        if ($this->isUnitType) {
            $this->measureValueEdit = null;
        }
    }

    //EDIT ACTION
        public function editItem(int $id)
        {
            $item = Item::with('unit')->findOrFail($id);
            $this->item_id = $id;
            if($item)
            {
                $this->itemCodeEdit = $item->item_code;
                $this->itemNameEdit = $item->item_description;
                $this->itemBarcodeEdit = $item->item_barcode;
                $this->itemClassEdit = $item->classification_id;
                $this->itemSubClassEdit = $item->sub_class_id;
                $this->itemBrandEdit = $item->brand_id;
                $this->itemCategoryEdit = $item->category_id;
                $this->itemOrderPointEdit = $item->orderpoint;
                $this->optimalStockEdit = $item->optimal_stock;
                $this->isForSaleEdit = $item->is_forsale;

                $this->measureTypeEdit = $item->unit->measure_type_id;
                $this->measureSymbolEdit = $item->unit->measure_symbol;
                $this->measureValueEdit= $item->unit->measure_value == 0.00 ? null : $item->unit->measure_value;
                $this->editItemModal = true;

            }

        }
        public function editCategory(int $id)
        {
            $cat = Category::findOrFail($id);
            $this->category_id = $id;
            if($cat)
            {
                $this->editCategoryName = $cat->category_name;
                $this->editCategoryDescription = $cat->category_description;
                $this->editItemCategoryModal = true;
            }

        }
        public function editClassification(int $id)
        {
            $cat = Classification::findOrFail($id);
            $this->classification_id = $id;
            if($cat)
            {
                $this->editClassificationName = $cat->classification_name;
                $this->editClassificationDescription = $cat->classification_description;
                $this->editItemClassificationModal = true;
            }

        }
        public function editSubClass(int $id)
        {
            $cat = Classification::findOrFail($id);
            $this->SubClass_id = $id;
            if($cat)
            {
                $this->editClassParent_id = $cat->class_parent;
                $this->editSubClassName = $cat->classification_name;
                $this->editSubClassDescription = $cat->classification_description;
                $this->editItemSubClassModal = true;
            }

        }
        public function editBrand(int $id)
        {
            $cat = Brand::findOrFail($id);
            $this->brand_id = $id;
            if($cat)
            {
                $this->editBrandName = $cat->brand_name;
                $this->editBrandDescription = $cat->brand_description;
                $this->editItemBrandModal = true;
            }

        }

public function with(): array
    {

        return [
            'itemHeaders' => [
                ['index' => 'item_status', 'label' => 'Status'],
                ['index' => 'item_code', 'label' => 'item code', 'sortable' => false],
                ['index' => 'item_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'uom_id', 'label' => 'unit' , 'sortable' => false],
                ['index' => 'optimal_stock', 'label' => 'optimal stock',  'sortable' => false],
                ['index' => 'orderpoint', 'label' => 're-order target',  'sortable' => false],
                ['index' => 'is_forsale', 'label' => 'for sale',  'sortable' => false],
                ['index' => 'cost', 'label' => 'cost',  'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'categoriesHeaders' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'category_name', 'label' => 'category name', 'sortable' => false],
                ['index' => 'category_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'classificationHeaders' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'classification_name', 'label' => 'class name', 'sortable' => false],
                ['index' => 'classification_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'subClassificationHeaders' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'class_parent', 'label' => 'class. parent name', 'sortable' => false],
                ['index' => 'classification_name', 'label' => 'sub-class. name', 'sortable' => false],
                ['index' => 'classification_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'unitMeasureHeaders' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'unit_name', 'label' => 'unit name', 'sortable' => false],
                ['index' => 'unit_symbol', 'label' => 'symbol' , 'sortable' => false],
                ['index' => 'action', 'label' => 'action'],

            ],
            'brandHeaders' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'brand_name', 'label' => 'item code', 'sortable' => false],
                ['index' => 'brand_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'unitConversionHeaders' => [
                ['index' => 'item_status', 'label' => 'Status'],
                ['index' => 'item_code', 'label' => 'item code', 'sortable' => false],
                ['index' => 'item_description', 'label' => 'description', 'sortable' => false],
                ['index' => 'uom_id', 'label' => 'unit' , 'sortable' => false],
                ['index' => 'measurement_type', 'label' => 'measured by' , 'sortable' => false],
                ['index' => 'optimal_stock', 'label' => 'optimal stock',  'sortable' => false],
                ['index' => 'orderpoint', 'label' => 're-order target',  'sortable' => false],
                ['index' => 'is_forsale', 'label' => 'for sale',  'sortable' => false],
                ['index' => 'created_at', 'label' => 'created date'],
                ['index' => 'action', 'label' => 'action'],

            ],
            'unitConversionRows' => Item::query()
                ->with(['brand','classification','subClassification','category'])
                ->when($this->search, function (Builder $query) {
                    return  $query->where('item_description', 'like', "%{$this->search}%");
                })
                ->when($this->itemStatus, function (Builder $query) {
                    return $query->where('item_status', $this->itemStatus);
                })
                ->where('company_id', Auth::user()->branch->company_id)
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
        ];
    }
};
?>

<div>
    <div >
        <x-ts-tab wire:model.live="mainTab">
            <x-ts-tab.items tab="Items">
                <div class="flex mb-3">
                    <x-ts-select.native wire:model.live="itemStatus"
                                placeholder="All"
                                :options="[
                                ['name' => 'All', 'id' => null],
                                ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                        ]" select="label:name|value:id" />
                </div>
                <x-ts-table :headers="$itemHeaders" :rows="$this->itemRows" :$sort paginate persistent loading filter expandable>
                    @interact('column_item_status', $row)
                        <div class="flex items-center gap-2">
                            @if($row->item_status == 'ACTIVE')
                                <x-ts-badge :text="$row->item_status" color="green" />
                            @elseif($row->item_status == 'INACTIVE')
                                <x-ts-badge :text="$row->item_status" color="red" />
                            @endif
                        </div>
                    @endinteract
                    @interact('column_uom_id',$row)
                        {{$row->unit?->unit_symbol}}
                    @endinteract
                    @interact('column_cost',$row)
                       ₱ {{number_format($row->cost?->amount,2)}}
                    @endinteract
                    @interact('column_is_forsale',$row)
                         <div class="flex items-center gap-2">
                            @if($row->is_forsale == 1)
                                <x-ts-badge text="Yes" color="fuchsia" outline/>
                            @elseif($row->is_forsale == 0)
                                <x-ts-badge text="No" color="cyan" outline/>
                            @endif
                        </div>
                    @endinteract
                    @interact('column_created_at', $row)
                        {{ ($row->created_at)->format('M. d, Y')}}
                    @endinteract
                    @interact('column_action', $row)
                        <x-ts-dropdown icon="ellipsis-vertical" static lg>
                            <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="editItem({{$row->id}})"/>
                            <x-ts-dropdown.items 
                                :text="$row->item_status == 'ACTIVE' ? 'Set INACTIVE' : 'Set ACTIVE'" 
                                :icon="$row->item_status == 'ACTIVE' ? 'x-mark' : 'check'" 
                                separator 
                                wire:click="changeItemStatus({{ $row->id }})" 
                            />
                        </x-ts-dropdown>
                    @endinteract
                    @interact('sub_table', $row)
                        @php
                            $headers = [
                                ['index' => 'brand_id', 'label' => 'brand'],
                                ['index' => 'classification_id', 'label' => 'classification'],
                                ['index' => 'sub_class_id', 'label' => 'sub-classification'],
                                ['index' => 'category_id', 'label' => 'category'],
                            ];

                            // Wrap the row associative array in an outer array [ ... ]
                            $rows = [
                                [
                                    'brand_id'          => $row->brand?->brand_name ?? 'N/A',
                                    'classification_id' => $row->classification?->classification_name ?? 'N/A',
                                    'sub_class_id'      => $row->subClassification?->classification_name ?? 'N/A',
                                    'category_id'       => $row->category?->category_name ?? 'N/A',
                                ]
                            ];
                        @endphp
                        <x-ts-table :headers="$headers" :rows="$rows"  compact/>
                    @endinteract
                </x-ts-table>
                <x-ts-dial>
                    <x-ts-dial.items icon="plus" label="Add Item"  wire:click="$toggle('addItemModal')"/>
                </x-ts-dial>
            </x-ts-tab.items>
            <x-ts-tab.items tab="Item Properties">
                <x-ts-tab wire:model.live="itemPropTab" shadowless >
                    <x-ts-tab.items tab="Categories">
                        <div class="flex mb-3">
                            <x-ts-select.native wire:model.live="categoryStatus"
                                    placeholder="All"
                                    :options="[
                                    ['name' => 'All', 'id' => null],
                                    ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                    ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                            ]" select="label:name|value:id" />
                        </div>
                        <x-ts-table :headers="$categoriesHeaders" :rows="$this->categoriesRows" :$sort paginate persistent loading filter>
                            @interact('column_status', $row)
                                <div class="flex items-center gap-2">
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-badge :text="$row->status" color="green" />
                                    @elseif($row->status == 'INACTIVE')
                                        <x-ts-badge :text="$row->status" color="red" />
                                    @endif
                                </div>
                            @endinteract
                            @interact('column_created_at', $row)
                                {{ ($row->created_at)->format('M. d, Y')}}
                            @endinteract
                            @interact('column_action', $row)
                                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="editCategory({{$row->id}})"/>
                                        <x-ts-dropdown.items 
                                            :text="$row->status == 'ACTIVE' ? 'Set INACTIVE' : 'Set ACTIVE'" 
                                            :icon="$row->status == 'ACTIVE' ? 'x-mark' : 'check'" 
                                            separator 
                                            wire:click="changeCategoryStatus({{ $row->id }})" 
                                        />
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="plus" label="Add Cegory" wire:click="$toggle('addItemCategoryModal')"/>
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Classification">
                        <div class="flex mb-3">
                            <x-ts-select.native wire:model.live="classificationStatus"
                                    placeholder="All"
                                    :options="[
                                    ['name' => 'All', 'id' => null],
                                    ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                    ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                            ]" select="label:name|value:id" />
                        </div>
                        <x-ts-table :headers="$classificationHeaders" :rows="$this->classificationRows" :$sort paginate persistent loading filter>
                            @interact('column_status', $row)
                                <div class="flex items-center gap-2">
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-badge :text="$row->status" color="green" />
                                    @elseif($row->status == 'INACTIVE')
                                        <x-ts-badge :text="$row->status" color="red" />
                                    @endif
                                </div>
                            @endinteract
                            @interact('column_created_at', $row)
                                {{ ($row->created_at)->format('M. d, Y')}}
                            @endinteract
                            @interact('column_action', $row)
                                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="editClassification({{$row->id}})"/>
                                        <x-ts-dropdown.items 
                                            :text="$row->status == 'ACTIVE' ? 'Set INACTIVE' : 'Set ACTIVE'" 
                                            :icon="$row->status == 'ACTIVE' ? 'x-mark' : 'check'" 
                                            separator 
                                            wire:click="changeClassificationStatus({{ $row->id }})" 
                                        />
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="plus" label="Add Classification" wire:click="$toggle('addItemClassificationModal')"/>
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Sub-classification">
                        <div class="flex mb-3">
                            <x-ts-select.native wire:model.live="subClassStatus"
                                    placeholder="All"
                                    :options="[
                                    ['name' => 'All', 'id' => null],
                                    ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                    ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                            ]" select="label:name|value:id" />
                        </div>
                        <x-ts-table :headers="$subClassificationHeaders" :rows="$this->subClassificationRows" :$sort paginate persistent loading filter>
                            @interact('column_status', $row)
                                <div class="flex items-center gap-2">
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-badge :text="$row->status" color="green" />
                                    @elseif($row->status == 'INACTIVE')
                                        <x-ts-badge :text="$row->status" color="red" />
                                    @endif
                                </div>
                            @endinteract
                            @interact('column_class_parent', $row)
                                {{ $row->classificationParent?->classification_name }}
                            @endinteract
                            @interact('column_created_at', $row)
                                {{ ($row->created_at)->format('M. d, Y')}}
                            @endinteract
                            @interact('column_action', $row)
                                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="editSubClass({{$row->id}})"/>
                                    <x-ts-dropdown.items 
                                            :text="$row->status == 'ACTIVE' ? 'Set INACTIVE' : 'Set ACTIVE'" 
                                            :icon="$row->status == 'ACTIVE' ? 'x-mark' : 'check'" 
                                            separator 
                                            wire:click="changeSubClassificationStatus({{ $row->id }})" 
                                        />
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="plus" label="Add Sub-Classification" wire:click="$toggle('addItemSubClassModal')" />
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Brands">
                        <div class="flex mb-3">
                            <x-ts-select.native wire:model.live="brandStatus"
                                    placeholder="All"
                                    :options="[
                                    ['name' => 'All', 'id' => null],
                                    ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                    ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                            ]" select="label:name|value:id" />
                        </div>
                        <x-ts-table :headers="$brandHeaders" :rows="$this->brandRows" :$sort paginate persistent loading filter>
                            @interact('column_status', $row)
                                <div class="flex items-center gap-2">
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-badge :text="$row->status" color="green" />
                                    @elseif($row->status == 'INACTIVE')
                                        <x-ts-badge :text="$row->status" color="red" />
                                    @endif
                                </div>
                            @endinteract
                            @interact('column_created_at', $row)
                                {{ ($row->created_at)->format('M. d, Y')}}
                            @endinteract
                            @interact('column_action', $row)
                                <x-ts-dropdown icon="ellipsis-vertical" static lg>
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="editBrand({{$row->id}})"/>
                                    <x-ts-dropdown.items 
                                            :text="$row->status == 'ACTIVE' ? 'Set INACTIVE' : 'Set ACTIVE'" 
                                            :icon="$row->status == 'ACTIVE' ? 'x-mark' : 'check'" 
                                            separator 
                                            wire:click="changeBrandStatus({{ $row->id }})" 
                                        />
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="plus" label="Add Brand" wire:click="$toggle('addItemBrandModal')"/>
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Unit Conversions">
                        Not yet available
                    </x-ts-tab.items>
                </x-ts-tab>
            </x-ts-tab.items>
            <x-ts-tab.items tab="Rooms">
                Not yet available
            </x-ts-tab.items>
            <x-ts-tab.items tab="Restaurant">
                Not yet available
            </x-ts-tab.items>
            <x-ts-tab.items tab="Business">
                Not yet available
            </x-ts-tab.items>
        </x-ts-tab>
    </div>

    {{-- ADD ITEM MODAL --}}
    <x-ts-modal title="ADD ITEM" size="4xl" wire="addItemModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid grid-cols-2 gap-3">
                <x-ts-input label="SKU / Item Code *" wire:model="itemCode"/>
                <x-ts-input label="Name *" wire:model="itemName"/>
                <x-ts-input label="Barcode Value" wire:model="itemBarcode"/>
                <x-ts-currency decimal label="Cost" clearable currency wire:model="itemCost"/>
                <x-ts-number label="Re-order Point *" wire:model="itemOrderPoint"/>
                <x-ts-number label="Optimal stock" hint="Default: (No limit)" wire:model="optimalStock"/>
                {{-- category --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.categories', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemCategory"
                    label="Category *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No categoies found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemCategoryModal')">
                                <span x-html="`Add new Category <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- brand --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.brand', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemBrand"
                    label="Brand"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No brand found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemBrandModal')">
                                <span x-html="`Add new Brand <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                <div wire:key="{{$itemClass}}" class="grid grid-cols-2 col-span-2 gap-3">
                    {{-- classification --}}
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                            select="label:label|value:id|description:description"
                            wire:model.live="itemClass"
                            label="Classification *"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No classification found',
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemClassificationModal')">
                                        <span x-html="`Add new Classification <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                    
                    {{-- sub-class --}}
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.active.subclassification', ['parent_id' =>  $itemClass])"
                            select="label:label|value:id|description:description"
                            wire:model="itemSubClass"
                            label="Sub-classification"
                            :disabled="!$itemClass"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No Sub-classification found',
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemSubClassModal')">
                                        <span x-html="`add new sub-class <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                </div>
                <div class="grid grid-cols-3 col-span-2 gap-3">
                    <div wire:key="{{$measureType}}" class="grid col-span-2 grid-cols-2 gap-3">
                        <x-ts-select.styled 
                            label="Measured type *"
                            placeholder="Select"
                            wire:model.live="measureType"
                            hint="You can choose weight, unit ,volume or length"
                            :request="route('api.item.measuredType')"
                            select="value:id" 
                        />
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.measuredSymbol', ['measure_type_id' => $measureType])"
                            select="label:label|value:label|description:description"
                            :disabled="!$measureType"
                            wire:model="measureSymbol"
                            label="Symbol *"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No symbol found',
                            ]" required/>
                    </div>
                    <x-ts-number 
                        :label="$this->isUnitType ? 'Measured Value' : 'Measured Value *'" 
                        :disabled="$this->isUnitType || !$measureType" 
                        wire:model="measureValue"
                    />
                </div>
               <div class="col-span-2">
                <x-ts-checkbox.group wire:model="isForSale" list :options="[ ['label' => 'Available for sale', 'value' => 'newsletter']]" />
               </div>

            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('addItemModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="saveItemAction">Save</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

    {{-- EDIT ITEM MODAL --}}
    <x-ts-modal title="EDIT ITEM" size="4xl" wire="editItemModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid grid-cols-2 gap-3">
                <x-ts-input label="SKU / Item Code *" wire:model="itemCodeEdit"/>
                <x-ts-input label="Name *" wire:model="itemNameEdit"/>
                <div class="col-span-2">
                    <x-ts-input label="Barcode Value" wire:model="itemBarcodeEdit"/>
                </div>
                <x-ts-number label="Re-order Point *" wire:model="itemOrderPointEdit"/>
                <x-ts-number label="Optimal stock" hint="Default: (No limit)" wire:model="optimalStockEdit"/>
                {{-- category --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.categories', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemCategoryEdit"
                    label="Category *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No categoies found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemCategoryModal')">
                                <span x-html="`Add new Category <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- brand --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.brand', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemBrandEdit"
                    label="Brand"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No brand found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemBrandModal')">
                                <span x-html="`Add new Brand <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>
                
                <div wire:key="{{$itemClassEdit}}" class="grid grid-cols-2 col-span-2 gap-3">
                    {{-- classification --}}
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                            select="label:label|value:id|description:description"
                            wire:model.live="itemClassEdit"
                            label="Classification *"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No classification found',
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemClassificationModal')">
                                        <span x-html="`Add new Classification <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>

                    {{-- sub-class --}}
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.active.subclassification', ['parent_id' =>  $itemClassEdit])"
                            select="label:label|value:id|description:description"
                            wire:model="itemSubClassEdit"
                            :disabled="!$itemClassEdit"
                            label="Sub-classification"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No Sub-classification found',
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`add new sub-class <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                </div>
                <div class="grid grid-cols-3 col-span-2 gap-3">
                    <div wire:key="{{$measureTypeEdit}}" class="grid col-span-2 grid-cols-2 gap-3">
                        <x-ts-select.styled 
                            label="Measured type *"
                            placeholder="Select"
                            wire:model.live="measureTypeEdit"
                            hint="You can choose weight, unit ,volume or length"
                            :request="route('api.item.measuredType')"
                            select="value:id" 
                        />
                        <x-ts-select.styled
                            indicator="spinner.bars"
                            :request="route('api.item.measuredSymbol', ['measure_type_id' => $measureTypeEdit])"
                            select="label:label|value:label|description:description"
                            :disabled="!$measureTypeEdit"
                            wire:model="measureSymbolEdit"
                            label="Symbol *"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No symbol found',
                            ]" required/>
                    </div>
                    <x-ts-number 
                        :label="$this->isUnitType ? 'Measured Value' : 'Measured Value *'" 
                        :disabled="$this->isUnitType || !$measureType" 
                        wire:model="measureValueEdit"
                    />
                </div>
               <div class="col-span-2">
                <x-ts-checkbox.group wire:model="isForSaleEdit" list :options="[ ['label' => 'Available for sale', 'value' => 'newsletter']]" />
               </div>

            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('editItemModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="updateItemAction">UPDATE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>


    {{-- ADD CATEGORY MODAL --}}
    <x-ts-modal title="ADD ITEM CATEGORY" size="4xl" wire="addItemCategoryModal" persistent center>
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Category name *" wire:model="addCategoryName"/>
                <x-ts-textarea maxlength="50" count label="Category Description" hint="Insert the description" wire:model="addCategoryDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('addItemCategoryModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="storeCategory">SAVE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>
    {{-- EDIT CATEGORY MODAL --}}
    <x-ts-modal title="EDIT ITEM CATEGORY" size="4xl" wire="editItemCategoryModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Category name *" wire:model="editCategoryName"/>
                <x-ts-textarea maxlength="50" count label="Category Description" hint="Insert the description" wire:model="editCategoryDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('editItemCategoryModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="updateCategory">UPDATE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

     {{-- ADD CLASSIFICATION MODAL --}}
    <x-ts-modal title="ADD ITEM CLASSIFICATION" size="4xl" wire="addItemClassificationModal" persistent center>
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Classification name *" wire:model="addClassificationName"/>
                <x-ts-textarea maxlength="50" count label="Classification Description" hint="Insert the description" wire:model="addClassificationDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('addItemClassificationModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="storeClassification">SAVE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>
    {{-- EDIT CLASSIFICATION MODAL --}}
    <x-ts-modal title="EDIT ITEM CLASSIFICATION" size="4xl" wire="editItemClassificationModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Classification name *" wire:model="editClassificationName"/>
                <x-ts-textarea maxlength="50" count label="Classification Description" hint="Insert the description" wire:model="editClassificationDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('editItemClassificationModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="updateClassification">UPDATE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

     {{-- ADD SUBCLASSIFICATION MODAL --}}
    <x-ts-modal title="ADD ITEM SUB-CLASSIFICATION" size="4xl" wire="addItemSubClassModal" persistent center>
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="addClassParent_id"
                    label="Parent Classification *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No classification found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemClassificationModal')">
                                <span x-html="`Add new Classification <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>
                <x-ts-input label="Sub-Class name *" wire:model="addSubClassName"/>
                <x-ts-textarea maxlength="50" count label="Classification Description" hint="Insert the description" wire:model="addSubClassDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('addItemSubClassModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="storeSubClass">SAVE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>
    {{-- EDIT SUBCLASSIFICATION MODAL --}}
    <x-ts-modal title="EDIT ITEM SUB-CLASSIFICATION" size="4xl" wire="editItemSubClassModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="editClassParent_id"
                    label="Parent Classification *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No classification found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })" wire:click="$toggle('addItemClassificationModal')">
                                <span x-html="`Add new Classification <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>
                <x-ts-input label="Sub-Class name *" wire:model="editSubClassName"/>
                <x-ts-textarea maxlength="50" count label="Sub-Class Description" hint="Insert the description" wire:model="editSubClassDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('editItemSubClassModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="updateSubClass">UPDATE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>


     {{-- ADD BRAND MODAL --}}
    <x-ts-modal title="ADD ITEM BRAND" size="4xl" wire="addItemBrandModal" persistent center>
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Brand name *" wire:model="addBrandName"/>
                <x-ts-textarea maxlength="50" count label="Brand Description" hint="Insert the description" wire:model="addBrandDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('addItemBrandModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="storeBrand">SAVE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>
    {{-- EDIT BRAND MODAL --}}
    <x-ts-modal title="EDIT ITEM BRAND" size="4xl" wire="editItemBrandModal" persistent center>
        <x-ts-banner wire close /> 
        <x-ts-card shadowless loading>
            <div class="grid gap-3">
                <x-ts-input label="Brand name *" wire:model="editBrandName"/>
                <x-ts-textarea maxlength="50" count label="Brand Description" hint="Insert the description" wire:model="editBrandDescription"/>
            </div>
            <x-slot:footer >
                <x-ts-button flat wire:click="$toggle('editItemBrandModal')">Cancel</x-ts-button>
                <x-ts-button wire:click="updateBrand">UPDATE</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

</div>