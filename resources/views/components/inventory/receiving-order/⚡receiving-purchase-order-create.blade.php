<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Inventory\PurchaseOrderService;
use Illuminate\Support\Arr;
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

    public $receivingInfo,$requestInfo,$requisitionDetails=[],$purchaseOrderId;

    //inputs
    public $selectedRows = [];
    public $notes;
    public $grand_total = 0.00;
    public $status;

    //selected
    public $selectedItem = [];


    protected $rules = [
            'supplier_id' => 'required|exists:suppliers,id',
            'type_id' => 'required|exists:system_parameters,id',
            'event_id' => 'nullable|exists:banquet_events,id',
            'productionOrder_id' => 'nullable|exists:production_orders,id',
            'term_id' => 'required|exists:system_parameters,id',
            'merchandisePONumber' => 'nullable|max:50',
            'notes' => 'nullable|string|max:255',
            'approvedBy' => 'required|exists:employees,id',
            'reviewedBy' => 'required|exists:employees,id',
            'selectedRows' => 'required|array|min:1',
            'selectedRows.*.quantity' => 'required|numeric|min:0.01',
            'selectedRows.*.cost'     => 'required|numeric|min:1',
        ];
    protected $messages=[
            'selectedRows.*.quantity.min' => 'Quantity must be greater than 0.',
            'selectedRows.*.quantity.required' => 'Qty is required.',
            'term_id.required' => 'Term is required.',
            'supplier_id.required' => 'Supplier is required.',
            'type_id.required' => 'Type is required.',
            'reviewedBy.required' => 'Reviewer is required.',
            'approvedBy.required' => 'Approver is required.',
            'reviewedBy.exists' => 'Select a valid reviewer on the list.',
            'approvedBy.exists' => 'Select a valid reviewer on the list.',
        ];

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

    public function updatedPurchaseOrderId($id)
    {
            $this->receivingInfo = Receiving::where('REQUISITION_ID', $id)->where('RECEIVING_STATUS', 'DRAFT')->first();
            if ($this->receivingInfo) {
            $this->toast()->warning('Warning', 'This Purchase Order has an existing draft receiving. Please update the existing receiving.')->send();
            $this->purchaseOrderId = null;

                return;
            }
        
        $this->requestInfo = PurchaseOrder::where('id', $id)
            ->first();
        $this->requisitionDetails = PurchaseOrderItems::where('requisition_info_id', $id)
            ->get() // Execute the query to get a collection first
            ->map(function ($item) {
                // Calculate the difference
                $remainingQty = $item->qty - ($item->cardexIn?->where('status', 'FINAL')->sum('qty_in') ?? 0);

                // Determine the highlight color
                $color = match (true) {
                    $remainingQty == 0 => 'green',
                    default => null,
                };
                return $item->setAttribute('highlight', $color);
            });

    }

    /**
     * Hook when the checkbox selection changes.
     */
    public function updatedSelectedItem($ids)
    {

        // 1. Get IDs already present in the table
        $existingIds = array_column($this->selectedRows, 'id');

        // 2. Identify the IDs that are not in the table yet
        $newIds = array_diff($ids, $existingIds);

        // 3. Identify IDs that were unchecked (to remove them from table)
        $removedIds = array_diff($existingIds, $ids);

        // Handle Removals: if an ID is unchecked in the modal, remove it from the table
        if (!empty($removedIds)) {
            $this->selectedRows = array_values(array_filter($this->selectedRows, function($row) use ($removedIds) {
                return !in_array($row['id'], $removedIds);
            }));
        }

        // Handle Additions: Only query the database for the NEW IDs
        if (!empty($newIds)) {
            $items = Item::with(['unit', 'cost', 'brand', 'category', 'classification', 'subClassification'])
                ->whereIn('id', $newIds)
                ->get();

            foreach ($items as $item) {
                $this->selectedRows[] = [
                    'id'               => $item->id,
                    'item_code'        => $item->item_code,
                    'item_description' => $item->item_description,
                    'unit'             => $item->unit?->unit_symbol ?? 'N/A',
                    'cost'             => (float) ($item->cost?->amount ?? 0),
                    'price_id'         => $item->cost?->id ?? null,
                    'quantity'         => 1,
                    'sub_total'        => (float) ($item->cost?->amount ?? 0),
                    // Sub-table data
                    'brand'            => $item->brand?->brand_name ?? 'N/A',
                    'category'         => $item->category?->category_name ?? 'N/A',
                    'classification'   => $item->classification?->classification_name ?? 'N/A',
                    'subClass'         => $item->subClassification?->classification_name ?? 'N/A',
                ];
            }
        }
        $this->calculateGrandTotal();

    }

    public function calculateGrandTotal()
    {
        $this->grand_total = number_format(collect($this->selectedRows)->sum('sub_total'), 2 );

    }

    // Remove from selected item
    public function removeItem($index)
    {
        unset($this->selectedRows[$index]);
        // Reset array keys to prevent index gaps
        $this->selectedRows = array_values($this->selectedRows);

        // Sync back to your original selection ID array if necessary
            $this->selectedItem = collect($this->selectedRows)->pluck('id')->toArray();
            $this->toast()->success('Success', 'Removed Successfully')->send();

            $this->calculateGrandTotal();

    }

    // This runs automatically whenever any value in $selectedRows changes
    public function updatedSelectedRows($value, $key)
    {
        // The $key looks like "0.quantity" = (index.property)
        // We extract the index to update the correct row
        $parts = explode('.', $key);
        $index = $parts[0];

        if (isset($parts[1]) && $parts[1] === 'quantity') {
            $qty = (float) ($this->selectedRows[$index]['quantity'] ?? 0);
            $cost = (float) ($this->selectedRows[$index]['cost'] ?? 0);

            // Update the Sub-total for this row
            $this->selectedRows[$index]['sub_total'] = $qty * $cost;
        }
        $this->calculateGrandTotal();
    }

    public function with(): array
    {
        return [
            'selectedItemHeader' => [
                ['index' => 'description', 'label' => 'Description'],
                ['index' => 'unit', 'label' => 'Unit' ],
                ['index' => 'qty', 'label' => 'request qty' ],
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
    $this->photo = is_array($this->photos) ? $collect->toArray() : $collect->first();
}

    public function saveAsDraftAction(): void
    {
         // 1. Validate the UI State
        $validated = $this->validate();
        $this->status = "PREPARING";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('New Purchase Order', 'Are you sure to save this order as draft ?')
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
        $validated = $this->validate();
        $this->status = "FOR REVIEW";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('New Purchase Order', 'Are you sure to save this order as final?')
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
                'event_id'    => $this->event_id,
                'branch_id'   => auth()->user()->branch_id,
                'merchandiseNumber' => $this->merchandisePONumber,
                'preparedBy'  => auth()->user()->emp_id,
                'reviewedBy'   => $this->reviewedBy,
                'approvedBy'   => $this->approvedBy,
                'term_id'      => $this->term_id,
                'notes'       => $this->notes,
                'supplier_id' => $this->supplier_id,
                'type_id'     => $this->type_id,
                'items'       => $this->selectedRows,
                'production_id' => $this->productionOrder_id,
                'status'  => $this->status,
            ];

            // 4. Call the Service
            $po = $service->create($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Purchase Order {$po->requisition_number} created successfully!")->send();
            $this->reset();
            return redirect()->route('purchase-order-summary');

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
                              ['label' => 'Inventory', 'link' => route('purchase-order-summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Receiving Summary', 'link' => route('purchase-order-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create Receiving', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.get.to-receive-purchase-order', ['branch_id' => auth()->user()->branch_id])"
                        label="Purchase Order"
                        wire:model.live='purchaseOrderId'
                        select="label:requisition_number|value:id|description:remarks"
                        :placeholders="[
                            'default' => 'Select purchase order',
                            'search'  => 'Search purchase order',
                            'empty'   => 'No to receive purchase order found',
                        ]"
                    />

                    <x-ts-input label="Waybill No̱." />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Receiving No̱."/>
                    <x-ts-input label="Delivery Receipt No̱." />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Delivered By"/>
                    <x-ts-input label="Invoice No̱." />
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <x-ts-table :headers="$selectedItemHeader" :rows="$requisitionDetails" striped expandable loading highlight>
                    @interact('column_description', $row)
                       {{ $row->item->item_description }}
                    @endinteract
                    @interact('column_unit', $row)
                       {{ $row->item->unit?->unit_symbol ?? 'N/A' }}
                    @endinteract
                    @interact('column_toReceive', $row)
                         {{ $row->qty - $row->cardexIn?->where('status', 'FINAL')->sum('qty_in') }} 
                    @endinteract
                    @interact('column_received', $row)
                        @php
                            $isComplete =  ($row->qty - $row->cardexIn?->where('status', 'FINAL')->sum('qty_in')) == 0;
                        @endphp
                       <x-ts-input type="number" sm wire:model="requisitionDetails.{{ $loop->index }}.received" :disabled="$isComplete" />
                    @endinteract
                    @interact('column_oldCost', $row)
                        ₱ {{{ $row->item->cost?->amount }}}
                    @endinteract
                    @interact('column_newCost', $row)
                        @php
                            $isComplete =  ($row->qty - $row->cardexIn?->where('status', 'FINAL')->sum('qty_in')) == 0;
                        @endphp
                       <x-ts-currency  symbol sm wire:model.live.debounce.500ms="requisitionDetails.{{ $loop->index }}.newCost" :disabled="$isComplete" />
                    @endinteract
                    @interact('sub_table', $row)
                        <x-ts-table :headers="[
                            ['index' => 'item_code', 'label' => 'Code'],
                            ['index' => 'item_description', 'label' => 'Description'],
                            ['index' => 'brand', 'label' => 'Brand'],
                            ['index' => 'category', 'label' => 'Category'],
                            ['index' => 'classification', 'label' => 'Classification'],
                            ['index' => 'subClass', 'label' => 'Sub-Classification'],
                        ]"
                        :rows="[[
                            'item_code'       => $row->item->item_code,
                            'item_description'=> $row->item->item_description,
                            'brand'          => $row->item->brand?->brand_name ?? 'N/A', {{-- Access as array --}}
                            'category'       => $row->item->category?->category_name ?? 'N/A',
                            'classification' => $row->item->classification?->classification_name ?? 'N/A',
                            'subClass'       => $row->item->subClassification?->classification_name ?? 'N/A',
                        ]]" />
                    @endinteract

                </x-ts-table>
                @error('selectedRows')
                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                @enderror
                <x-slot:footer>
                    <div class="flex justify-end">
                        <x-ts-stats number="{{$grand_total}}" title="Total Cost">
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                    </x-ts-stats>
                    </div>
                </x-slot:footer>
            </x-ts-card>
        </div>

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
