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
use App\Models\Inventory\Cardex;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\SplFileInfo;

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
    $notes,
    $supplierId,
    $adtl_attachment = [];

    //inputs
    public $selectedRows = [],
    $grand_total = 0.00,
    $status,
    $receivingId,
    $receivingData;

    //selected
    public $selectedItem = [];


    protected $rules = [
        'waybillNumber' => 'required_without_all:deliveryReceiptNumber,invoiceNumber|nullable|max:55',
        'deliveryReceiptNumber' => 'required_without_all:waybillNumber,invoiceNumber|nullable|max:55',
        'invoiceNumber' => 'required_without_all:waybillNumber,deliveryReceiptNumber|nullable|max:55',
        'deliveredBy' => 'nullable|string|max:55',
        'notes' => 'nullable|string|max:250',
    ];

     protected $messages = [
        'waybillNumber.required_without_all' => 'Either delivery receipt number, invoice number, or this field must be provided.',
        'deliveryReceiptNumber.required_without_all' => 'Either waybill number, invoice number, or this field must be provided.',
        'invoiceNumber.required_without_all' => 'Either waybill number, delivery number, or this field must be provided.',
    ];

    public function mount($id)
    {
        $this->receivingId = $id;
        $this->fetchData();
    }
    public function fetchData()
    {
        $this->receivingData = Receiving::findOrFail($this->receivingId);
        $this->purchaseOrderId = $this->receivingData->REQUISITION_ID;
        $this->requestInfo = PurchaseOrder::where('id', $this->purchaseOrderId)->first();
        $this->supplierId = $this->requestInfo->supplier_id;
        $this->receivingNumber = $this->receivingData->RECEIVING_NUMBER;
        $this->deliveredBy = $this->receivingData->DELIVERED_BY;
        $this->waybillNumber = $this->receivingData->WAYBILL_NUMBER;
        $this->deliveryReceiptNumber = $this->receivingData->DELIVERY_NUMBER;
        $this->invoiceNumber = $this->receivingData->INVOICE_NUMBER;
        $this->notes = $this->receivingData->remarks;
        $this->requisitionDetails = PurchaseOrderItems::where('requisition_info_id', $this->purchaseOrderId)
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
                    // Get the old cost
                    $oldCost = (float) ($item->cost?->amount ?? 0);
                    
                    //get temp in from cardex
                    $tmpIn = $item->cardexIn?->where('receiving_id',$this->receivingId)->sum('qty_in') ?? 0;
                    

                    //get the last entry cost for this receivingd
                    $enteredCost = $item->cardexIn
                    ->where('item_id', $item->item_id)
                    ->where('receiving_id', $this->receivingId)
                    ->sortByDesc('created_at')->first()?->cost?->amount 
                    ?? $oldCost;
                    

                    // Convert to array with defaults
                    return [
                        'item_id'               => $item->item_id,
                        'requested_qty'         => $item->qty,
                        'description'           => $item->item->item_description,
                        'unit'                  => $item->item->unit?->unit_symbol ?? 'N/A',
                        'toReceive'             => $remainingQty,
                        'oldCost'               => $oldCost,
                        'newCost'               => $enteredCost, // previouse entered cost if none old cost to default
                        'received'              => $tmpIn, 
                        'subTotal'              => 0, // Will be calculated
                        'highlight'             => $color,
                        'item'                  => $item->item,
                        'price_id'              => $item->price_level_id,
                        'cardexIn'              => $item->cardexIn?->where('status', 'FINAL')->sum('qty_in') ?? 0,
                        'cardexInTmp'           => $tmpIn,
                        'notIncluded'           => $remainingQty == 0 || $remainingQty < 0,

                        // for sub table
                        'item_code'             => $item->item->item_code,
                        'item_description'      => $item->item->item_description,
                        'brand'                 => $item->item->brand?->brand_name ?? 'N/A',
                        'category'              => $item->item->category?->category_name ?? 'N/A',
                        'classification'        => $item->item->classification?->classification_name ?? 'N/A',
                        'subClass'              => $item->item->subClassification?->classification_name ?? 'N/A',
                    ];
                })->toArray();
    

        $this->photos = $this->receivingData->attachments->map(function ($attachment) {
            // Standardizes the file path reference
            $filePath = $attachment->file_path; 

            return [
                'id'        => $attachment->id, // handy if you need to delete it later
                'name'      => basename($filePath),
                'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
                'size'      => Storage::disk('public')->exists($filePath) ? Storage::disk('public')->size($filePath) : 0,
                'path'      => $filePath,
                'url'       => Storage::disk('public')->url($filePath),
            ];
        })->toArray();

    }

    public function updatingPhotos(): void
    {
        // 2. We store the uploaded files in the temporary property
        $this->backup = $this->photos;
    }
 
    public function updatedPhotos(): void
    {
        if (!$this->photos) {
            return;
        }
 
        // 3. We merge the newly uploaded files with the saved ones
        $file = Arr::flatten(array_merge($this->backup, [$this->photos]));
 
        // 4. We finishing by removing the duplicates
        $this->photos = collect($file)->unique(fn (UploadedFile $item) => $item->getClientOriginalName())->toArray();
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

    public function deleteAdtlUpload(array $content): void{
        if (! $this->adtl_attachment) {
            return;
        }
 
        $files = Arr::wrap($this->adtl_attachment);
    
        /** @var UploadedFile $file */
        $file = collect($files)->filter(fn (UploadedFile $item) => $item->getFilename() === $content['temporary_name'])->first();
    
        // 1. Here we delete the file. Even if we have a error here, we simply
        // ignore it because as long as the file is not persisted, it is
        // temporary and will be deleted at some point if there is a failure here.
        rescue(fn () => $file->delete(), report: false);
    
        $collect = collect($files)->filter(fn (UploadedFile $item) => $item->getFilename() !== $content['temporary_name']);
    
        // 2. We guarantee restore of remaining files regardless of upload
        // type, whether you are dealing with multiple or single uploads
        $this->adtl_attachment = is_array($this->adtl_attachment) ? $collect->toArray() : $collect->first();
    }

    public function deleteExistingUpload(array $content): void
    {
        if (! $this->photos) {
            return;
        }

        // --- CASE 1: It's an already SAVED file from the database ---
        // If the file array has an 'id' (which we passed from the database model)
        if (isset($content['id']) || !empty($content['path'])) {

            // 1. Delete from database
            if(isset($content['path'])){
                PurchaseOrderService::removeReceivingAttachment($this->receivingId, $content['path']);
            }
            
            // 2. Delete the actual file from your public storage disk
            if (Storage::disk('public')->exists($content['path'])) {
                Storage::disk('public')->delete($content['path']);
            }

            // 3. Delete the record from your database table
            if (isset($content['id'])) {
                ReceivingAttachment::destroy($content['id']);
            }

            // 4. Remove it from your local Livewire state array
            $this->photos = collect($this->photos)
                ->filter(fn ($item) => is_array($item) && $item['path'] !== $content['path'])
                ->toArray();

            $this->toast()->success('Deleted', 'Attachment removed successfully.')->send();
            return;
        }

        // --- CASE 2: It's a brand new TEMPORARY upload ---
        $files = Arr::wrap($this->photos);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = collect($files)
            ->filter(fn ($item) => method_exists($item, 'getFilename') && $item->getFilename() === $content['temporary_name'])
            ->first();

        if ($file) {
            rescue(fn () => $file->delete(), report: false);
        }

        $collect = collect($files)
            ->filter(fn ($item) => !method_exists($item, 'getFilename') || $item->getFilename() !== $content['temporary_name']);

        $this->photos = $collect->toArray();
    }

    public function updateAsDraftAction(): void
    {
        // 1. Validate the UI State
        $validated = $this->validate();
        $this->status = "DRAFT";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('Update receiving?', 'Are you sure to save this receiving as draft ?')
        ->confirm(
            'Confirm',
            'updateReceiving', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function updateAsFinalAction(): void
    {
        // 1. Validate the UI State
        $validated = $this->validate();
        $this->status = "FINAL";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('Update receiving?', 'Are you sure to save this receiving as final?')
        ->confirm(
            'Confirm',
            'updateReceiving', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function updateReceiving(PurchaseOrderService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id'   => Auth::user()->branch_id,
                'company_id'    => Auth::user()->branch->company_id,
                'supplier_id' => $this->supplierId,
                'requisition_id' => $this->purchaseOrderId,
                'receiving_number' => $this->receivingNumber,
                'deliveredBy' => $this->deliveredBy,
                'waybill_number' => $this->waybillNumber,
                'delivery_number' => $this->deliveryReceiptNumber,
                'invoice_number' => $this->invoiceNumber,
                'preparedBy'  => auth()->user()->emp_id,
                'note'       => $this->notes,
                'status'  => $this->status,
                'items' => $this->requisitionDetails,
                'attachments'=> $this->adtl_attachment,
                'receiving_id' =>$this->receivingId,
            ];

            // 4. Call the Service
            $po = $service->updateReceiving($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Purchase Order {$po->requisition_number} updated successfully!")->send();
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
                              ['label' => 'View Receiving', 'icon' => 'eye'],
                  ]"  class="mb-3"/>
            <span></span>
    </div>

    <div class="grid gap-4 mb-10">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Purchase Order" value="{{$this->requestInfo->requisition_number}}" readonly/>
                    <x-ts-input label="Waybill No̱." wire:model.blur="waybillNumber" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Receiving No̱." wire:model.blur="receivingNumber" readonly/>
                    <x-ts-input label="Delivery Receipt No̱." wire:model.blur="deliveryReceiptNumber" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Delivered By" wire:model.blur="deliveredBy" readonly/>
                    <x-ts-input label="Invoice No̱." wire:model.blur="invoiceNumber" readonly/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <div x-data="{ items: {} }">
                    <x-ts-table :headers="$selectedItemHeader" :rows="$requisitionDetails" striped expandable loading highlight>
                        
                        @interact('column_toReceive', $row)
                            @php
                                $maxReceive = $row['requested_qty'] - $row['cardexIn'];
                            @endphp
                            <span 
                                x-init="items[{{ $loop->index }}] = { 
                                    max: {{ $maxReceive }}, 
                                    received: {{ $requisitionDetails[$loop->index]['received'] ?? $row['cardexInTmp'] }}, 
                                    newCost: {{ $requisitionDetails[$loop->index]['newCost'] ?? $row['newCost'] }},
                                }" 
                                x-text="items[{{ $loop->index }}]?.max"
                            ></span>
                        @endinteract

                        @interact('column_received', $row)
                            <x-ts-input 
                                type="number" 
                                sm
                                readonly 
                                wire:model.blur="requisitionDetails.{{ $loop->index }}.received" 
                                x-bind:disabled="{{$row['notIncluded']}}" 
                                x-model.number="items[{{ $loop->index }}].received"
                                x-bind:max="items[{{ $loop->index }}]?.max"
                                @input="
                                    if (items[{{ $loop->index }}].received > items[{{ $loop->index }}].max) {
                                        items[{{ $loop->index }}].received = items[{{ $loop->index }}].max;
                                    }
                                "
                            />
                        @endinteract

                        @interact('column_oldCost', $row)
                            ₱ {{ number_format($row['oldCost'],2) }}
                        @endinteract

                        @interact('column_newCost', $row)
                            <x-ts-input 
                                type="number"
                                sm
                                readonly
                                wire:model.blur="requisitionDetails.{{ $loop->index }}.newCost" 
                                x-bind:disabled="{{$row['notIncluded']}}"  
                                x-model.number="items[{{ $loop->index }}].newCost"
                            /> 
                        @endinteract

                        @interact('column_subTotal', $row)
                            <span class="font-semibold text-gray-700">
                                ₱ <span x-text="((items[{{ $loop->index }}]?.received || 0) * (items[{{ $loop->index }}]?.newCost || 0)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">0.00</span>
                            </span>
                        @endinteract
                        @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'item_id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'item_id'           => $row['item_id'],
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
                                    title="Total Cost">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span x-text="Object.values(items).reduce((s, it) => s + ((Number(it.received)||0) * (Number(it.newCost)||0)), 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})">0.00</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                    </x-ts-table>
                </div>

                @error('selectedRows')
                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                @enderror
            </x-ts-card>
        </div>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes" readonly/>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="col-span-2">
                            <x-ts-upload multiple static :placeholder="count($photos) . ' atttached image'"  label="Attachments" wire:model="photos" />
                        </div>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                     <x-ts-button  icon="arrow-left" outline :href="route('receiving-summary')">Back</x-ts-button>
                     @if($receivingData->RECEIVING_STATUS == 'DRAFT')
                     <x-ts-button  icon="pencil-square" :href="route('receiving.edit', ['id' => $receivingId])">Edit</x-ts-button>
                     @else
                     <x-ts-button  icon="pencil-square" disabled>Edit</x-ts-button>
                     @endif
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>

    <x-ts-back-to-top />
</div>
