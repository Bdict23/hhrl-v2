<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Inventory\PurchaseOrderService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use Livewire\WithFileUploads;
use App\Models\Inventory\Receiving;
use App\Models\Inventory\PurchaseOrderItems;
use App\Models\Inventory\PurchaseOrder;


new class extends Component
{
    use WithFileUploads;
    use WithPagination;
    use Interactions;

    public ?int $quantity = 10;
    public ?string $search = null;
    public $isExists = false;

    // For multiple files the property must be an array 
    public $photos = [];
     // 1. We create a property that will temporarily store the uploaded files
    public $backup = [];

    public $receivingInfo,
    $requestInfo,
    $requisitionDetails=[],
    $purchaseOrderId,
    $receivingNumber,
    $deliveredBy,
    $waybillNumber,
    $deliveryReceiptNumber,
    $invoiceNumber,
    $supplierId;

    //inputs
    public $selectedRows = [];
    public $notes;
    public $grand_total = 0.00;
    public $status;

    //selected
    public $selectedItem = [];

     protected $messages = [
        'waybillNumber.required_without_all' => 'Either delivery receipt number, invoice number, or this field must be provided.',
        'deliveryReceiptNumber.required_without_all' => 'Either waybill number, invoice number, or this field must be provided.',
        'invoiceNumber.required_without_all' => 'Either waybill number, delivery number, or this field must be provided.',
    ];

    public function validationRule()
    {
        $itemsTmp = $this->requisitionDetails;
        $this->requisitionDetails = $itemsTmp;
        $this->validate([
            'waybillNumber' => 'required_without_all:deliveryReceiptNumber,invoiceNumber|nullable|max:55',
            'deliveryReceiptNumber' => 'required_without_all:waybillNumber,invoiceNumber|nullable|max:55',
            'invoiceNumber' => 'required_without_all:waybillNumber,deliveryReceiptNumber|nullable|max:55',
            'deliveredBy' => 'nullable|string|max:55',
            'notes' => 'nullable|string|max:250',
        ]);

    }
    public function updated($key){
        $itemsTmp = $this->requisitionDetails;
        $this->requisitionDetails = $itemsTmp;
    }
 
    public function updatingPhotos(): void
    {
        // 2. We store the uploaded files in the temporary property
        $this->backup = $this->photos;
        $itemsTmp = $this->requisitionDetails;
        $this->requisitionDetails = $itemsTmp;
    }

    public function updatedPhotos(): void
    {
        $itemsTmp = $this->requisitionDetails;
        $this->requisitionDetails = $itemsTmp;
        if (!$this->photos) {
            return;
        }
 
        // 3. We merge the newly uploaded files with the saved ones
        $file = Arr::flatten(array_merge($this->backup, [$this->photos]));
 
        // 4. We finishing by removing the duplicates
        $this->photos = collect($file)->unique(fn (UploadedFile $item) => $item->getClientOriginalName())->toArray();
    }

    public function updatedPurchaseOrderId($id)
    {
        if($id){

            $this->receivingInfo = Receiving::where('REQUISITION_ID', $id)->where('RECEIVING_STATUS', 'DRAFT')->first();
            if ($this->receivingInfo) {
            $this->toast()->warning('Warning', 'This Purchase Order has an existing draft receiving. Please update the existing receiving.')->send();
            $this->purchaseOrderId = null;
                return;
            }
        
            $this->requestInfo = PurchaseOrder::where('id', $id)->first();
            $this->supplierId = $this->requestInfo->supplier_id;
            $this->requisitionDetails = PurchaseOrderItems::where('requisition_info_id', $id)
            ->get() // Execute the query to get a collection first
            ->map(function ($item) {
                // Calculate the difference
                    $remainingQty = $item->qty - ($item->cardexIn?->where('status', 'FINAL')->sum('qty_in') ?? 0);

                    // Determine the highlight color
                    $color = match (true) {
                        $remainingQty == 0 => 'green',
                        $remainingQty < 0 => 'red',
                        default => null,
                    };
                    // return $item->setAttribute('highlight', $color);

                    // Get the old cost
                    $oldCost = (float) ($item->cost?->amount ?? 0);
                    
                    // Convert to array with defaults
                    return [
                        'item_id'               => $item->item_id,
                        'requested_qty'         => $item->qty,
                        'description'           => $item->item->item_description,
                        'unit'                  => $item->item->unit?->unit_symbol ?? 'N/A',
                        'toReceive'             => $remainingQty,
                        'oldCost'               => $oldCost,
                        'newCost'               => $oldCost, // Default to old cost
                        'received'              => 0, 
                        'subTotal'              => 0, // Will be calculated
                        'highlight'             => $color,
                        'item'                  => $item->item,
                        'price_id'              => $item->price_level_id,
                        'cardexIn'              => $item->cardexIn?->where('status', 'FINAL')->sum('qty_in') ?? 0,
                        'notIncluded'           => $remainingQty == 0 || $remainingQty < 0,

                        // for sub table
                        'id'                    => $item->item->id,
                        'item_code'             => $item->item->item_code,
                        'item_description'      => $item->item->item_description,
                        'brand'                 => $item->item->brand?->brand_name ?? 'N/A',
                        'category'              => $item->item->category?->category_name ?? 'N/A',
                        'classification'        => $item->item->classification?->classification_name ?? 'N/A',
                        'subClass'              => $item->item->subClassification?->classification_name ?? 'N/A',
                    ];
                })->toArray();
            }
            else{
                $this->reset();
            }
    }


    public function with(): array
    {
        return [
            'selectedItemHeader' => [
                ['index' => 'description', 'label' => 'Description'],
                ['index' => 'unit', 'label' => 'Unit' ],
                ['index' => 'requested_qty', 'label' => 'request qty' ],
                ['index' => 'toReceive', 'label' => 'to receive'],
                ['index' => 'received', 'label' => 'received' ],
                ['index' => 'oldCost', 'label' => 'old cost'],
                ['index' => 'newCost', 'label' => 'new cost'],
                ['index' => 'subTotal', 'label' => 'sub total'],
            ]
        ];
    }


    public function deleteUpload(array $content): void
    {
    /*
     the $content contains:
     [
         'temporary_name',
         'real_name',
         'extension',
         'size',
         'path',
         'url',
     ]
     */
        $itemsTmp = $this->requisitionDetails;
        if (! $this->photos) {
            return;
        }
    
        $files = Arr::wrap($this->photos);
    
        /** @var UploadedFile $file */
        $file = collect($files)->filter(fn (UploadedFile $item) => $item->getFilename() === $content['temporary_name'])->first();
    
        // 1. Here we delete the file. Even if we have a error here, we simply
        // ignore it because as long as the file is not persisted, it is
        // temporary and will be deleted at some point if there is a failure here.
        rescue(fn () => $file->delete(), report: false);
    
        $collect = collect($files)->filter(fn (UploadedFile $item) => $item->getFilename() !== $content['temporary_name']);
    
        // 2. We guarantee restore of remaining files regardless of upload
        // type, whether you are dealing with multiple or single uploads
        $this->photos = is_array($this->photos) ? $collect->toArray() : $collect->first();

        $this->requisitionDetails = $itemsTmp;
    }

    public function saveAsDraftAction(): void
    {

        // 1. Validate the UI State
        $validated = $this->validationRule();

        $this->status = "DRAFT";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('Save receiving?', 'Are you sure to save this receiving as draft ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function saveAsFinalAction(): void
    {
        // 1. Validate the UI State
        $validated = $this->validationRule();
        $this->status = "FINAL";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('Save receiving?', 'Are you sure to save this receiving as final?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(PurchaseOrderService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id'   => Auth::user()->branch_id,
                'company_id'    => Auth::user()->branch->company_id,
                'requisition_id' => $this->purchaseOrderId,
                'supplier_id' => $this->supplierId,
                'receiving_type'   => 'PO',
                'receiving_number' => $this->receivingNumber,
                'deliveredBy' => $this->deliveredBy,
                'waybill_number' => $this->waybillNumber,
                'delivery_number' => $this->deliveryReceiptNumber,
                'invoice_number' => $this->invoiceNumber,
                'preparedBy'  => auth()->user()->emp_id,
                'note'       => $this->notes,
                'status'  => $this->status,
                'items' => $this->requisitionDetails,
                'attachments'=> $this->photos,
            ];

            // 4. Call the Service
            $po = $service->createReceiving($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Purchase Order {$po->requisition_number} created successfully!")->send();
            $this->reset();
            return redirect()->route('receiving-summary');

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PO Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
 
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Inventory', 'link' => route('receiving-summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Receiving Summary', 'link' => route('receiving-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create Receiving', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4 mb-10">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.get.to-receive-purchase-order', ['branch_id' => Auth::user()->branch_id])"
                        label="Bangquet event"
                        wire:model.live='purchaseOrderId'
                        select="label:requisition_number|value:id|description:remarks"
                        :placeholders="[
                            'default' => 'Select purchase order',
                            'search'  => 'Search purchase order',
                            'empty'   => 'No to receive purchase order found',
                        ]"
                    />

                    <x-ts-input label="Approved Budget" wire:model.blur="waybillNumber"/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Check No̱." wire:model.blur="receivingNumber"/>
                    <x-ts-input label="Total incurred amount" wire:model.blur="deliveryReceiptNumber"/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="CRS No̱." wire:model.blur="deliveredBy"/>
                    <x-ts-input label="Return amount" wire:model.blur="invoiceNumber"/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <x-ts-tab selected="PETTY CASH VOUCHERS">
            <x-ts-tab.items tab="PETTY CASH VOUCHERS">
                <x-ts-card>
                    <x-ts-table :headers="$selectedItemHeader" :rows="$requisitionDetails" striped expandable loading highlight>
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                        <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    x-bind:number="Number(Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toFixed(2))"
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span x-text="Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">0.00</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                    </x-ts-table>
                </x-ts-card>
            </x-ts-tab.items>
            <x-ts-tab.items tab="PURCHASE ORDERS">
                <x-ts-card>
                    <x-ts-table :headers="$selectedItemHeader" :rows="$requisitionDetails" striped expandable loading highlight>
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                        <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    x-bind:number="Number(Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toFixed(2))"
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span x-text="Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">0.00</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                    </x-ts-table>
                </x-ts-card>
            </x-ts-tab.items>
            <x-ts-tab.items tab="ITEM WITHDRAWALS">
                <x-ts-card>
                    <x-ts-table :headers="$selectedItemHeader" :rows="$requisitionDetails" striped expandable loading highlight>
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract
                        <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    x-bind:number="Number(Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toFixed(2))"
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span x-text="Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">0.00</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                    </x-ts-table>
                </x-ts-card>
            </x-ts-tab.items>
        </x-ts-tab>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes"/>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="col-span-2">
                            <x-ts-upload delete multiple label="Attachments" wire:model="photos"/>
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end">
                    <x-ts-dropdown>
                        <x-slot:action>
                            <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                        </x-slot:action>
                        <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT"
                            wire:click="saveAsDraftAction()" />
                        <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator
                            wire:click="saveAsFinalAction()" />
                    </x-ts-dropdown>
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>

    <x-ts-back-to-top />
</div>
