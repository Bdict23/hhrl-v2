<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\Accounting\TemplateDetail;
use App\Models\Accounting\TransactionTemplate;
use App\Services\Transaction\PettyCashVoucherService;
use Illuminate\Support\Facades\Auth;
use App\Services\Transaction\RevolvingFundService;


new class extends Component
{
    use Interactions;

    public $particularsRow = [];


    // entries
    public $transTypeId;
    public $transactionId;
    public $payeeId; //mount
    public $pcvType;
    public $purchaseOrderId;
    public $notes;
    public $isCustomer;
    public $status;
    public $createdBy;

    // view
    public $debit_total = 0.00;
    public $credit_total = 0.00 ,$fundBalance = 0;


    protected function rules()
    {
        $rules = [
            'transTypeId' => 'required|exists:system_parameters,id',
            'transactionId' => 'required|exists:actng_trans_templates,id',
            'pcvType' => 'required|exists:system_parameters,id',
            'notes' => 'nullable|string|max:255',
            'payeeId' => 'required|exists:'.($this->isCustomer ? 'customers,id' : 'employees,id'),
        ];

        // Make purchaseOrderId required only when transaction template requires a requisition
        if ($this->isPurchaseOrderRequired()) {
            $rules['purchaseOrderId'] = 'required|exists:requisition_infos,id';
        } else {
            $rules['purchaseOrderId'] = 'nullable|exists:requisition_infos,id';
        }

        return $rules;
    }
    public function mount(){
        $this->fundBalance = RevolvingFundService::currentBalance(Auth::user()->branch_id);
    }

    public function saveAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'OPEN';
        $this->dialog()
        ->question('New PCV', 'Are you sure to save this pcv as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();

    }

    public function isPurchaseOrderRequired(): bool
    {
        $isRequire = TransactionTemplate::find($this->transactionId)->for_requisition ?? 0;
        return $isRequire == 1;
    }


    public function saveAsDraftAction(){
        $validated = $this->validate();
        $this->status = 'DRAFT';
         $this->dialog()
        ->question('New PCV', 'Are you sure to save this pcv as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(PettyCashVoucherService $pcvService)
    {
        try {
            $paid_to_customer_id = null;
            $paid_to_employee_id = null;

            if($this->isCustomer){
                $paid_to_customer_id = $this->payeeId;
            }else{
                $paid_to_employee_id = $this->payeeId;
            }
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'paid_to_employee_id' => $paid_to_employee_id,
                'paid_to_customer_id' => $paid_to_customer_id,
                'total_amount' => collect($this->particularsRow)->sum('credit'),
                'purpose' => $this->notes,
                'status' => $this->status,
                'created_by'    => Auth::user()->emp_id,
                'requisition_id'    => $this->purchaseOrderId,
                'account_types_id' => $this->transTypeId, //COA header
                'template_id' => $this->transactionId, // Template Id
                'type_id' => $this->pcvType,
                'fund_balance' => $this->fundBalance,
                'items' => $this->particularsRow,
            ];

            // 4. Call the Service
            $pcv = $pcvService->create($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Petty Cash Voucher created successfully!")->send();
            $this->reset();
            return redirect()->route('petty-cash-voucher.summary');

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PCV Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }

    }

    public function updatedParticularsRow($value, $key)
    {

        $this->debit_total = number_format(collect($this->particularsRow)->sum('debit'), 2 );
        $this->credit_total = number_format(collect($this->particularsRow)->sum('credit'), 2 );
        $isDebit = $this->particularsRow[$key]['debit'] ?? false;
        if($isDebit)
            $this->particularsRow[$key]['amount'] = $this->particularsRow[$key]['debit'];
        else{
            $this->particularsRow[$key]['amount'] = $this->particularsRow[$key]['credit'];
        }

    }


    public function with(): array
    {
        return [
            'particularsHeader' => [
                ['index' => 'transaction_title', 'label' => 'Title'],
                ['index' => 'debit', 'label' => 'DEBIT'],
                ['index' => 'credit', 'label' => 'CREDIT' ],
                ['index' => 'action', 'label' => 'Action' ],
            ],
        ];
    }


    public function removeItem($index)
    {
        unset($this->particularsRow[$index]);
        // Reset array keys to prevent index gaps
        $this->particularsRow = array_values($this->particularsRow);

        // Sync back to your original selection ID array if necessary
        $this->toast()->success('Success', 'Removed Successfully')->send();
    }

    public function updatedTransactionId()
    {

        if ($this->transactionId) {
            $this->particularsRow = [];
            $item = TemplateDetail::with('accountTitle')->where('template_id', $this->transactionId)->get();
            foreach ($item as $row) {
            $this->particularsRow[] = [
                    'transaction_title_id' => $row->accountTitle->id,
                    'transaction_title'    => $row->accountTitle->account_title,
                    'type'             => $row->type,
                    'amount'            => 0,
                ];
            }
        }
    }

    public function updatedTransTypeId()
    {
        $this->reset('transactionId');

    }
    public function updatedIsCustomer()
    {
        $this->reset('payeeId');
    }


};
?>

<div>
    <div >
         <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                            ['label' => 'Transaction', 'link' => route('petty-cash-voucher.summary') , 'icon' => 'cog'],
                            ['label' => 'Petty cash voucher summary', 'link' => route('petty-cash-voucher.summary'), 'icon' => 'list-bullet'],
                            ['label' => 'Petty cash voucher create', 'icon' => 'pencil-square'],
                        ]"  class="mb-3"/>
    </div>
    <div class="grid gap-4 grid-cols-2 mt-5">
        <div>
            <x-ts-table :headers="$particularsHeader" :rows="$particularsRow" striped>
                @interact('column_debit', $row)
                    @if($row['type'] == 'DEBIT')
                        <x-ts-input wire:model.live.debounce.750ms="particularsRow.{{ $loop->index }}.debit"/>
                    @endif
                @endinteract
                @interact('column_credit', $row)
                    @if($row['type'] == 'CREDIT')
                        <x-ts-input sm type="number" wire:model.live.debounce.750ms="particularsRow.{{ $loop->index }}.credit" />
                    @endif
                @endinteract
                @interact('column_action', $row)
                        @if ($row['type'] == 'DEBIT')
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
                        @endif
                    @endinteract
            </x-ts-table>
        </div>
        <x-ts-card>
            <div>
                <div class="grid gap-3 grid-cols-2 mt-3">
                    <x-ts-select.styled
                        :request="route('api.get.pcv-type', ['branch_id' => auth()->user()->branch_id ])"
                        select="label:name|value:id"
                        wire:model="pcvType"
                        label="Type"
                        :placeholders="[
                        'default' => 'Select',
                        'empty'   => 'No type found',
                        ]" required/>

                        <x-ts-select.styled
                        label="Purchase Order"
                        select="label:requisition_number|value:id|description:remarks"
                        :placeholders="[
                            'default' => 'Select',
                            'search'  => 'Search Purchase Order',
                            'empty'   => 'No received purchase order found',
                        ]"
                        wire:model.live="purchaseOrderId"
                        :request="route('api.active.purchase-order', ['branch_id' => auth()->user()->branch_id])"
                    />
                </div>

                    <div class="mt-3">
                        <x-ts-select.styled
                        class="mt-3"
                        :request="route('api.get.active-account-type', ['company_id' => auth()->user()->branch->company_id ])"
                        select="label:type_name|value:id"
                        wire:model.live="transTypeId"
                        label="Account"
                        :placeholders="[
                        'default' => 'Select',
                        'empty'   => 'No reviewers found',
                        ]" required/>
                    </div>

                    <div wire:key="transaction-container-{{ $transTypeId }}" class="mt-3">
                            <x-ts-select.styled
                                wire:model.live="transactionId"
                                :request="route('api.get.selected-transaction_template', ['company_id' => auth()->user()->branch->company_id, 'transaction_type_id' => $transTypeId])"
                                select="label:template_name|value:id"
                                label="Transaction"
                                :placeholders="[
                                    'default' => 'Select',
                                    'empty'   => 'No transaction found',
                                ]" required />
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-3 w-full">
                        <div class="col-span-2" wire:key="payee-select-container-{{ (int) $isCustomer }}">

                        @if($isCustomer)
                            <x-ts-select.styled
                            wire:model.live="payeeId"
                            :request="route('api.get.pcv-payee-customer', ['branch_id' => auth()->user()->branch_id])"
                            select="label:name|value:id"
                            label="Payee (customer)"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No payee found',
                            ]" required/>
                        @else
                            <x-ts-select.styled
                                wire:model.live="payeeId"
                                :request="route('api.get.pcv-payee-employee', ['branch_id' => auth()->user()->branch_id])"
                                select="label:name|value:id"
                                label="Payee (employee)"
                                :placeholders="[
                                'default' => 'Select',
                                'empty'   => 'No payee found',
                                ]" required/>
                        @endif
                        </div>
                        <div class="flex items-end pb-2">
                            <x-ts-checkbox label="Customer" wire:model.live="isCustomer" />
                        </div>
                    </div>

                    <div class="grid gap-3 grid-cols-2 mt-3">
                        <x-ts-input label="Debit" sm placeholder="0.00" readonly wire:model='debit_total'/>
                        <x-ts-input label="Credit" sm placeholder="0.00" readonly wire:model='credit_total'/>
                    </div>
                        <div class="mt-3">
                            <x-ts-input label="Disburse amount" sm placeholder="0.00" readonly wire:model='credit_total'/>
                        </div>
                        <div class="mt-3">
                            <x-ts-textarea label="Note" resize maxlength="250" count placeholder="Add note .." wire:model="notes"/>
                        </div>

            </div>

                <div class="flex  justify-between mt-3 gap-2">
                    <x-ts-stats :number="$fundBalance" title="Fund Balance" animated>
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                    </x-ts-stats>
                    <div class="whitespace-nowrap content-center">
                        <x-ts-dropdown>
                            <x-slot:action>
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                            </x-slot:action>
                            <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                            <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                        </x-ts-dropdown>
                    </div>
                </div>


        </x-ts-card>
    </div>
</div>
