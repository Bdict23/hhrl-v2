<?php

use Livewire\Component;
use App\Models\Inventory\Receiving;
use App\Models\Inventory\Cardex;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\CashReturnService;
use Illuminate\Support\Facades\Auth;

use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\CashReturn;




new class extends Component
{
    use Interactions;

    // Form Inputs
    public $purchaseOrderReference;
    public $selectedRows = []; // Main registration table
    public $pcvId,
    $pcvDate,
    $transaction,
    $purchaseOrder,
    $purchaseOrderId,
    $notes,
    $status = 'DRAFT',
    $totalExpense;
    public $pcvAmount =  0.00;
    public $returnAmount = 0.00;

    protected $rules = [
            'notes' => 'nullable|max:250',
            'selectedRows' => 'required|array|min:1',
            'selectedRows.*.amount' => 'required',
        ];

    // Selection Modal State
    public $receivingReferences = [];
    public $selectedReceivingId;
    public $itemRow = [];
    public $selectedItem = [];



    public function updatedPurchaseOrderId($value)
    {
        $pettyCashVoucherData = PettyCashVoucher::find($value);
        $this->pcvId = $value;
        $this->purchaseOrderReference = $pettyCashVoucherData->purchaseOrder->requisition_number ?? 'N/A';
        $this->pcvDate = $pettyCashVoucherData->created_at;
        $this->transaction = $pettyCashVoucherData->transaction_title;
        $this->pcvAmount = ($pettyCashVoucherData->total_amount);
    }


    public function updatedSelectedRows($value, $key)
    {
        $parts = explode('.', $key);
        $index = $parts[0];


         if (isset($parts[1]) && $parts[1] === 'amount') {
           $this->totalExpense = collect($this->selectedRows)->sum('amount');
           $this->returnAmount = ($this->pcvAmount - $this->totalExpense) ;
        }

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

       public function saveAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'FINAL';
        $this->dialog()
        ->question('New PCV - CRS', 'Are you sure to save this crs as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();

    }
    public function saveAsDraftAction(){
        $validated = $this->validate();
        $this->status = 'DRAFT';
         $this->dialog()
        ->question('New PCV - CRS', 'Are you sure to save this crs as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function store(CashReturnService $service)
    {
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'pcv_id' => $this->pcvId,
                'prepared_by' => Auth::user()->emp_id,
                'amount_returned' => $this->returnAmount,
                'notes' => $this->notes,
                'items' => $this->selectedRows

            ];

            // 4. Call the Service
            $po = $service->createPcvCrs($data);

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
                $newRow = [
                    'purchase_date' => '',
                    'vendor'        => '',
                    'reference'     => '',
                    'particular'    => '',
                    'amount'        => 0,
                    'branch_id'     => Auth::user()->branch_id,
                ];
                $this->selectedRows[] = $newRow;
    }

    public function removeItem($index)
    {
        unset($this->selectedRows[$index]);
        $this->selectedRows = array_values($this->selectedRows);
    }

    public function with(): array
    {
        return [
            'selectedItemHeader' => [
                ['index' => 'purchase_date', 'label' => 'DATE'],
                ['index' => 'vendor', 'label' => 'VENDOR'],
                ['index' => 'reference', 'label' => 'REFERENCE'], // Read Only
                ['index' => 'particular', 'label' => 'PARTICULAR', 'sortable' => false],
                ['index' => 'amount', 'label' => 'AMOUNT', 'sortable' => false],
                ['index' => 'action', 'label' => 'ACTION', 'sortable' => false],
            ],
        ];
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction','link' => route('cash-return.summary-tab'), 'icon' => 'archive-box' ],
                              ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
                              ['label' => 'PCV Cash return create', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-3 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        label="Petty Cash Voucher"
                        select="label:reference|value:id|description:purpose"
                        :placeholders="[
                            'default' => 'Select',
                            'search'  => 'Search Purchase Order',
                            'empty'   => 'No received purchase order found',
                        ]"
                        wire:model.live="purchaseOrderId"
                        :request="route('api.get.active.petty-cash-voucher', ['branch_id' => auth()->user()->branch_id])"
                    />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='pcvDate' label="PCV Date" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Purchase Order"  wire:model='purchaseOrderReference' readonly
                        />
                    <x-ts-input label="Transaction" wire:model="transaction" readonly/>
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
                        <x-ts-button icon="plus" class="mt-2" wire:click='insertItems()' loading='insertItems()' flat>Add Row</x-ts-button>
                    </x-slot:footer>
                    @interact('column_purchase_date')
                        <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='selectedRows.{{ $loop->index }}.purchase_date' />
                    @endinteract
                    @interact('column_vendor')
                        <x-ts-input icon="building-storefront" wire:model='selectedRows.{{ $loop->index }}.vendor' />
                    @endinteract
                    @interact('column_reference')
                        <x-ts-input icon="receipt-percent" wire:model='selectedRows.{{ $loop->index }}.reference' />
                    @endinteract
                    @interact('column_particular')
                        <x-ts-input icon="list-bullet" wire:model='selectedRows.{{ $loop->index }}.particular'/>
                    @endinteract
                    @interact('column_amount')
                      <x-ts-currency clearable symbol  wire:model.live.debounce.750ms='selectedRows.{{ $loop->index }}.amount' mutate/>
                    @endinteract

                    @interact('column_action', $row)
                        <x-ts-button outline color="rose"
                            sm
                            wire:click="removeItem({{ $loop->index }})"
                            loading="removeItem({{ $loop->index }})">
                            <x-ts-icon name="trash"
                                    wire:loading.remove
                                    wire:target="removeItem({{ $loop->index }})"
                                    class="w-5 h-5" />
                        </x-ts-button>
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

                </div>
                <div class="grid gap-2 p-3">
                    <div class="grid grid-cols-5 gap-2">
                        <div class="col-span-2">
                            <x-ts-currency mutate decimal
                            wire:model="pcvAmount"
                            label="PCV Amount" readonly/>
                        </div>

                        <div class="col-span-2">
                            <x-ts-currency
                                wire:model="totalExpense"
                                label="Total Expense"
                                readonly />
                        </div>

                        <div class="col-span-1 h-fit text-center inline-flex mt=3 p-2">
                            <x-ts-dropdown>
                                <x-slot:action>
                                    <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                                </x-slot:action>
                                <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                                <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                            </x-ts-dropdown>
                        </div>
                    </div>
                     <div>
                            <x-ts-stats :number="$returnAmount" title="Return amount" animated mutate>
                                <x-slot:icon>
                                    <x-icon-peso class="w-6 h-6" />
                                </x-slot:icon>
                            </x-ts-stats>
                        </div>
                </div>
            </div>
        </x-ts-card>
    </div>
    <x-ts-back-to-top />
</div>
