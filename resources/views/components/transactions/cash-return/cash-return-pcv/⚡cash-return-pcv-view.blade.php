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




new class extends Component
{
    use Interactions;

    // Form Inputs
    public $purchaseOrderReference,
    $selectedRows = [],
    $pcvId,
    $pcvDate,
    $transaction,
    $purchaseOrder,
    $pettyCashVoucherId,
    $notes,
    $status = 'DRAFT',
    $totalExpense,
    $afl,$source= '--.--', 
    $pcvAmount =  0.00,
    $returnAmount = 0.00,
    $previousCrsAmount = 0.00,
    $cashReturnId,
    $cashReturnData,
    $amountReturned = 0.00;

    // Selection Modal State
    public $receivingReferences = [],
    $itemRow = [],
    $selectedItem = [];


    public function mount($id)
    {
        $this->cashReturnId = $id;
        $this->fetchData();
    }
    public function fetchData()
    {
        $this->cashReturnData = CashReturn::find($this->cashReturnId);
        $pettyCashVoucherData = PettyCashVoucher::find($this->cashReturnData->pcv_id);
        $this->pcvId = $this->cashReturnData->pcv_id;
        $this->pettyCashVoucherId = $this->cashReturnData->pcv_id;
        $this->purchaseOrderReference = $this->cashReturnData->pcv->purchaseOrder->requisition_number ?? 'N/A';
        $this->pcvDate = $pettyCashVoucherData->created_at;
        $this->pcvDate = $pettyCashVoucherData->created_at;
        $this->transaction = $pettyCashVoucherData->transaction_title;
        $this->pcvAmount = ($pettyCashVoucherData->total_amount);
        $this->afl = $pettyCashVoucherData->advance_liquidation_id;
        $this->totalExpense = PettyCashVoucherService::liquidatedAmount($this->pcvId);
        $this->previousCrsAmount = (CashReturn::where('pcv_id', $this->pcvId)->where('id', '!=', $this->cashReturnId)->sum('amount_returned') ?? 0);
        $this->amountReturned = $this->cashReturnData->amount_returned;
        $this->returnAmount = number_format(($this->pcvAmount - $this->totalExpense - $this->previousCrsAmount), 2);
        $this->source = $pettyCashVoucherData->advance_liquidation_id ? 'ADVANCES' : 'REVOLVING';
        $this->notes = $this->cashReturnData->notes;
        $this->status = $this->cashReturnData->status;
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
                              ['label' => 'PCV Cash return view', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <x-slot:header>
                <div >
                    <i class="font-bold text-sm">{{$cashReturnData?->reference}}</i> 
                    @if($cashReturnData->status == 'DRAFT')
                        <x-ts-badge text="DRAFT" color="secondary" />
                     @elseif($cashReturnData->status == 'FINAL')
                        <x-ts-badge text="FINAL" color="green" light/>
                     @else
                        <x-ts-badge :text="$cashReturnData->status" xs color="red"/>
                    @endif
                </div>
                </x-slot:header>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-input label="Petty cash voucher" value="{{$cashReturnData?->pettyCashVoucher->reference}}" readonly/>
                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='pcvDate' label="PCV Date" disabled/>
                    <x-ts-input label="Purchase Order"  wire:model='purchaseOrderReference' readonly/>
                    <x-ts-input label="Transaction" wire:model="transaction" readonly/>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate decimal wire:model="pcvAmount" label="PCV Amount" readonly/>
                        <x-ts-currency wire:model="totalExpense" label="Total Expense" readonly />
                    </div>
                </div>
                <div>
                    <x-ts-currency label="Return Amount" wire:model="amountReturned" mutate symbol :disabled="$source == 'REVOLVING'" readonly/>
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes" readonly/>
                    <div class="grid grid-cols-3 gap-2 mt-3">
                        <x-ts-stats number="{{number_format($totalExpense,2)}}" title="LIQUIDATED" >
                            <x-slot:icon>
                                <x-icon-peso class="w-6 h-6" />
                            </x-slot:icon>
                        </x-ts-stats>
                        <x-ts-stats number="{{$source}}" title="SOURCE OF FUND">
                            <x-slot:icon>
                                <x-ts-icon class="w-6 h-6" name="building-library" />
                            </x-slot:icon>
                        </x-ts-stats>

                    </div>
                </div>

            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                     <x-ts-button  icon="arrow-left" outline :href="route('cash-return.summary-tab')">Back</x-ts-button>
                     @if($cashReturnData->status == 'DRAFT')
                     <x-ts-button  icon="pencil-square" :href="route('cash-return.pcv-crs.edit', ['id' => $cashReturnData->id])">Edit</x-ts-button>
                     @else
                     <x-ts-button  icon="pencil-square" disabled>Edit</x-ts-button>
                     @endif
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>
    <x-ts-back-to-top />
</div>
