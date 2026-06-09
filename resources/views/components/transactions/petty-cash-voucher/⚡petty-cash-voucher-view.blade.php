<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\Accounting\TemplateDetail;
use App\Models\Transaction\PettyCashVoucher;
use Illuminate\Support\Facades\Auth;
use App\Services\Transaction\PettyCashVoucherService;

new class extends Component {
    use Interactions;

    public $selectedRows = []; // Main registration table
    public $particularsRow = [];
    public bool $liquidationModal = false;
    public $pcvData;

    public $pcvId;

    // entries
    public $transTypeId;
    public $transactionId;
    public $payeeId; //mount
    public $aflId;
    public $purchaseOrderId;
    public $notes;
    public $isCustomer;
    public $status;
    public $createdBy;

    // view
    public $debit_total = 0.0;
    public $credit_total = 0.0;
    public $reference;
    public $isLiquidated = false;

    //stats
    public $liquidatedAmt = '--.--',
        $cashReturnAmt = '--.--',
        $reimbursedAmt = '--.--';

    public function rules(): array
    {
        return [
            'selectedRows' => 'required|array|min:1',
            'selectedRows.*.purchase_date' => 'required|date',
            'selectedRows.*.vendor' => 'nullable|string',
            'selectedRows.*.reference' => 'nullable|string',
            'selectedRows.*.particular' => 'required|string',
            'selectedRows.*.amount' => [
                'required',
                'regex:/^[0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})?$/',
                function ($attribute, $value, $fail) {
                    // Strips commas: "1,000.00" becomes "1000.00"
                    $numericValue = floatval(str_replace(',', '', $value));

                    if ($numericValue < 0.1) {
                        $fail('The amount must be at least 0.1.');
                    }
                },
            ],
        ];
    }
    protected $messages = [
        'selectedRows.*.purchase_date.required' => 'The purchase date is required.',
        'selectedRows.*.purchase_date.date' => 'The purchase date must be a valid date.',
        'selectedRows.*.particular.required' => 'The particular field is required.',
        'selectedRows.*.amount.required' => 'The amount is required.',
        'selectedRows.*.amount.regex' => 'The amount must be a valid number format, e.g. 1,000 or 1000.00.',
        'selectedRows.*.amount.min' => 'Invalid amount.',
        'selectedRows.required' => 'At least one item is required.',
        'selectedRows.array' => 'Invalid data format for items.',
    ];

    public function mount($id)
    {
        $this->pcvId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $this->pcvData = PettyCashVoucher::find($this->pcvId);
        $this->particularsRow = [];
        $this->particularsRow = $this->pcvData->pettyCashVoucherDetail;
        $this->debit_total = collect($this->particularsRow->where('type', 'DEBIT'))->sum('amount');
        $this->credit_total = collect($this->particularsRow->where('type', 'CREDIT'))->sum('amount');
        $this->transTypeId = $this->pcvData->account_types_id;
        $this->transactionId = $this->pcvData->template_id;
        $this->isCustomer = $this->pcvData->paid_to_customer_id != null ? true : false;
        $this->payeeId = $this->pcvData->paid_to_employee_id ?? $this->pcvData->paid_to_customer_id;
        $this->aflId = $this->pcvData->advance_liquidation_id;
        $this->purchaseOrderId = $this->pcvData->requisition_id;
        $this->notes = $this->pcvData->purpose;
        $this->status = $this->pcvData->status;
        $this->reference = $this->pcvData->reference;
        $this->isLiquidated = $this->pcvData->liquidationData->isNotEmpty() || $this->pcvData->cashReturns->isNotEmpty(); //  || $this->pcvData->status == 'DRAFT' || $this->pcvData->status == 'CANCELLED' ? true : false
        if ($this->isLiquidated) {
            $totalLiquidated = $this->pcvData->liquidationData->sum('amount');
            $this->liquidatedAmt = number_format($totalLiquidated, 2);
            if ($totalLiquidated > $this->pcvData->total_amount) {
                if ($this->pcvData->reimbursements) {
                    $this->reimbursedAmt = $this->pcvData->reimbursements->amount;
                } else {
                    $this->reimbursedAmt = 'PENDING';
                }
            } elseif ($totalLiquidated < $this->pcvData->total_amount) {
                if ($this->pcvData->cashReturns->isNotEmpty()) {
                    $this->cashReturnAmt = number_format($this->pcvData->cashReturns->where('status', 'FINAL')->sum('amount_returned'), 2);
                } else {
                    $this->cashReturnAmt = 'PENDING';
                }
            }
        } else {
            $this->isLiquidated = $this->pcvData->status != 'OPEN';
        }
    }

    public function with(): array
    {
        return [
            'particularsHeader' => [['index' => 'transaction_title', 'label' => 'Title'], ['index' => 'debit', 'label' => 'DEBIT'], ['index' => 'credit', 'label' => 'CREDIT']],
            'liquidationSnapshotHeader' => [['index' => 'purchase_date', 'label' => 'purchase date'], ['index' => 'vendor', 'label' => 'vendor'], ['index' => 'reference', 'label' => 'reference'], ['index' => 'particular', 'label' => 'particular'], ['index' => 'amount', 'label' => 'amount']],

            'selectedItemHeader' => [
                ['index' => 'purchase_date', 'label' => 'DATE'],
                ['index' => 'vendor', 'label' => 'VENDOR'],
                ['index' => 'reference', 'label' => 'REFERENCE'], // Read Only
                ['index' => 'particular', 'label' => 'PARTICULAR', 'sortable' => false],
                ['index' => 'amount', 'label' => 'AMOUNT', 'sortable' => false],
                ['index' => 'action', 'label' => 'ACTION', 'sortable' => false],
            ],

            'liquidationSnapshotRows' => $this->pcvData->liquidationData ?? [],
            'particularsRow' => $this->pcvData->pettyCashVoucherDetail ?? [],
        ];
    }

    public function insertItems()
    {
        $newRow = [
            'purchase_date' => '',
            'vendor' => '',
            'reference' => '',
            'particular' => '',
            'amount' => 0,
            'branch_id' => Auth::user()->branch_id,
        ];
        $this->selectedRows[] = $newRow;
    }

    public function removeItem($index)
    {
        unset($this->selectedRows[$index]);
        $this->selectedRows = array_values($this->selectedRows);
    }

    public function cancelLiquidation()
    {
        $this->liquidationModal = false;
    }

    public function liquidateAction()
    {
        $this->validate(); // Validate before showing confirmation
        $this->dialog()
            ->question('Save liquidation entries ?', 'Are you sure you want to save this entries?')
            ->confirm(
                'Yes',
                'liquidate', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function liquidate(PettyCashVoucherService $pcvService)
    {
        try {
            $this->liquidationModal = false;
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'pcv_id' => $this->pcvId,
                'items' => $this->selectedRows,
            ];

            // 4. Call the Service
            $pcv = $pcvService->liquidate($data);

            // 5. Success Feedback
            $this->toast()
                ->success('Success', "Petty Cash Voucher $pcv->reference liquidated successfully! with $pcv->status status")
                ->send();
            $this->refreshData();
            return;
        } catch (\Exception $e) {
            \Log::error('PCV liquidation Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while saving: ' . $e->getMessage())
                ->send();
        }
    }

    public function refreshData()
    {
        $this->transTypeId = null;
        $this->transactionId = null;
        $this->payeeId = null;
        $this->aflId = null;
        $this->purchaseOrderId = null;
        $this->notes = null;
        $this->isCustomer = null;
        $this->status = null;
        $this->createdBy = null;
        $this->selectedRows = [];
        $this->debit_total = 0.0;
        $this->credit_total = 0.0;
        $this->reference = null;
        $this->pcvData = null;
        $this->fetchData();
    }
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' => route('petty-cash-voucher.summary'), 'icon' => 'cog'],
            [
                'label' => 'Petty cash voucher summary',
                'link' => route('petty-cash-voucher.summary'),
                'icon' => 'list-bullet',
            ],
            ['label' => 'Petty cash voucher view', 'icon' => 'eye'],
        ]" class="mb-3" />
    </div>
    <div class="grid gap-4 grid-cols-2 mt-3">
        {{-- LEFT --}}
        <div>
            <div class="mb-3 flex justify-between items-center w-full gap-4">

                <x-ts-stats number="{{ $liquidatedAmt }}" class="p-3">
                    <x-slot:header>
                        LIQUIDATED
                    </x-slot:header>
                    <x-slot:right>
                        <x-ts-icon name="receipt-percent" class="w-6 h-6 text-green-500" outline />
                    </x-slot:right>
                </x-ts-stats>
                <x-ts-stats number="{{ $cashReturnAmt }}" class="p-3">
                    <x-slot:header>
                        CASH RETURN
                    </x-slot:header>
                    <x-slot:right>
                        <x-ts-icon name="inbox-arrow-down" class="w-6 h-6 text-green-500" />
                    </x-slot:right>
                    <x-slot:footer>
                        @if ($pcvData->cashReturns->isNotEmpty())
                            <i class="text-xs"> {{ $pcvData->cashReturns->first()->reference ?? '' }}</i>
                        @else
                            <i class="text-xs"></i>
                        @endif
                    </x-slot:footer>
                </x-ts-stats>
                <x-ts-stats number="{{ $reimbursedAmt }}" class="p-3">
                    <x-slot:header>
                        REIMBURSEMENT
                    </x-slot:header>
                    <x-slot:right>
                        <x-ts-icon name="banknotes" class="w-6 h-6 text-green-500" outline />
                    </x-slot:right>
                    <x-slot:footer>
                        <i class="text-xs"> {{ $pcvData->reimbursements->reference ?? '' }}</i>
                    </x-slot:footer>
                </x-ts-stats>
            </div>
            <x-ts-card header="LIQUIDATION DETAILS">
                <x-ts-table :headers="$liquidationSnapshotHeader" :rows="$liquidationSnapshotRows" striped>
                    @interact('column_purchase_date', $row)
                        {{ \Illuminate\Support\Carbon::parse($row->purchase_date)->format('M. d, Y') }}
                    @endinteract
                    @interact('column_amount', $row)
                        ₱ {{ number_format($row->amount, 2) }}
                    @endinteract
                </x-ts-table>
            </x-ts-card>
        </div>
        {{-- RIGHT --}}
        <div>
            <x-ts-card>
                <x-slot:header>
                    <div class="flex justify-between w-full">
                        <div class="inline-flex items-center gap-2">
                            <h2 class="text-sm font-bold tracking-tight uppercase italic">{{ $reference }}</h2>
                            @if ($status == 'OPEN')
                                <x-ts-badge text="{{ $status }}" xs color="amber" />
                            @elseif ($status == 'DRAFT')
                                <x-ts-badge text="{{ $status }}" xs color="secondary" />
                            @elseif ($status == 'CLOSED')
                                <x-ts-badge text="{{ $status }}" xs color="green" />
                            @else
                                <x-ts-badge text="{{ $status }}" xs color="rose" />
                            @endif
                        </div>
                        <div class="flex  justify-end items-end">
                            @if ($status == 'DRAFT')
                                <x-ts-button icon="pencil-square" class="underline" flat lg
                                    href="{{ route('petty-cash-voucher.edit', ['id' => $pcvId]) }}">Edit</x-ts-button>
                            @endif
                            <x-ts-button icon="printer" flat class="underline-offset-1 underline" lg
                                disabled>Print</x-ts-button>
                            <x-ts-button icon="calculator" flat class="underline-offset-1 underline" lg
                                wire:click="$toggle('liquidationModal')" :disabled="$isLiquidated">Liquidate</x-ts-button>
                        </div>
                    </div>
                </x-slot:header>
                <div>
                    <div class="grid gap-3 grid-cols-2">
                        <x-ts-select.styled :request="route('api.get.active-afl', ['branch_id' => auth()->user()->branch_id])"
                            select="label:reference|value:id|description:description_one" wire:model="aflId"
                            label="AFL Reference" :placeholders="[
                                'default' => 'N/A',
                                'empty' => 'No type found',
                            ]" required readonly />

                        <x-ts-select.styled label="Purchase Order"
                            select="label:requisition_number|value:id|description:remarks" :placeholders="[
                                'default' => 'N/A',
                                'search' => 'Search Purchase Order',
                                'empty' => 'No received purchase order found',
                            ]"
                            wire:model.live="purchaseOrderId" :request="route('api.active.purchase-order', ['branch_id' => auth()->user()->branch_id])" readonly />
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 w-full">
                        <x-ts-select.styled class="mt-3" :request="route('api.get.active-account-type', [
                            'company_id' => auth()->user()->branch->company_id,
                        ])" select="label:type_name|value:id"
                            wire:model.live="transTypeId" label="Account" :placeholders="[
                                'default' => 'Select',
                                'empty' => 'No reviewers found',
                            ]" required readonly />
                        <x-ts-select.styled wire:model.live="transactionId" :request="route('api.get.selected-transaction_template', [
                            'company_id' => auth()->user()->branch->company_id,
                            'transaction_type_id' => $transTypeId,
                        ])"
                            select="label:template_name|value:id" label="Transaction" :placeholders="[
                                'default' => 'Select',
                                'empty' => 'No transaction found',
                            ]" required
                            readonly />
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-3 w-full">
                        <div class="col-span-2" wire:key="payee-select-container-{{ (int) $isCustomer }}">

                            @if ($isCustomer)
                                <x-ts-select.styled wire:model.live="payeeId" :request="route('api.get.pcv-payee-customer', [
                                    'branch_id' => auth()->user()->branch_id,
                                ])"
                                    select="label:name|value:id" label="Payee (customer)" :placeholders="[
                                        'default' => 'Select',
                                        'empty' => 'No payee found',
                                    ]" required
                                    readonly />
                            @else
                                <x-ts-select.styled wire:model.live="payeeId" :request="route('api.get.pcv-payee-employee', [
                                    'branch_id' => auth()->user()->branch_id,
                                ])"
                                    select="label:name|value:id" label="Payee (employee)" :placeholders="[
                                        'default' => 'Select',
                                        'empty' => 'No payee found',
                                    ]" required
                                    readonly />
                            @endif
                        </div>
                        <div class="flex items-end pb-2">
                            <x-ts-checkbox label="Customer" wire:model.live="isCustomer" disabled />
                        </div>
                    </div>

                    <div class="grid gap-3 grid-cols-2 mt-3">
                        <x-ts-currency symbol label="Debit" sm placeholder="0.00" readonly wire:model='debit_total' />
                        <x-ts-currency symbol label="Credit" sm placeholder="0.00" readonly wire:model='credit_total' />
                    </div>
                    <div class="mt-3">
                        <x-ts-currency symbol label="Disburse amount" sm placeholder="0.00" readonly
                            wire:model='credit_total' />
                    </div>
                    <div class="mt-3">
                        <x-ts-textarea label="Note" resize maxlength="250" count placeholder="Add note .."
                            wire:model="notes" readonly />
                    </div>

                </div>
            </x-ts-card>
            <div class="mt-3">
                <x-ts-card header="PARTICULARS">
                    <x-ts-table :headers="$particularsHeader" :rows="$particularsRow" striped>
                        @interact('column_debit', $row)
                            @if ($row['type'] == 'DEBIT')
                                <x-ts-input sm value="₱ {{ number_format($row->amount, 2) }}" readonly />
                            @endif
                        @endinteract
                        @interact('column_credit', $row)
                            @if ($row['type'] == 'CREDIT')
                                <x-ts-input sm value="₱ {{ number_format($row->amount, 2) }}" readonly />
                            @endif
                        @endinteract

                    </x-ts-table>
                </x-ts-card>
            </div>
        </div>

        <x-ts-modal title="Liquidation" wire="liquidationModal" size="7xl" z-index="z-10" center persistent>
            <x-ts-card>
                <x-ts-table :headers="$selectedItemHeader" :rows="$selectedRows" striped expandable wire:ignore.self>
                    <x-slot:footer>
                        <x-ts-button icon="plus" class="mt-2" wire:click='insertItems()' loading='insertItems()'
                            flat>Add Row</x-ts-button>
                    </x-slot:footer>
                    @interact('column_purchase_date')
                        <x-ts-date format="DD [of] MMMM [of] YYYY"
                            wire:model='selectedRows.{{ $loop->index }}.purchase_date' />
                    @endinteract
                    @interact('column_vendor')
                        <x-ts-input icon="building-storefront" wire:model='selectedRows.{{ $loop->index }}.vendor' />
                    @endinteract
                    @interact('column_reference')
                        <x-ts-input icon="receipt-percent" wire:model='selectedRows.{{ $loop->index }}.reference' />
                    @endinteract
                    @interact('column_particular')
                        <x-ts-input icon="list-bullet" wire:model='selectedRows.{{ $loop->index }}.particular' />
                    @endinteract
                    @interact('column_amount')
                        <x-ts-currency clearable symbol wire:model='selectedRows.{{ $loop->index }}.amount' mutate
                            decimal />
                    @endinteract

                    @interact('column_action', $row)
                        <x-ts-button outline color="rose" sm wire:click="removeItem({{ $loop->index }})"
                            loading="removeItem({{ $loop->index }})">
                            <x-ts-icon name="trash" wire:loading.remove wire:target="removeItem({{ $loop->index }})"
                                class="w-5 h-5" />
                        </x-ts-button>
                    @endinteract

                </x-ts-table>

                @error('selectedRows')
                    <x-ts-alert title="Validation Error"
                        text="Please ensure all required fields in the items table are filled." color="red"
                        class="mt-2" />
                @enderror
            </x-ts-card>
            <x-slot:footer>
                <x-ts-button flat wire:click='cancelLiquidation'>Cancel</x-ts-button>
                <x-ts-button wire:click='liquidateAction'>Confirm</x-ts-button>
            </x-slot:footer>
        </x-ts-modal>
    </div>
    <x-ts-loading delay="short" loading="liquidate" />
</div>
