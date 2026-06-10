<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Inventory\PurchaseOrderService;


new class extends Component
{
    use WithPagination;
    use Interactions;

    public ?int $quantity = 10;
    public ?string $search = null;

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

    public function mount(){
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
                ['index' => 'item_code', 'label' => 'Code'],
                ['index' => 'item_description', 'label' => 'Description'],
                ['index' => 'unit', 'label' => 'Unit' ],
                ['index' => 'unit', 'label' => 'request qty' ],
                ['index' => 'cost', 'label' => 'to receive qty'],
                ['index' => 'quantity', 'label' => 'received' ],
                ['index' => 'sub_total', 'label' => 'old cost'],
                ['index' => 'sub_total', 'label' => 'new cost'],
                ['index' => 'sub_total', 'label' => 'sub total'],
            ]
        ];
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
                        wire:model='purchase_order_id'
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
                    <x-ts-input label="Supplier" readonly />
                    <x-ts-input label="Delivery Receipt No̱." />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Delivered By"/>
                    <x-ts-input label="Invoice No̱." />
                </div>
                <div class="p-10 justify-center">
                    <x-ts-stats :number="$grand_total" title="Total Cost" animated>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                    </x-ts-stats>
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
                        wire:model.live.debounce.500ms="selectedRows.{{ $loop->index }}.quantity" />
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
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes"/>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-1 gap-2">
                        <div class="col-span-2">
                            <x-ts-select.styled
                            :request="route('api.active.reviewers', ['branch_id' => auth()->user()->branch_id ])"
                            select="label:fullName|value:id|description:position"
                            wire:model="reviewedBy"
                            label="Reviewed By"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No reviewers found',
                            ]" ... required/>
                        </div>

                        <div class="col-span-2">
                            <x-ts-select.styled
                                :request="route('api.active.approvers', ['branch_id' => auth()->user()->branch_id])"
                                wire:model="approvedBy"
                                select="label:fullName|value:id|description:position"
                                label="Approved By"
                                :placeholders="[
                                    'default' => 'Select    ',
                                    'empty'   => 'No aapprovers found',
                                ]" required />
                        </div>

                        <div class="col-span-1 items-center inline-flex mt-2">
                            <div class="flex justify-end">
                                
                            </div>
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
