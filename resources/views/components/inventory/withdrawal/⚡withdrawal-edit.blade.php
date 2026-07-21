<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Inventory\ItemWithdrawalService as WithdrawalService;
use App\Models\Inventory\Withdrawal;


new class extends Component
{
    use WithPagination;
    use Interactions;

    public ?int $quantity = 5;
    public ?string $search = null;

    //inputs
    public $selectedRows = [];
    public $grand_total = 0.00;
    public $itemRowReceiving = [];

    public $receivingId;
    public $withdrawalId;

    //selected
    public $selectedItem = [],
           $typeId,
           $event_id,
           $productionOrder_id,
           $effectiveDate,
           $restockDate,
           $department_id,
           $notes,
           $approvedBy,
           $reviewedBy,
           $status,
           $step = 1,
           $reference;


    protected $rules = [
            'typeId' => 'required|exists:system_parameters,id',
            'event_id' => 'nullable|exists:banquet_events,id',
            'productionOrder_id' => 'nullable|exists:production_orders,id',
            'effectiveDate' => 'required|date',
            'restockDate' => 'nullable|date',
            'department_id' => 'required|exists:departments,id',
            'approvedBy' => 'required|exists:employees,id',
            'reviewedBy' => 'required|exists:employees,id',
            'selectedRows' => 'required|array|min:1',
            'notes' => 'nullable|string|max:255',
            'selectedRows.*.quantity' => 'required|numeric|min:0.01',
            'selectedRows.*.cost'     => 'required|numeric|min:1',
        ];
    protected $messages=[
            'selectedRows.*.quantity.min' => 'Quantity must be greater than 0.',
            'selectedRows.*.quantity.required' => 'Qty is required.',
            'typeId.required' => 'Type is required.',
            'reviewedBy.required' => 'Reviewer is required.',
            'approvedBy.required' => 'Approver is required.',
            'reviewedBy.exists' => 'Select a valid reviewer on the list.',
            'approvedBy.exists' => 'Select a valid reviewer on the list.',
        ];

    public function mount($id){
        $this->withdrawalId = $id;
        $this->fetchData();

    }

    public function fetchData(){
        
        $withdrawal = Withdrawal::findOrFail($this->withdrawalId);

        $this->reference = $withdrawal->reference_number;
        $this->typeId = $withdrawal->type_id;
        $this->event_id = $withdrawal->event_id;
        $this->productionOrder_id = $withdrawal->production_id;
        $this->effectiveDate = $withdrawal->usage_date;
        $this->restockDate = $withdrawal->useful_date;
        $this->department_id = $withdrawal->department_id;
        $this->notes = $withdrawal->remarks;
        $this->approvedBy = $withdrawal->approved_by;
        $this->reviewedBy = $withdrawal->reviewed_by;
        $this->status = $withdrawal->withdrawal_status;
        $this->step = match($this->status) {
            'PREPARING' => 1,
            'FOR REVIEW' => 2,
            'FOR APPROVAL' => 3,
            'COMPLETED' => 4,
            default => 1,
        };

        // Populate selectedRows with the items from the withdrawal
        foreach ($withdrawal->cardex as $cardex) {
           $this->selectedRows[] = [
                'id'               => $cardex->item->id,
                'item_code'        => $cardex->item->item_code,
                'item_description' => $cardex->item->item_description,
                'unit'             => $cardex->item->unit?->unit_symbol ?? 'N/A',
                'cost'             => (float) ($cardex->cost?->amount ?? 0),
                'price_id'         => $cardex->item->cost?->id ?? null,
                'quantity'         => (float) ($cardex->qty ?? 1),
                'sub_total'        => (float) ($cardex->qty * ($cardex->cost?->amount ?? 0)),
                // Sub-table data
                'brand'            => $cardex->item->brand?->brand_name ?? 'N/A',
                'category'         => $cardex->item->category?->category_name ?? 'N/A',
                'classification'   => $cardex->item->classification?->classification_name ?? 'N/A',
                'subClass'         => $cardex->item->subClassification?->classification_name ?? 'N/A',
            ];
        }

        // Set the selectedItem array to the IDs of the selectedRows
        $this->selectedItem = collect($this->selectedRows)->pluck('id')->toArray();

        // Calculate grand total after populating selectedRows
        $this->calculateGrandTotal();
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

    public function updatedReceivingId($value)
    {
        
        if ($value) {
            $this->itemRowReceiving = Item::query()
                ->whereHas('receivingItems', function (Builder $query) use ($value) {
                    $query->where('receiving_id', $value);
                })
                ->with(['unit', 'cost', 'brand', 'category', 'classification', 'subClassification'])
                ->get()
                ->map(function ($item) {
                    return [
                        'id'               => $item->id,
                        'item_code'        => $item->item_code,
                        'item_description' => $item->item_description,
                        'unit'             => $item->unit?->unit_symbol ?? 'N/A',
                        'cost'             => (float) ($item->cost?->amount ?? 0),
                        'price_id'         => $item->cost?->id ?? null,
                        // Sub-table data
                        'brand'            => $item->brand?->brand_name ?? 'N/A',
                        'category'         => $item->category?->category_name ?? 'N/A',
                        'classification'   => $item->classification?->classification_name ?? 'N/A',
                        'subClass'         => $item->subClassification?->classification_name ?? 'N/A',
                    ];
                })
                ->toArray();
        } else {
            $this->itemRowReceiving = [];
        }
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
                ['index' => 'sub_total', 'label' => 'Sub-total',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'itemsHeader' => [
                ['index' => 'item_code', 'label' => 'ID'],
                ['index' => 'item_description', 'label' => 'Description'],
            ],
            'itemRow' => Item::query()
                ->where('company_id', auth()->user()->branch->company_id)
                ->when($this->search, function (Builder $query) {
                    return $query->where('item_description', 'like', "%{$this->search}%");
                })
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }

    public function saveAsDraftAction(): void
    {
         // 1. Validate the UI State
        $validated = $this->validate();
        $this->status = "DRAFT";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('Update Withdrawal', 'Are you sure to update this order as draft ?')
        ->confirm(
            'Confirm',
            'updateWithdrawal', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function saveAsFinalAction(): void
    {
        // 1. Validate the UI State
        $validated = $this->validate();
        $this->status = "FINAL";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('Update Withdrawal', 'Are you sure to update this order as final?')
        ->confirm(
            'Confirm',
            'updateWithdrawal', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function updateWithdrawal(WithdrawalService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'withdrawal_id'  => $this->withdrawalId,
                'event_id'      => $this->event_id,
                'branch_id'     => auth()->user()->branch_id,
                'preparedBy'    => auth()->user()->emp_id,
                'reviewedBy'    => $this->reviewedBy,
                'approvedBy'    => $this->approvedBy,
                'notes'         => $this->notes,
                'type_id'       => $this->typeId,
                'items'         => $this->selectedRows,
                'production_id' => $this->productionOrder_id,
                'status'        => $this->status,
                'effectiveDate' => $this->effectiveDate,
                'restockDate'   => $this->restockDate,  
                'department_id'  => $this->department_id,
            ];

            // 4. Call the Service
            $po = $service->updateWithdrawal($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Withdrawal {$po->reference} created successfully!")->send();
            $this->reset();
            return redirect()->route('withdrawal-summary');

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("Withdrawal Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Inventory', 'link' => route('withdrawal-summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Withdrawal Summary', 'link' => route('withdrawal-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Edit withdrawal', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
          <label class="text-2xl italic mb-3">( {{ $reference }} )</label>

    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-4 w-full h-full mb-4">
                <div class="grid gap-3 p-2">
                    <x-ts-date wire:model.live="effectiveDate" label="Efective Date" />
                    <x-ts-date wire:model.live="restockDate" label="Restock Date" placeholder="(optional)"/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.active.withdrawal-type', ['branch_id' => auth()->user()->branch_id])"
                        label="Withdrawal Type"
                        wire:model='typeId'
                        select="label:name|value:id|description:description"
                        :placeholders="[
                            'default' => 'Select Supplier',
                            'search'  => 'Search Supplier',
                            'empty'   => 'No Added Supplier',
                        ]"
                    />
                    <x-ts-select.styled
                        :request="route('api.active.department', ['branch_id' => auth()->user()->branch_id])"
                        select="label:department_name|value:id|description:department_description"
                            wire:model="department_id"
                            label="Department *"
                            :placeholders="[
                            'default' => 'Terms',
                            'empty'   => 'No available terms found',
                        ]" ... />
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                    :request="route('api.active.event', ['branch_id' => auth()->user()->branch_id])"
                    wire:model="event_id"
                    select="label:event_name|value:id|description:reference"
                    label="Event"
                    :placeholders="[
                    'default' => 'Select Event (Optional)',
                    'search'  => 'Search Event',
                    'empty'   => 'No Event found',
                    ]" ... />

                    <x-ts-select.styled
                    label="Production Order"
                    :request="route('api.active.production-order',['branch_id' => auth()->user()->branch_id])"
                    wire:model="productionOrder_id"
                    select="label:reference|value:id"
                    :placeholders="[
                    'default' => 'Select Production (Optional)',
                    'search'  => 'Search Production order',
                    'empty'   => 'No production order found',
                    ]" ... />

                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <x-ts-table :headers="$selectedItemHeader" :rows="$selectedRows" striped expandable>
                    <x-slot:footer>
                        <x-ts-button icon="plus" position="left" class="mt-2" x-on:click="$tsui.open.modal('modal-add-item')" flat>Add Item </x-ts-button>
                    </x-slot:footer>
                    @interact('column_action', $row)
                        <x-ts-button
                                color="rose"
                                outline
                                wire:click="removeItem({{ $loop->index }})"
                                loading="removeItem({{ $loop->index }})">

                                <x-ts-icon name="trash"
                                    wire:loading.remove
                                    wire:target="removeItem({{ $loop->index }})"
                                    class="w-5 h-5" />
                            </x-ts-button>
                    @endinteract

                    @interact('column_quantity', $row)
                       <x-ts-input type="number"
                        sm
                        wire:model.live.debounce.500ms="selectedRows.{{ $loop->index }}.quantity" />
                    @endinteract

                    @interact('column_cost', $row)
                         ₱ {{ number_format($row['cost'], 2) }}
                    @endinteract

                    @interact('column_sub_total', $row)
                        ₱  {{ number_format($row['sub_total'], 2) }}
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
                    <x-ts-stats number="{{$grand_total}}" title="Total Cost">
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                    </x-ts-stats>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-5 gap-2">
                        <div class="col-span-2">
                            <x-ts-select.styled
                            :request="route('api.active.withdrawal-reviewers', ['branch_id' => auth()->user()->branch_id ])"
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
                                :request="route('api.active.withdrawal-approvers', ['branch_id' => auth()->user()->branch_id])"
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
                                <x-ts-dropdown>
                                    <x-slot:action>
                                        <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">UPDATE
                                            AS</x-ts-button>
                                    </x-slot:action>
                                    <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT"
                                        wire:click="saveAsDraftAction()" />
                                    <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator
                                        wire:click="saveAsFinalAction()" />
                                </x-ts-dropdown>
                            </div>
                        </div>

                    </div>

                    <div>
                        <x-ts-step wire:model="step" circles>
                            <x-ts-step.items step="1"
                                        title="Create Withdrawal"
                                        description="Step 1">
                            </x-ts-step.items>
                            <x-ts-step.items step="2"
                                        title="For Review"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        completed
                                        title="For Approval"
                                        description="Step 3">
                            </x-ts-step.items>
                            <x-ts-step.items step="4"
                                        completed
                                        title="Completed"
                                        description="Step 6">
                                        <b>Withdrawal Completed!</b>
                            </x-ts-step.items>
                        </x-ts-step>
                    </div>
                </div>
            </div>
        </x-ts-card>
    </div>

    <x-ts-modal id="modal-add-item" size="5xl">
        <x-ts-tab selected="All Items">
            <x-ts-tab.items tab="All Items" icon="archive-box">
                <x-ts-card class="p-4 max-h-200 overflow-y-auto">
                    <x-ts-table  :headers="$itemsHeader" :rows="$itemRow" striped  filter  paginate selectable wire:model.live='selectedItem' />
                </x-ts-card>
            </x-ts-tab.items>
            <x-ts-tab.items tab="Receiving" icon="check-badge">
                <x-ts-card class="p-4 max-h-200 overflow-y-auto">
                    <div class="mb-4">
                        <x-ts-select.styled
                            :request="route('api.active.receiving-number', ['branch_id' => auth()->user()->branch_id])"
                            label="Receiving"
                            wire:model.live='receivingId'
                            select="label:reference|value:id|description:remarks"
                            :placeholders="[
                                'default' => 'Select Receiving Number',
                                'search'  => 'Search receiving',
                                'empty'   => 'No existing Receiving',
                            ]"
                        />
                    </div>
                    <x-ts-table  :headers="$itemsHeader" :rows="$itemRowReceiving" striped paginate selectable wire:model.live='selectedItem'/>
                </x-ts-card>
            </x-ts-tab.items>
        </x-ts-tab>
        <x-slot:footer>
                    <x-ts-button icon="check" x-on:click="$tsui.close.modal('modal-add-item')">Done</x-ts-button>
                </x-slot:footer>
    </x-ts-modal>

    <x-ts-back-to-top />
</div>
