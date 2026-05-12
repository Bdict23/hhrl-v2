<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use App\Services\Inventory\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;

new class extends Component
{
    use WithPagination;
    use Interactions;

     //inputs
    public $selectedRows = [];
    public $supplier_id;
    public $type_id;
    public $event_id;
    public $productionOrder_id;
    public $term_id;
    public $merchandisePONumber;
    public $notes;
    public $approvedBy;
    public $reviewedBy;
    public $status;
    public $step;
    public $reference;
    public $grand_total = 0.00;

    //selected
    public $selectedItem = [];

    // Mounted Data
    public $purchase_order_id;
    public $puchaseOrderData;

    public function mount($id){
        $this->purchase_order_id = $id;
        $this->puchaseOrderData = PurchaseOrder::find($id);

        $this->fetchData();
    }
     public function fetchData(){
        $this->supplier_id = $this->puchaseOrderData->supplier_id;
        $this->type_id = $this->puchaseOrderData->type_id;
        $this->event_id = $this->puchaseOrderData->event_id;
        $this->productionOrder_id = $this->puchaseOrderData->production_id;
        $this->term_id = $this->puchaseOrderData->term_type_id;
        $this->merchandisePONumber = $this->puchaseOrderData->merchandise_po_number;
        $this->notes = $this->puchaseOrderData->remarks;
        $this->approvedBy = $this->puchaseOrderData->approved_by;
        $this->reviewedBy = $this->puchaseOrderData->reviewed_by;
        $this->status = $this->puchaseOrderData->requisition_status == 'PREPARING' ? 'DRAFT' : 'FINALIZED';
        $this->grand_total = ($this->puchaseOrderData->total_amount);
        $this->reference = $this->puchaseOrderData->requisition_number;
        $currentStep = $this->puchaseOrderData->requisition_status;
        if($currentStep == 'PREPARING'){
            $this->step = '1';
        }elseif($currentStep == 'FOR REVIEW'){
            $this->step = '2';
        }elseif($currentStep == 'FOR APPROVAL'){
            $this->step = '3';
        }elseif($currentStep == 'TO RECEIVE'){
            $this->step = '4';
        }elseif($currentStep == 'PARTIALLY FULFILLED'){
            $this->step = '5';
        }elseif($currentStep == 'COMPLETED'){
            $this->step = '6';
        }else{
            $this->step = '1';
        }


        foreach ($this->puchaseOrderData->purchaseOrderItems as $poItem) {
                $this->selectedRows[] = [
                    'id'               => $poItem->item_id,
                    'item_code'        => $poItem->item->item_code,
                    'item_description' => $poItem->item->item_description,
                    'unit'             => $poItem->item->unit?->unit_symbol ?? 'N/A',
                    'cost'             => (float) ($poItem->item->cost?->amount ?? 0),
                    'price_id'         => $poItem->item->cost?->id ?? null,
                    'quantity'         => $poItem->qty,
                    'sub_total'        => (float) ($poItem->item->cost?->amount ?? 0) * $poItem->qty,
                    // Sub-table data
                    'brand'            => $poItem->item->brand?->brand_name ?? 'N/A',
                    'category'         => $poItem->item->category?->category_name ?? 'N/A',
                    'classification'   => $poItem->item->classification?->classification_name ?? 'N/A',
                    'subClass'         => $poItem->item->subClassification?->classification_name ?? 'N/A',
                ];
            }
            $this->selectedItem = collect($this->selectedRows)->pluck('id')->toArray();
    }
    public function edit(){
        return redirect()->route('purchase-order-edit', ['id' => $this->purchase_order_id]);
    }

    public function with(): array
    {
        return [
            'selectedItemHeader' => [
                ['index' => 'item_code', 'label' => 'Code'],
                ['index' => 'item_description', 'label' => 'Description'],
                ['index' => 'unit', 'label' => 'Unit' , 'sortable' => false],
                ['index' => 'cost', 'label' => 'Cost' , 'sortable' => false],
                ['index' => 'quantity', 'label' => 'Qty' , 'sortable' => false],
                ['index' => 'sub_total', 'label' => 'total',  'sortable' => false],
            ],

        ];
    }
};
?>

