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
    public $addItemModal = false,$editItemModal=false;

    // ITEM REGISTRATION DECLARATION
    public $itemCode,$itemName,$itemBarcode,$itemCost,$itemOrderPoint,$optimalStock,$itemCategory,$itemBrand,$itemClass,$itemSubClass,$measureType,$measureValue,$measureSymbol,$isForSale=false;

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
    public function cancelledItemRegister(): void
    {
        $this->addItemModal = true;
    }

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
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

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
        if (!$this->measureType) {
            return false;
        }

        return SystemParameter::where('id', $this->measureType)
            ->where('name', 'UNIT')
            ->exists();
    }

    public function updatedMeasureType($value): void
    {
        if ($this->isUnitType) {
            $this->measureValue = null;
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
                ['index' => 'category_type', 'label' => 'type' , 'sortable' => false],
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
                       ₱ {{$row->cost?->amount ?? '0.00'}}
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
                            <x-ts-dropdown.items text="Edit" icon="pencil-square" />
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
                    <x-ts-dial.items icon="share" label="Share" />
                    <x-ts-dial.items icon="trash" label="Delete" />
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
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                                    @else
                                        <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                                    @endif
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="pencil" label="Edit" />
                            <x-ts-dial.items icon="share" label="Share" />
                            <x-ts-dial.items icon="trash" label="Delete" />
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
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                                    @else
                                        <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                                    @endif
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="pencil" label="Edit" />
                            <x-ts-dial.items icon="share" label="Share" />
                            <x-ts-dial.items icon="trash" label="Delete" />
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
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                                    @else
                                        <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                                    @endif
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="pencil" label="Edit" />
                            <x-ts-dial.items icon="share" label="Share" />
                            <x-ts-dial.items icon="trash" label="Delete" />
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Unit Measure">
                        <div class="flex mb-3">
                            <x-ts-select.native wire:model.live="unitMeasureStatus"
                                    placeholder="All"
                                    :options="[
                                    ['name' => 'All', 'id' => null],
                                    ['name' => 'ACTIVE', 'id' => 'ACTIVE'],
                                    ['name' => 'INACTIVE', 'id' => 'INACTIVE'],
                            ]" select="label:name|value:id" />
                        </div>
                        <x-ts-table :headers="$unitMeasureHeaders" :rows="$this->unitMeasureRows" :$sort paginate persistent loading filter>
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
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                                    @else
                                        <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                                    @endif
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="pencil" label="Edit" />
                            <x-ts-dial.items icon="share" label="Share" />
                            <x-ts-dial.items icon="trash" label="Delete" />
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
                                    <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                                    @if($row->status == 'ACTIVE')
                                        <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                                    @else
                                        <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                                    @endif
                                </x-ts-dropdown>
                            @endinteract
                        </x-ts-table>
                        <x-ts-dial>
                            <x-ts-dial.items icon="pencil" label="Edit" />
                            <x-ts-dial.items icon="share" label="Share" />
                            <x-ts-dial.items icon="trash" label="Delete" />
                        </x-ts-dial>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Unit Conversions">
                        Business
                    </x-ts-tab.items>
                </x-ts-tab>
            </x-ts-tab.items>
            <x-ts-tab.items tab="Rooms">
                Rooms
            </x-ts-tab.items>
            <x-ts-tab.items tab="Restaurant">
                Restaurant
            </x-ts-tab.items>
            <x-ts-tab.items tab="Business">
                Business
            </x-ts-tab.items>
        </x-ts-tab>
    </div>

    {{-- ADD ITEM MODAL --}}
    <x-ts-modal title="ADD ITEM" size="4xl" wire="addItemModal" persistent center>
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
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
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
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                <span x-html="`Add new Brand <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- classification --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemClass"
                    label="Classification *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No classification found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                <span x-html="`Add new Classification <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- sub-class --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.subclassification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemSubClass"
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
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`Add new Symbol <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
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
                <x-ts-button flat>Cancel</x-ts-button>
                <x-ts-button wire:click="saveItemAction">Save</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

        {{-- ADD ITEM MODAL --}}
    <x-ts-modal title="EDIT ITEM" size="4xl" wire="editItemModal" persistent center>
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
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
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
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                <span x-html="`Add new Brand <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- classification --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.classification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemClass"
                    label="Classification *"
                    :placeholders="[
                    'default' => 'Select',
                    'empty'   => 'No classification found',
                    ]" required>
                    <x-slot:after>
                        <div class="px-2 mb-2 flex justify-center items-center">
                            <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                <span x-html="`Add new Classification <b>${search}</b>`"></span>
                            </x-ts-button>
                        </div>
                    </x-slot:after>
                </x-ts-select.styled>

                {{-- sub-class --}}
                <x-ts-select.styled
                    indicator="spinner.bars"
                    :request="route('api.item.active.subclassification', ['company_id' => auth()->user()->branch->company_id ])"
                    select="label:label|value:id|description:description"
                    wire:model="itemSubClass"
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
                            ]" required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`Add new Symbol <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
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
                <x-ts-button flat>Cancel</x-ts-button>
                <x-ts-button wire:click="saveItemAction">Save</x-ts-button>
            </x-slot:footer>
        </x-ts-card>
    </x-ts-modal>

</div>