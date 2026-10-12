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
    public bool $printPreviewModal = false;

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

    public function exportPdf()
    {
        $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('assets/images/1772440510.png')));
        $supplierModel = \App\Models\Business\Supplier::find($this->supplier_id);
        $typeModel = \App\Models\Settings\SystemParameter::find($this->type_id);
        $termModel = \App\Models\Settings\SystemParameter::find($this->term_id);
        $eventModel = \App\Models\BanquetEvent\Event::find($this->event_id);
        $reviewerEmp = \App\Models\Business\Employee::find($this->reviewedBy);
        $approverEmp = \App\Models\Business\Employee::find($this->approvedBy);
        $preparerEmp = $this->puchaseOrderData?->preparedBy ?? (auth()->user()->employee);

        return response()->streamDownload(function () use ($logo, $supplierModel, $typeModel, $termModel, $eventModel, $reviewerEmp, $approverEmp, $preparerEmp) {
            echo \Spatie\LaravelPdf\Facades\Pdf::view('pdf.purchase-order', [
                'reference' => $this->reference,
                'merchandisePONumber' => $this->merchandisePONumber,
                'status' => $this->status,
                'created_at' => $this->puchaseOrderData?->created_at,
                'supplier' => $supplierModel,
                'type' => $typeModel,
                'term' => $termModel,
                'event' => $eventModel,
                'selectedRows' => $this->selectedRows,
                'grand_total' => $this->grand_total,
                'notes' => $this->notes,
                'preparer' => $preparerEmp,
                'reviewer' => $reviewerEmp,
                'approver' => $approverEmp,
                'logo' => $logo,
                'company' => auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure',
                'branch' => auth()->user()->branch?->branch_name ?? 'Main Branch',
                'branch_address' => auth()->user()->branch?->branch_address ?? '',
            ])->format('a4')->generatePdfContent();
        }, 'Purchase-Order-' . $this->reference . '.pdf');
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
                    <x-ts-badge :text="$status" light  class="w-auto h-fit justify-center" lg round/>

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
                    <x-ts-stats number="{{$grand_total}}" title="Total Cost">
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                            <x-slot:right>
                                @if($status == 'DRAFT')
                                    <x-ts-button icon="pencil-square" class="underline"  flat  wire:click="edit()">Edit</x-ts-button>
                                @endif
                                <x-ts-button icon="document-arrow-down" flat class="underline-offset-1 underline cursor-pointer" wire:click="$set('printPreviewModal', true)">Export to PDF</x-ts-button>
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

    {{-- PRINTABLE PREVIEW MODAL --}}
    <x-ts-modal wire="printPreviewModal" size="6xl" center>
        <x-slot:title>
            <div class="flex items-center gap-2">
                <x-ts-icon name="document-arrow-down" class="w-5 h-5 text-primary-600" />
                <span class="font-bold text-slate-800 dark:text-slate-100">Export to PDF - Purchase Order ({{ $reference }})</span>
            </div>
        </x-slot:title>

        <div class="space-y-4">
            {{-- Toolbar / Screen Controls (Hidden in Print) --}}
            <div class="flex items-center justify-between p-3 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 print:hidden">
                <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <x-ts-badge text="PDF Ready" color="green" light />
                    <span>Formatted for export via Spatie Laravel PDF.</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ts-button flat sm text="Close" wire:click="$set('printPreviewModal', false)" />
                    <x-ts-button sm icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf" color="primary" />
                </div>
            </div>

            {{-- Document Sheet --}}
            <div id="printable-po-preview" class="bg-white text-slate-900 p-6 sm:p-10 rounded-2xl border border-slate-200 shadow-sm print:border-none print:shadow-none print:p-0 print:m-0 print:w-full">
                {{-- Header with Company Logo --}}
                <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5 mb-6">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('assets/images/1772440510.png') }}" alt="Company Logo" class="h-20 w-auto object-contain" />
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wide text-slate-900">
                                {{ auth()->user()->branch?->company?->company_name ?? '' }}
                            </h1>
                            <p class="text-xs text-slate-600 font-medium">
                                {{ auth()->user()->branch?->branch_name ?? 'Main Branch' }}
                                @if(auth()->user()->branch?->branch_address)
                                    • {{ auth()->user()->branch->branch_address }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 font-medium">
                                Official Procurement & Purchase Requisition
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">
                            PURCHASE ORDER
                        </span>
                        <div class="mt-2 text-lg font-black font-mono text-slate-900">
                            {{ $reference }}
                        </div>
                        <div class="text-xs text-slate-500">
                            Date: {{ $puchaseOrderData?->created_at ? $puchaseOrderData->created_at->format('M d, Y') : now()->format('M d, Y') }}
                        </div>
                        <div class="mt-1">
                            <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded {{ $status === 'FINALIZED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $status }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Order Details Meta (NO logged in user section) --}}
                @php
                    $supplierModel = \App\Models\Business\Supplier::find($supplier_id);
                    $typeModel = \App\Models\Settings\SystemParameter::find($type_id);
                    $termModel = \App\Models\Settings\SystemParameter::find($term_id);
                    $eventModel = \App\Models\BanquetEvent\Event::find($event_id);
                @endphp
                <div class="grid grid-cols-2 gap-4 mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                            Supplier Information
                        </h4>
                        <div class="space-y-1">
                            <div><span class="text-slate-500 font-medium">Supplier:</span> <strong class="text-slate-900">{{ $supplierModel?->supp_name ?? 'N/A' }}</strong></div>
                            @if($supplierModel?->contact_no_1)
                                <div><span class="text-slate-500 font-medium">Contact:</span> <span class="text-slate-800">{{ $supplierModel->contact_person ? $supplierModel->contact_person . ' (' . $supplierModel->contact_no_1 . ')' : $supplierModel->contact_no_1 }}</span></div>
                            @endif
                            @if($supplierModel?->supp_address)
                                <div><span class="text-slate-500 font-medium">Address:</span> <span class="text-slate-800">{{ $supplierModel->supp_address }}</span></div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                            Requisition Details
                        </h4>
                        <div class="space-y-1">
                            <div><span class="text-slate-500 font-medium">Merchandise P.O #:</span> <span class="text-slate-800 font-mono">{{ $merchandisePONumber ?: 'N/A' }}</span></div>
                            <div><span class="text-slate-500 font-medium">Type / Terms:</span> <span class="text-slate-800">{{ $typeModel?->name ?? 'N/A' }} • {{ $termModel?->name ?? 'N/A' }}</span></div>
                            @if($eventModel)
                                <div><span class="text-slate-500 font-medium">Associated Event:</span> <span class="text-slate-800">{{ $eventModel->event_name }} ({{ $eventModel->reference }})</span></div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Items Table --}}
                <div class="mb-6 overflow-hidden rounded-lg border border-slate-200">
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                <th class="py-2.5 px-3 w-10 text-center">#</th>
                                <th class="py-2.5 px-3">Item Code</th>
                                <th class="py-2.5 px-3">Description</th>
                                <th class="py-2.5 px-3">Category</th>
                                <th class="py-2.5 px-3 text-center">Unit</th>
                                <th class="py-2.5 px-3 text-right">Cost</th>
                                <th class="py-2.5 px-3 text-right">Qty</th>
                                <th class="py-2.5 px-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($selectedRows as $index => $row)
                                <tr class="hover:bg-slate-50/50">
                                    <td class="py-2 px-3 text-center text-slate-400">{{ $index + 1 }}</td>
                                    <td class="py-2 px-3 font-mono font-semibold text-slate-900">{{ $row['item_code'] ?? 'N/A' }}</td>
                                    <td class="py-2 px-3 text-slate-900 font-medium">{{ $row['item_description'] ?? 'N/A' }}</td>
                                    <td class="py-2 px-3 text-slate-600">{{ $row['category'] ?? 'N/A' }}</td>
                                    <td class="py-2 px-3 text-center text-slate-700">{{ $row['unit'] ?? 'N/A' }}</td>
                                    <td class="py-2 px-3 text-right font-mono">₱{{ number_format((float) ($row['cost'] ?? 0), 2) }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold text-slate-900">{{ $row['quantity'] ?? 0 }}</td>
                                    <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) ($row['sub_total'] ?? 0), 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-6 text-center text-slate-400 italic">No line items in this purchase order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-50 border-t-2 border-slate-800 font-bold">
                                <td colspan="7" class="py-3 px-3 text-right text-xs uppercase tracking-wider text-slate-700">Total Purchase Order Cost:</td>
                                <td class="py-3 px-3 text-right text-sm font-mono text-emerald-700">₱{{ number_format((float) $grand_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Notes / Remarks --}}
                @if($notes)
                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Notes / Instructions:</span>
                        <p class="mt-1 text-slate-800 whitespace-pre-line">{{ $notes }}</p>
                    </div>
                @endif

                {{-- Signatures Section --}}
                @php
                    $reviewerEmp = \App\Models\Business\Employee::find($reviewedBy);
                    $approverEmp = \App\Models\Business\Employee::find($approvedBy);
                    $preparerEmp = $puchaseOrderData?->preparedBy ?? (auth()->user()->employee);
                @endphp
                <div class="grid grid-cols-3 gap-6 pt-6 mt-6 border-t border-slate-200 text-xs">
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ $preparerEmp?->full_name ?? auth()->user()->name }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Prepared By</div>
                        <div class="text-[10px] text-slate-400">{{ $preparerEmp?->position_name ?? 'Purchaser / Staff' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ $reviewerEmp?->full_name ?? '_________________________' }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Reviewed By</div>
                        <div class="text-[10px] text-slate-400">{{ $reviewerEmp?->position_name ?? 'Designated Reviewer' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ $approverEmp?->full_name ?? '_________________________' }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Approved By</div>
                        <div class="text-[10px] text-slate-400">{{ $approverEmp?->position_name ?? 'Designated Approver' }}</div>
                    </div>
                </div>

                {{-- Document Footer --}}
                <div class="mt-8 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
                    <div>{{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }} • Requisition Management System</div>
                    <div>Generated on {{ now()->format('M d, Y h:i:s A') }}</div>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-ts-button flat text="Close" wire:click="$set('printPreviewModal', false)" />
                <x-ts-button icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf" color="primary" />
            </div>
        </x-slot:footer>
    </x-ts-modal>

    <style>
        @media print {
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body * {
                visibility: hidden !important;
            }
            #printable-po-preview, #printable-po-preview * {
                visibility: visible !important;
            }
            #printable-po-preview {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 1.5cm !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
            }
            .print\:hidden {
                display: none !important;
            }
        }
    </style>

    <x-ts-back-to-top lg/>
</div>