<div>
    <div class="flex justify-between mb-3">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                          ['label' => 'Inventory','link' => route('purchase-order-view', ['id' => $purchase_order_id]), 'icon' => 'archive-box' ],
                          ['label' => 'Purchase Summary', 'link' => route('purchase-order-summary'), 'icon' => 'list-bullet'],
                          ['label' => 'View Purchase Order', 'icon' => 'eye', 'color' => 'primary-500'],
              ]"  />
        <label class="text-2xl italic">( {{ $reference }} )</label>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Merchandise P.O #" placeholder="N/A" wire:model="merchandisePONumber" :readonly="true"/>

                    <x-ts-select.styled
                        :request="route('api.active.purchase-type', ['branch_id' => auth()->user()->branch_id])"
                        label="Type"
                        select="label:name|value:id"
                        :placeholders="[
                                    'default' => 'N/A',
                                ]"
                        wire:model='type_id'
                        :readonly="true"
                        />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.supplier.index', ['company_id' => auth()->user()->branch->company_id])"
                        label="Supplier"
                        wire:model='supplier_id'
                        select="label:supp_name|value:id|description:description"
                        :placeholders="[
                                    'default' => 'N/A',
                                ]"
                        :readonly="true"
                    />
                    <x-ts-select.styled
                        :request="route('api.active.purchase-term', ['branch_id' => auth()->user()->branch_id])"
                        select="label:name|value:id|description:description"
                            wire:model="term_id"
                            label="Terms"
                            :placeholders="[
                                    'default' => 'N/A',
                                ]"
                            :readonly="true"
                    />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                    :request="route('api.active.event', ['branch_id' => auth()->user()->branch_id])"
                    wire:model="event_id"
                    select="label:event_name|value:id|description:reference"
                    label="Event"
                    :placeholders="[
                                    'default' => 'N/A',
                                ]"
                    :readonly="true"
                    />

                    <x-ts-select.styled
                    label="Production Order"
                    :request="route('api.active.production-order',['branch_id' => auth()->user()->branch_id])"
                    wire:model="productionOrder_id"
                    select="label:reference|value:id"
                    :placeholders="[
                                    'default' => 'N/A',
                                ]"
                    :readonly="true"
                    />
                </div>
                <div class="p-10 grid gap-3">
                    {{-- <span class="inline-flex items-center justify-center rounded-full border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 w-full">DRAFT</span> --}}
                    <x-ts-badge :text="$status" light  class="w-full justify-center" lg round/>

                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <x-ts-table :headers="$selectedItemHeader" :rows="$selectedRows" striped expandable>

                    @interact('column_quantity', $row)
                       <x-ts-input type="number"
                        sm
                        wire:model.live.debounce.500ms="selectedRows.{{ $loop->index }}.quantity"
                        :readonly="true"
                        />
                    @endinteract

                    @interact('sub_table', $row)
                        <x-ts-table :headers="[
                            ['index' => 'brand', 'label' => 'Brand'],
                            ['index' => 'category', 'label' => 'Category'],
                            ['index' => 'classification', 'label' => 'Classification'],
                            ['index' => 'subClass', 'label' => 'Sub-Classification'],
                        ]"
                        :rows="[[
                            'brand'          => $row['brand'], {{-- Access as array --}}
                            'category'       => $row['category'],
                            'classification' => $row['classification'],
                            'subClass'       => $row['subClass'],
                        ]]" />
                    @endinteract

                </x-ts-table>
                @error('selectedRows')
                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                @enderror
            </x-ts-card>
        </div>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes" :readonly="true"/>
                    <x-ts-stats :number="$grand_total" title="Total Cost" animated>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                            <x-slot:right>
                                @if($status == 'DRAFT')
                                    <x-ts-button icon="pencil-square" class="underline"  flat wire:click='edit'>Edit</x-ts-button>
                                @endif
                                <x-ts-button icon="printer"  flat class="underline-offset-1 underline">Print</x-ts-button>
                            </x-slot:right>
                    </x-ts-stats>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-4 gap-2">
                        <div class="col-span-2">
                            <x-ts-select.styled
                            :request="route('api.active.reviewers', ['branch_id' => auth()->user()->branch_id ])"
                            select="label:fullName|value:id|description:position"
                            wire:model="reviewedBy"
                            label="Reviewed By"
                            :placeholders="[
                                    'default' => 'N/A',
                                ]"
                            :readonly="true"
                            />
                        </div>

                        <div class="col-span-2">
                            <x-ts-select.styled
                                :request="route('api.active.approvers', ['branch_id' => auth()->user()->branch_id])"
                                wire:model="approvedBy"
                                select="label:fullName|value:id|description:position"
                                label="Approved By"
                                :placeholders="[
                                    'default' => 'N/A',
                                ]"
                                :readonly="true"
                                />
                        </div>
                    </div>

                    <div>
                        <x-ts-step wire:model="step" circles>
                            <x-ts-step.items step="1"
                                        title="Create Order"
                                        description="Step 1">
                            </x-ts-tep.items>
                            <x-ts-step.items step="2"
                                        title="For Review"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        title="For Approval"
                                        description="Step 3">
                            </x-ts-step.items>
                            <x-ts-step.items step="4"
                                        title="To Receive"
                                        description="Step 4">
                            </x-ts-step.items>
                            <x-ts-step.items step="5"
                                        title="For Completion"
                                        description="Step 5">
                            </x-ts-step.items>
                            <x-ts-step.items step="6"
                                        completed
                                        title="Completed"
                                        description="Step 6">
                                        <b>Order Completed!</b>
                            </x-ts-step.items>
                        </x-ts-step>
                    </div>
                </div>
            </div>
        </x-ts-card>
    </div>

    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="New" href="/posts/1/edit" navigate />
        <x-ts-dial.items icon="pencil" label="Edit" href="/posts/1/edit" navigate />
        <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
    </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
