<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\Accounting\TemplateDetail;
use App\Models\Accounting\TransactionTemplate;
use App\Services\Transaction\PettyCashVoucherService;
use Illuminate\Support\Facades\Auth;
use App\Services\Transaction\RevolvingFundService;
use App\Services\Transaction\AdvancesForLiquidationService;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Models\Transaction\EmployeeAdvance;

use App\Models\Inventory\PurchaseOrder;



new class extends Component
{
    use Interactions;

    public $particularsRow = [];


    // entries
    public $transTypeId, 
    $transactionId,
    $payeeId,
    $purchaseOrderId,
    $notes,
    $isCustomer,
    $status,
    $createdBy,
    $aflId,
    $fundSource = 'REVOLVING',
    $eventId,
    $isCashAdvance,
    $cashAdvanceId;


    // view
    public $debit_total = 0.00;
    public $credit_total = 0.00 ,$dynamicBalance = 0, $staticBalance = 0,
    $hasEvent = false;


    protected function rules()
    {
        $rules = [
            'transTypeId' => 'required|exists:system_parameters,id',
            'transactionId' => 'required|exists:actng_trans_templates,id',
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
        $this->dynamicBalance = RevolvingFundService::currentBalance(Auth::user()->branch_id);
        $this->staticBalance = $this->dynamicBalance;

    }
    public function updatedAflId(){
        if($this->aflId){
            $this->dynamicBalance = AdvancesForLiquidationService::currentBalance($this->aflId);
            $this->staticBalance = $this->dynamicBalance;
            $this->fundSource = 'ADVANCES';
            $this->eventId = AdvancesForLiquidation::find($this->aflId)->event_id;
            $this->purchaseOrderId = null;
            if($this->eventId){
                $this->hasEvent = true;
            }
        }else{
            $this->hasEvent = false;
            $this->dynamicBalance = RevolvingFundService::currentBalance(Auth::user()->branch_id);
            $this->staticBalance = $this->dynamicBalance;
            $this->fundSource = 'REVOLVING';
        }
        if($this->particularsRow){
            $this->debit_total = collect($this->particularsRow)->sum('debit');
            $this->credit_total = collect($this->particularsRow)->sum('credit');
            $this->dynamicBalance = $this->staticBalance - $this->credit_total;
        }
    }

    // check if there are pending cash advances from pcv as draft
    public function updatedCashAdvanceId(){
        if($this->cashAdvanceId){
            $hasPendingPCV = PettyCashVoucherService::hasPendingCashAdvance($this->cashAdvanceId);
            if($hasPendingPCV){
                $this->cashAdvanceId = null;
                $this->dialog()
                ->warning('Pending PCV Detected', 'There are pending Petty Cash Vouchers (PCV) associated with this cash advance. Would you like to view and update them?')
                ->confirm(method:'redirectPcv', params:$hasPendingPCV)
                ->cancel('Dismiss')
                ->send();
                return;
            }
        
        $cashAdvance = EmployeeAdvance::find($this->cashAdvanceId);
        $this->payeeId = $cashAdvance->received_by;
        $this->isCustomer = false;

        foreach ($this->particularsRow as $index => $value) {
            if($this->particularsRow[$index]['type'] == 'DEBIT'){
                $this->particularsRow[$index]['debit'] = $cashAdvance->amount;
                $this->particularsRow[$index]['credit'] = 0;
                $this->debit_total = collect($this->particularsRow)->sum('debit');
            }else{
                $this->particularsRow[$index]['debit'] = 0;
                $this->particularsRow[$index]['credit'] = $cashAdvance->amount;
                $this->credit_total = collect($this->particularsRow)->sum('credit');
            }
        }
        $this->dynamicBalance = $this->staticBalance - $this->credit_total;
        
        }
    }
    public function redirectPcv($pcvId){
        //redirect
        $this->redirect(route('petty-cash-voucher.edit', $pcvId));
    }

    public function saveAsFinalAction(){
        $this->validateCashAdvance();
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
        $this->validateCashAdvance();
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

            if($this->credit_total != $this->debit_total){
                $this->toast()->error('Error', 'Debit and credit amounts do not match')->send();
                return;
            }else{
                if($this->dynamicBalance < 0){
                    $this->toast()->error('Error', 'Insufficient fund balance')->send();
                    return;
                }
            }

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
                'fund_balance' => $this->staticBalance,
                'fund_source' => $this->fundSource,
                'afl_id' => $this->aflId,
                'items' => $this->particularsRow,
                'isCashAdvance' => $this->isCashAdvance,
                'employee_advance_id' => $this->cashAdvanceId,
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

        $parts = explode('.', $key);
        $index = $parts[0];
        if (isset($parts[1]) && $parts[1] === 'debit') {
            if($this->particularsRow[$index]['debit'])
                {
                    $this->debit_total = collect($this->particularsRow)->sum('debit');
                }
        }
         if (isset($parts[1]) && $parts[1] === 'credit') {
             if($this->particularsRow[$index]['credit'])
                {
                    $this->credit_total = collect($this->particularsRow)->sum('credit');
                }
        }

        $this->dynamicBalance = $this->staticBalance - $this->credit_total;

    }

    public function validateCashAdvance(){
        if($this->isCashAdvance){
            $this->validate([
                'cashAdvanceId' => 'required|exists:employee_advances,id'
            ]);
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
            $data = TransactionTemplate::find($this->transactionId);
            $this->isCashAdvance = $data->module_type == 'CASH_ADVANCE' ? true : false;
            $item = $data->transactionDetails;
            foreach ($item as $row) {
            $this->particularsRow[] = [
                    'transaction_title_id'  => $row->accountTitle->id,
                    'transaction_title'     => $row->accountTitle->account_title,
                    'type'                  => $row->type,
                    'amount'                => 0,
                    'debit'                 => 0,
                    'credit'                => 0,
                ];
            }
            if($this->isCashAdvance){
                $this->aflId = null;
                $this->purchaseOrderId = null;
                $this->hasEvent = false;
            }else{
                $this->cashAdvanceId;
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
                        <x-ts-input wire:model.live.debounce.750ms="particularsRow.{{ $loop->index }}.debit" type="number"/>
                    @endif
                @endinteract
                @interact('column_credit', $row)
                    @if($row['type'] == 'CREDIT')
                        <x-ts-input sm type="number" wire:model.live.debounce.750ms="particularsRow.{{ $loop->index }}.credit"/>
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
            <div class="mt-3">
                @if ($aflId)
                    <x-ts-alert :dismiss="5" close title="Important Note" text="Choosing Advances for Liquidation option will allocate the PCV entries to AFL" light />
                @endif
            </div>
        </div>
        <x-ts-card>
            <div>
                <div class="grid gap-3 grid-cols-2 mt-3">
                    @if(!$isCashAdvance)
                        <div wire:key="afl-container">
                            <x-ts-select.styled
                            :request="route('api.pcv.get.active-afl', ['branch_id' => auth()->user()->branch_id ])"
                            select="label:reference|value:id|description:description_one"
                            wire:model.live="aflId"
                            label="Advances for liquidation"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No type found',
                            ]"
                            />
                        </div>

                        @if(!$hasEvent)
                             <x-ts-select.styled
                                label="Purchase Order"
                                select="label:requisition_number|value:id|description:remarks"
                                :placeholders="[
                                    'default' => 'Select',
                                    'search'  => 'Search Purchase Order',
                                    'empty'   => 'No received purchase order found',
                                ]"
                                wire:model.live="purchaseOrderId"
                                :request="route('api.get.non-event-purchase-order', ['branch_id' => auth()->user()->branch_id])"
                            />
                        @else
                            <div wire:key="event-purchase-order-container-{{ $eventId }}">
                                <x-ts-select.styled
                                    label="Event Purchase Order"
                                    select="label:requisition_number|value:id|description:remarks"
                                    :placeholders="[
                                        'default' => 'Select',
                                        'search'  => 'Search Event Purchase Order',
                                        'empty'   => 'No received event purchase order found',
                                    ]"
                                    wire:model.live="purchaseOrderId"
                                    :request="route('api.get.event-purchase-order', ['event_id' => $eventId])"
                                />
                            </div>
                        @endif
                    @else
                        <x-ts-select.styled
                            :request="route('api.get.for-disbusement-cash-advances', ['branch_id' => auth()->user()->branch_id ])"
                            select="label:reference|value:id|description:remarks"
                            wire:model.live="cashAdvanceId"
                            label="Cash Advance reference *"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No cash advances found',
                            ]"
                        />
                    @endif
                    
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
                        ]" required
                        :disabled="$isCashAdvance"
                        />
                    @else
                        <x-ts-select.styled
                            wire:model.live="payeeId"
                            :request="route('api.get.pcv-payee-employee', ['branch_id' => auth()->user()->branch_id])"
                            select="label:name|value:id"
                            label="Payee (employee)"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No payee found',
                            ]" required
                            :disabled="$isCashAdvance"
                            />
                    @endif
                    </div>
                    <div class="flex items-end pb-2">
                        <x-ts-checkbox label="Customer" wire:model.live="isCustomer" :disabled="$isCashAdvance"/>
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
                <x-ts-stats :number="$dynamicBalance" title="Fund Balance" animated>
                        <x-slot:icon>
                            <x-icon-peso class="w-6 h-6" />
                        </x-slot:icon>
                </x-ts-stats>
            </div>
            <x-slot:footer>
                 <div class="whitespace-nowrap flex justify-end">
                    <x-ts-dropdown>
                        <x-slot:action>
                            <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                        </x-slot:action>
                        <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                        <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                    </x-ts-dropdown>
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>

    <x-ts-loading delay="short" loading="redirectPcv" />
    <x-ts-loading delay="short" loading="store" />
</div>
