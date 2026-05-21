<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\Accounting\TemplateDetail;
use App\Models\Transaction\PettyCashVoucher;
use Illuminate\Support\Facades\Auth;


new class extends Component
{
    use Interactions;

    public $particularsRow = [];
    public $pcvData;

    public $pcvId;

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
    public $credit_total = 0.00;
    public $reference;


    public function mount($id)
    {
        $this->pcvId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $this->pcvData = PettyCashVoucher::find($this->pcvId);
        foreach ($this->pcvData->pettyCashVoucherDetail as $row) {
        $this->particularsRow[] = [
                'transaction_title_id' => $row->transaction_title_id,
                'transaction_title'    => $row->transaction_title,
                'type'             => $row->type,
                'amount'            => $row->amount,
            ];
            if($row->type == 'DEBIT'){
                $this->debit_total = $row->amount;
            }else{
                $this->credit_total = $row->amount;
            }
        }
        $this->transTypeId = $this->pcvData->account_types_id;
        $this->transactionId = $this->pcvData->template_id;
        $this->transactionId = $this->pcvData->template_id;
        $this->isCustomer = $this->pcvData->paid_to_customer_id != null ? true : false;
        $this->payeeId = $this->pcvData->paid_to_employee_id ?? $this->pcvData->paid_to_customer_id;
        $this->pcvType = $this->pcvData->type_id;
        $this->purchaseOrderId = $this->pcvData->requisition_id;
        $this->notes = $this->pcvData->purpose;
        $this->status = $this->pcvData->status;
        $this->reference = $this->pcvData->reference;
    }


    public function updatedParticularsRow($value, $key)
    {
        $this->debit_total = number_format(collect($this->particularsRow)->sum('debit'), 2 );
        $this->credit_total = number_format(collect($this->particularsRow)->sum('credit'), 2 );

    }


    public function with(): array
    {
        return [
            'particularsHeader' => [
                ['index' => 'transaction_title', 'label' => 'Title'],
                ['index' => 'debit', 'label' => 'DEBIT'],
                ['index' => 'credit', 'label' => 'CREDIT' ],
            ],
        ];
    }


};
?>

<div>
    <div class="flex justify-between">
         <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                            ['label' => 'Transaction', 'link' => route('petty-cash-voucher.summary'), 'icon' => 'cog' ],
                            ['label' => 'Petty cash voucher summary', 'link' => route('petty-cash-voucher.summary'), 'icon' => 'list-bullet'],
                            ['label' => 'Petty cash voucher view',  'icon' => 'eye'],
                        ]"  class="mb-3"/>
        <label class="text-2xl italic">( {{ $reference }} )</label>
    </div>
    <div class="grid gap-4 grid-cols-2 mt-5">
        <div>
            <x-ts-table :headers="$particularsHeader" :rows="$particularsRow" striped>
                @interact('column_debit', $row)
                    @if($row['type'] == 'DEBIT')
                        <x-ts-input  :value="$row['amount']" readonly/>
                    @endif
                @endinteract
                @interact('column_credit', $row)
                    @if($row['type'] == 'CREDIT')
                        <x-ts-input sm type="number" :value="$row['amount']" readonly/>
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
                        ]" required
                        readonly
                        />

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
                        readonly
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
                        ]" required
                        readonly
                        />
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
                                ]" required
                                readonly
                                />
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
                            readonly
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
                                readonly
                                />
                        @endif
                        </div>
                        <div class="flex items-end pb-2">
                            <x-ts-checkbox label="Customer" wire:model.live="isCustomer" disabled/>
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
                            <x-ts-textarea label="Note" resize maxlength="250" count placeholder="Add note .." wire:model="notes" readonly/>
                        </div>

            </div>

                <div class="flex mt-3 gap-2">
                    @if($status == 'DRAFT')
                        <x-ts-button icon="pencil-square" class="underline"  flat lg href="{{route('petty-cash-voucher.edit',  ['id' => $pcvId])}}">Edit</x-ts-button>
                    @endif
                    <x-ts-button icon="printer"  flat class="underline-offset-1 underline" lg>Print</x-ts-button>

                </div>


        </x-ts-card>
    </div>
</div>
