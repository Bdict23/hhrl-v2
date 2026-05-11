<?php

use Livewire\Component;
use App\Models\Inventory\Receiving;
use App\Models\Inventory\Cardex;
use TallStackUi\Traits\Interactions;
use App\Services\Inventory\FixedAssetService;


new class extends Component
{
    use Interactions;

    // Form Inputs
    public $grand_total = 0.00;
    public $selectedRows = []; // Main registration table
    public $dateIssued, $purpose, $type_id, $purchaseOrderId, $notes, $approvedBy, $reviewedBy;

    protected $rules = [
            'type_id' => 'nullable|exists:system_parameters,id',
            'purchaseOrderId' => 'required|exists:requisition_infos,id',
            'dateIssued' => 'required',
            'purpose' => 'nullable|max:255',
            'notes' => 'nullable|max:250',
            'approvedBy' => 'required|exists:employees,id',
            'reviewedBy' => 'required|exists:employees,id',
            'selectedRows' => 'required|array|min:1',
            'selectedRows.*.condition' => 'required_if:selectedRows.*.is_serialized,false',
            'selectedRows.*.useful_life' => 'required_if:selectedRows.*.is_serialized,false',
            'selectedRows.*.serialized_items.*.serial_number' => 'required_if:selectedRows.*.is_serialized,true',
            'selectedRows.*.serialized_items.*.condition' => 'required_if:selectedRows.*.is_serialized,true',
            'selectedRows.*.serialized_items.*.useful_life' => 'required_if:selectedRows.*.is_serialized,true',
        ];

    // Selection Modal State
    public $receivingReferences = [];
    public $selectedReceivingId;
    public $itemRow = [];
    public $selectedItem = [];

    public function updatedPurchaseOrderId($value)
    {
        $this->receivingReferences = Receiving::where('requisition_id', $value)->get();
    }

    public function updatedSelectedReceivingId($value)
    {
        // Convert to array immediately to prevent "Undefined array key" errors in Blade
        $this->itemRow = Cardex::with('item', 'cost')
            ->where('receiving_id', $value)
            ->get()
            ->toArray();

        // Reset selection when changing reference
        $this->selectedItem = [];
    }
    public function saveAction()
    {
        $this->validate();
         $this->dialog()
            ->question('New Purchase Order', 'Are you sure to save this order ?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
                )
            ->cancel('Cancel')
            ->send();

    }

    public function store(FixedAssetService $service)
    {
        try {
            $data = [
                'status'            => 'DRAFT',
                'type_id'           => $this->type_id,
                'requisition_id'    => $this->purchaseOrderId,
                'branch_id'         => auth()->user()->branch_id,
                'note'              => $this->notes,
                'purpose'           => $this->purpose,
                'prepared_by'       => auth()->user()->emp_id,
                'reviewed_by'       => $this->reviewedBy,
                'approved_by'       => $this->approvedBy,
                'issued_date'       => $this->dateIssued,
                'items'             => $this->selectedRows,
            ];

            // 4. Call the Service
            $po = $service->createBatch($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Fixed Asset Batch {$po->reference} created successfully!")->send();
            $this->reset();

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PO Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

    public function insertItems()
    {
        if (empty($this->selectedItem)) {
            $this->toast()->error('Error', 'Please select at least one item.')->send();
            return;
        }

        foreach ($this->selectedItem as $id) {
            // Check if item is already in the main table to avoid duplicates
            if (collect($this->selectedRows)->contains('id', $id)) continue;

            // Find the item data from our modal array
            $sourceData = collect($this->itemRow)->firstWhere('id', $id);

            if ($sourceData) {
                $qty = (int) ($sourceData['qty_in'] ?? 1);
                $isSerialized = (bool) ($sourceData['is_serialize'] ?? false);

                $newRow = [
                    'id'               => $sourceData['id'],
                    'item_id'          => $sourceData['item_id'],
                    'item_code'        => $sourceData['item']['item_code'],
                    'item_description' => $sourceData['item']['item_description'],
                    'price_id'         => $sourceData['cost']['id'] ?? null,
                    'cost'             => $sourceData['cost']['amount'] ?? 0,
                    'quantity'         => $qty,
                    'sub_total'        => (float) ($sourceData['cost']['amount'] ?? 0),
                    'is_serialized'    => $isSerialized,
                    'condition'        => 'NEW',
                    'useful_life'      => null,
                    'serialized_items' => []
                ];

                // Populate individual sub-rows for serialized items
                if ($isSerialized) {
                    for ($i = 0; $i < $qty; $i++) {
                        $newRow['serialized_items'][] = [
                            'serial_number' => '',
                            'condition'     => 'NEW',
                            'useful_life'   => null,
                        ];
                    }
                }

                $this->selectedRows[] = $newRow;
            }
        }

        $this->calculateGrandTotal();
        // $this->modal()->close('modal-add-item');
    }

    public function removeItem($index)
    {
        unset($this->selectedRows[$index]);
        $this->selectedRows = array_values($this->selectedRows);
        $this->calculateGrandTotal();
    }

    public function calculateGrandTotal()
    {
        $total = collect($this->selectedRows)->sum('sub_total');
        $this->grand_total = number_format($total, 2, '.', '');
    }

    public function with(): array
    {
        return [
            'selectedItemHeader' => [
                ['index' => 'item_code', 'label' => 'Code'],
                ['index' => 'item_description', 'label' => 'Description'],
                ['index' => 'quantity', 'label' => 'Qty'], // Read Only
                ['index' => 'condition', 'label' => 'Condition', 'sortable' => false],
                ['index' => 'useful_life', 'label' => 'Useful Life', 'sortable' => false],
                ['index' => 'action', 'label' => 'Action', 'sortable' => false],
            ],
            'itemsHeader' => [
                ['index' => 'item_code', 'label' => 'Code'],
                ['index' => 'item_description', 'label' => 'Description'],
                ['index' => 'qty_in', 'label' => 'Qty Received'],
                ['index' => 'price_level_id', 'label' => 'Cost'],
                ['index' => 'is_serialize', 'label' => 'Serialize'],
            ],
        ];
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Inventory','link' => route('fixed-asset.menu'), 'icon' => 'archive-box' ],
                              ['label' => 'Fixed Asset', 'link' => route('fixed-asset.menu'), 'icon' => 'list-bullet'],
                              ['label' => 'Register Asset', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-3 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        label="Purchase Order"
                        select="label:requisition_number|value:id|description:remarks"
                        :placeholders="[
                            'default' => 'Select',
                            'search'  => 'Search Purchase Order',
                            'empty'   => 'No received purchase order found',
                        ]"
                        wire:model.live="purchaseOrderId"
                        :request="route('api.received.purchase-order.filter1', ['branch_id' => auth()->user()->branch_id])"
                    />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='dateIssued' label="Date Issued" required/>
                    @error('dateIssued')
                        <p class="text-red-500 text-sm font-semibold">{{ $message }}</p>
                    @enderror
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.active.asset-registration-type', ['branch_id' => auth()->user()->branch_id])"
                        label="Type"
                        select="label:name|value:id"
                        placeholder="Select"
                        wire:model='type_id'
                        required
                        />
                    <x-ts-input label="Purpose" wire:model="purpose" />
                </div>
                <div class="p-10 justify-center">
                    {{-- <span class="inline-flex items-center justify-center rounded-full border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 w-full">DRAFT</span> --}}
                    <x-ts-badge text="DRAFT" light  class="w-full justify-center" lg/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <x-ts-table :headers="$selectedItemHeader" :rows="$selectedRows" striped expandable>
                    <x-slot:footer>
                        <x-ts-button icon="plus" class="mt-2" x-on:click="$tsui.open.modal('modal-add-item')" flat>Add Item</x-ts-button>
                    </x-slot:footer>

                    @interact('column_quantity', $row)
                        <span class="font-bold">{{ $row['quantity'] }}</span>
                    @endinteract

                    @interact('column_condition', $row)
                        @if(!$row['is_serialized'])
                            <x-ts-select.styled
                                :options="[['label' => 'New', 'value' => 'NEW'], ['label' => 'Used', 'value' => 'USED']]"
                                wire:model.live="selectedRows.{{ $loop->index }}.condition" sm />
                        @else
                            <span class="text-gray-400 italic text-xs uppercase">Serialized Item</span>
                        @endif
                    @endinteract

                    @interact('column_useful_life', $row)
                        @if(!$row['is_serialized'])
                            <x-ts-input type="number" sm wire:model.live="selectedRows.{{ $loop->index }}.useful_life" />
                        @else
                            <span class="text-gray-400 italic text-xs">See sub-table</span>
                        @endif
                    @endinteract

                    @interact('column_action', $row)
                        <x-ts-button.circle icon="trash" color="red" sm wire:click="removeItem({{ $loop->index }})" />
                    @endinteract

                    {{-- SUB-TABLE FOR SERIALIZED ITEMS --}}
                    @interact('sub_table', $row)
                        @if($row['is_serialized'])
                            <div class="p-4 bg-gray-50 rounded-lg border border-blue-100">
                                <h4 class="text-xs font-bold mb-3 uppercase text-blue-700">Individual Asset Details</h4>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b text-gray-500">
                                            <th class="p-2 text-left">Qty</th>
                                            <th class="p-2 text-left">Serial Number</th>
                                            <th class="p-2 text-left">Condition</th>
                                            <th class="p-2 text-left">Useful Life (Years)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['serialized_items'] as $subIndex => $serial)
                                            <tr class="border-b border-gray-100">
                                                <td class="p-2"><x-ts-input value="1" readonly sm class="w-12 bg-gray-100 font-bold text-center"/></td>
                                                <td class="p-2">
                                                    <x-ts-input wire:model="selectedRows.{{ $loop->parent->index }}.serialized_items.{{ $subIndex }}.serial_number" sm placeholder="S/N..."/>
                                                </td>
                                                <td class="p-2">
                                                    <x-ts-select.styled
                                                        :options="[['label' => 'New', 'value' => 'NEW'], ['label' => 'Used', 'value' => 'USED']]"
                                                        wire:model="selectedRows.{{ $loop->parent->index }}.serialized_items.{{ $subIndex }}.condition" sm />
                                                </td>
                                                <td class="p-2">
                                                    <x-ts-input type="number" wire:model="selectedRows.{{ $loop->parent->index }}.serialized_items.{{ $subIndex }}.useful_life" sm />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="p-2 text-xs italic text-gray-400">Non-serialized: Entry handled in main row.</div>
                        @endif
                    @endinteract
                </x-ts-table>

                @error('selectedRows')
                    <x-ts-alert title="Validation Error" text="Please ensure all required fields in the items table are filled." color="red" class="mt-2" />
                @enderror
            </x-ts-card>
        </div>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes"/>
                    <x-ts-stats :number="$grand_total" title="Total Cost" animated>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                    </x-ts-stats>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-5 gap-2">
                        <div class="col-span-2">
                            <x-ts-select.styled
                            :request="route('api.active.asset-registration-reviewers', ['branch_id' => auth()->user()->branch_id ])"
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
                                :request="route('api.active.asset-registration-approvers', ['branch_id' => auth()->user()->branch_id])"
                                wire:model="approvedBy"
                                select="label:fullName|value:id|description:position"
                                label="Approved By"
                                :placeholders="[
                                    'default' => 'Select    ',
                                    'empty'   => 'No aapprovers found',
                                ]" required />
                        </div>

                        <div class="col-span-1 items-center inline-flex mt-2">
                            <x-ts-button class=" w-full" wire:click='saveAction' loading='saveAction'>Save</x-ts-button>
                        </div>
                    </div>

                    <div>
                        <x-ts-step selected="1" circles>
                            <x-ts-step.items step="1"
                                        title="Create Batch"
                                        description="Step 1">
                            </x-ts-tep.items>
                            <x-ts-step.items step="2"
                                        title="For Review"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        completed
                                        title="For Approval"
                                        description="Step 3">
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

    <x-ts-modal id="modal-add-item" title="Add Assets from Receiving" size="5xl">
        <div class="grid gap-4">
            <x-ts-select.styled label="Select Receiving Reference"
                 placeholder="Choose a record"
                 hint="Make sure to select purchase order first before selecting receiving reference"
                 :options="$receivingReferences"
                 wire:model.live="selectedReceivingId"
                 select="label:RECEIVING_NUMBER|value:id" />

            <x-ts-table :headers="$itemsHeader" :rows="$itemRow" striped selectable wire:model.live="selectedItem">
                @interact('column_item_code', $row)
                    {{ $row['item']['item_code'] }}
                @endinteract

                @interact('column_item_description', $row)
                    {{ $row['item']['item_description'] }}
                @endinteract

                @interact('column_price_level_id', $row)
                    ₱ {{ number_format($row['cost']['amount'] ?? 0, 2) }}
                @endinteract

                @interact('column_is_serialize', $row)
                    {{-- Binding directly to the index of the itemRow array --}}
                    <x-ts-toggle wire:model.live="itemRow.{{ $loop->index }}.is_serialize" />
                @endinteract
            </x-ts-table>
        </div>

        <x-slot:footer>
            <x-ts-button color="primary" icon="check" wire:click="insertItems">Insert Selected Items</x-ts-button>
                <x-ts-button flat x-on:click="$tsui.close.modal('modal-add-item')">Close</x-ts-button>
        </x-slot:footer>
    </x-ts-modal>

    <x-ts-back-to-top />
</div>
