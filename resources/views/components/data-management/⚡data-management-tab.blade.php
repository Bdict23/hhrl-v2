<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Builder;


use App\Models\DataManagement\Item;
use App\Models\DataManagement\Brand;
use App\Models\DataManagement\Category;
use App\Models\DataManagement\Classification;
use App\Models\DataManagement\UnitOfMeasure;

new class extends Component
{
    use WithPagination;

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

    
    #[Computed]
    public function itemRows(): LengthAwarePaginator
    {
        if($this->mainTab == 'Items')
        {
            return Item::query()
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


public function with(): array
    {

        return [
            'itemHeaders' => [
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
                    @interact('column_created_at', $row)
                        {{ ($row->created_at)->format('M. d, Y')}}
                    @endinteract
                    @interact('column_action', $row)
                        <x-ts-dropdown icon="ellipsis-vertical" static lg>
                            <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            @if($row->item_status == 'ACTIVE')
                                <x-ts-dropdown.items text="Set INACTIVE" separator icon="x-circle" />
                            @else
                                <x-ts-dropdown.items text="Set ACTIVE"  separator icon="check-circle" />
                            @endif
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
                    <x-ts-dial.items icon="pencil" label="Edit" />
                    <x-ts-dial.items icon="share" label="Share" />
                    <x-ts-dial.items icon="trash" label="Delete" />
                </x-ts-dial>
            </x-ts-tab.items>
            <x-ts-tab.items tab="Item Properties">
                <x-ts-tab wire:model.live="itemPropTab" shadowless bordered>
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
</div>