<?php

use Livewire\Component;
use App\Models\Inventory\Receiving;
use App\Models\Inventory\Cardex;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\CashReturnService;
use Illuminate\Support\Facades\Auth;

use App\Models\Transaction\PettyCashVoucher;
use App\Models\Transaction\CashReturn;
use App\Services\Transaction\PettyCashVoucherService;

new class extends Component {
    use Interactions;

    // Form Inputs
    public $purchaseOrderReference;
    public $selectedRows = []; // Main registration table
    public $pcvId,
        $pcvDate,
        $transaction,
        $purchaseOrder,
        $pettyCashVoucherId,
        $notes,
        $status = 'DRAFT',
        $totalExpense,
        $afl,
        $source = '--.--';
    public $pcvAmount = 0.0;
    public $returnAmount = 0.0;
    public $previousCrsAmount = 0.0;

    protected $rules = [
        'notes' => 'nullable|max:250',
    ];

    // Selection Modal State
    public $receivingReferences = [];
    public $itemRow = [];
    public $selectedItem = [];

    public function updatedPettyCashVoucherId($value)
    {
        if ($value) {
            $this->pcvId = $value;
            $this->checkExistingDraft();
            $pettyCashVoucherData = PettyCashVoucher::find($value);
            $this->previousCrsAmount = CashReturn::where('pcv_id', $value)->sum('amount_returned') ?? 0;
            $this->purchaseOrderReference = $pettyCashVoucherData->purchaseOrder->requisition_number ?? 'N/A';
            $this->pcvDate = $pettyCashVoucherData->created_at;
            $this->transaction = $pettyCashVoucherData->transaction_title;
            $this->pcvAmount = $pettyCashVoucherData->total_amount;
            $this->afl = $pettyCashVoucherData->advance_liquidation_id;
            $this->totalExpense = PettyCashVoucherService::liquidatedAmount($this->pcvId);
            $this->returnAmount = number_format($this->pcvAmount - $this->totalExpense - $this->previousCrsAmount, 2);
            $this->source = $pettyCashVoucherData->advance_liquidation_id ? 'ADVANCES' : 'REVOLVING';
        } else {
            $this->reset();
        }
    }

    // check for existing draft CRS for the selected PCV, to prompt user to edit the existing draft instead of creating a new one
    public function checkExistingDraft()
    {
        $existingDraftCrs = CashReturn::where('pcv_id', $this->pettyCashVoucherId)->where('status', 'DRAFT')->first();
        if ($existingDraftCrs) {
            $this->dialog()
                ->question('Existing Draft Found', 'A draft cash return already exists for this PCV. Do you want to edit the existing draft?')
                ->confirm(
                    'Yes, Edit Draft',
                    'redirectToEdit', //pass a function to call
                    ['cashReturnId' => $existingDraftCrs->id], //pass parameters to the function
                )
                ->cancel(
                    'No, Create New',
                    'resetForm', //pass a function to call
                )
                ->send();
        }
    }
    public function redirectToEdit($data)
    {
        $id = $data['cashReturnId'];
        return redirect()->route('cash-return.pcv-crs.edit', ['id' => $id]);
    }

    public function resetForm()
    {
        $this->reset();
    }

    public function saveAsFinalAction()
    {
        $this->validate();
        $this->status = 'FINAL';
        $this->dialog()
            ->question('New Cash Return - PCV', 'Are you sure to save this cash return as final ?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function saveAsDraftAction()
    {
        $this->validate();
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('New Cash Return - PCV', 'Are you sure to save this cash return as draft?')
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
                'amount_returned' => str_replace(',', '', $this->returnAmount),
                'notes' => $this->notes,
                'advances_liquidation_id' => $this->afl,
            ];

            // 4. Call the Service
            $crs = $service->createPcvCrs($data);

            // 5. Success Feedback
            $this->toast()
                ->success('Success', "Cash Return {$crs->reference} created successfully!")
                ->send();
            $this->reset();
        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error('Cash return Creation Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while saving: ' . $e->getMessage())
                ->send();
        }
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
            ['label' => 'Transaction', 'link' => route('cash-return.summary-tab'), 'icon' => 'archive-box'],
            ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
            ['label' => 'PCV Cash return create', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-select.styled label="Petty Cash Voucher" select="label:reference|value:id|description:purpose"
                        :placeholders="[
                            'default' => 'Select',
                            'search' => 'Search PCV',
                            'empty' => 'No PCV found',
                        ]" wire:model.live="pettyCashVoucherId" :request="route('api.get.cash-return-pcv', ['branch_id' => auth()->user()->branch_id])" />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='pcvDate' label="PCV Date" disabled />
                    <x-ts-input label="Purchase Order" wire:model='purchaseOrderReference' readonly />
                    <x-ts-input label="Transaction" wire:model="transaction" readonly />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate  wire:model="pcvAmount" label="PCV Amount" readonly />
                        <x-ts-currency wire:model="totalExpense" label="Total Expense" readonly />
                    </div>
                </div>
                <div>
                    <x-ts-currency label="Return Amount" wire:model="returnAmount" mutate symbol :disabled="$source == 'REVOLVING'" />
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..."
                        wire:model="notes" />
                    <div class="grid grid-cols-3 gap-2 mt-3">
                        <x-ts-stats number="{{ $returnAmount }}" title="FOR RETURN" animated mutate>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                        </x-ts-stats>
                        <x-ts-stats number="{{ number_format($totalExpense, 2) }}" title="LIQUIDATED" animated mutate>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                        </x-ts-stats>
                        <x-ts-stats number="{{ $source }}" title="SOURCE OF FUND">
                            <x-slot:icon>
                                <x-ts-icon class="w-6 h-6" name="building-library" />
                            </x-slot:icon>
                        </x-ts-stats>

                    </div>
                </div>

            </div>
            <x-slot:footer>
                <div class="flex justify-end">
                    <x-ts-dropdown>
                        <x-slot:action>
                            <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE
                                AS</x-ts-button>
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
