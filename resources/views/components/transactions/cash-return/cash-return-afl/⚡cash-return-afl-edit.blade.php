<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction\CashReturn;
use App\Models\Transaction\AdvancesForLiquidation;
use App\Services\Transaction\CashReturnService;
use App\Services\Transaction\AdvancesForLiquidationService;

new class extends Component {
    use Interactions;

    protected $rules = [
        'notes' => 'nullable|max:225',
    ];

    public $aflId, $aflDate, $event, $disburser, $preparedBy, $approvedBy, $aflAmount, $totalExpense, $amountReturned, $notes, $amountToReturn;
    public $status = 'DRAFT',
        $crsData,
        $crsId,
        $hasPendingTransaction = false;

    public function mount($id)
    {
        $this->crsId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $crsData = CashReturn::find($this->crsId);
        $this->crsData = $crsData;
        $this->notes = $crsData->notes;
        $this->aflData = AdvancesForLiquidation::find($crsData->advances_liquidation_id);
        $this->aflId = $this->aflData->id;
        $this->amountReturned = $crsData->amount_returned;
        $this->aflDate = $this->aflData->created_at;
        $this->event = $this->aflData->event?->event_name;
        $this->disburser = $this->aflData->receivedBy?->full_name;
        $this->preparedBy = $this->aflData->preparedBy?->full_name;
        $this->approvedBy = $this->aflData->approvedBy?->full_name;
        $this->aflAmount = $this->aflData->amount_received;
        $this->amountToReturn = round(AdvancesForLiquidationService::currentBalance($this->aflId),2);
        $this->hasPendingTransaction = AdvancesForLiquidationService::hasPendingTransaction($this->aflId); // check if naa pay open nga transaction sa cash return or pcv
        
    }


    public function updatedAflId($value)
    {
        if ($value) {
            $afl = AdvancesForLiquidation::find($value);
            if ($afl) {
                $this->aflDate = $afl->created_at;
                $this->event = $afl->event?->event_name;
                $this->disburser = $afl->receivedBy?->full_name;
                $this->preparedBy = $afl->preparedBy?->full_name;
                $this->approvedBy = $afl->approvedBy?->full_name;
                $this->aflAmount = $afl->amount_received;
                $this->totalExpense = round(AdvancesForLiquidationService::totalExpense($value),2);
                $this->amountToReturn = round(AdvancesForLiquidationService::currentBalance($value),2);
                $this->amountReturned = $this->amountToReturn;
                $this->hasPendingTransaction = AdvancesForLiquidationService::hasPendingTransaction($value); // check if naa pay open nga transaction sa cash return or pcv
            }
        }
    }

    public function isValidReturn()
    {
        $validateReturn = str_replace(',', '', $this->amountReturned) <= $this->amountToReturn ? true : false;
        return $validateReturn;
    }

    public function saveAsDraftAction()
    {
        $validated = $this->validate();
        if (!$this->isValidReturn()) {
            $this->toast()->error('Error', 'Invalid returned amount!')->send();
            return;
        }
        $this->status = 'DRAFT';
        $this->dialog()
            ->question('New Cash return - AFL', 'Are you sure to update this cash return as draft?')
            ->confirm(
                'Confirm',
                'update', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }

    public function saveAsFinalAction()
    {
        $validated = $this->validate();
        if (!$this->isValidReturn()) {
            $this->toast()->error('Error', 'Invalid returned amount!')->send();
            return;
        }
        $this->status = 'FINAL';
        $this->dialog()
            ->question('New Cash return - AFL', 'Are you sure to update this cash return as draft?')
            ->confirm(
                'Confirm',
                'update', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function update(CashReturnService $service)
    {
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'amount_returned' => str_replace(',', '', $this->amountReturned),
                'notes' => $this->notes,
                'advances_liquidation_id' => $this->aflId,
                'has_pending_transaction' => $this->hasPendingTransaction,
                'id' => $this->crsId,
            ];
            $crs = $service->updateAflCrs($data);
            $this->toast()
                ->success('Success', "Cash Return {$crs->reference} created successfully!")
                ->send();
            $this->reset();
        } catch (\Exception $e) {
            \Log::error('Cash return Creation Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while saving: ' . $e->getMessage())
                ->send();
        }
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' => route('cash-return.summary-tab'), 'icon' => 'archive-box'],
            ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
            ['label' => 'Advances for liquidation cash return create', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-select.styled label="Advances for liquidation" :request="route('api.get.active-afl', ['branch_id' => auth()->user()->branch_id])"
                        select="label:reference|value:id|description:notes" wire:model.live="aflId" readonly />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='aflDate' label="AFL Date" disabled />
                    <x-ts-input label="Associated Event" wire:model='event' readonly />
                    <x-ts-input label="Disburser" wire:model="disburser" readonly />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-input label="Prepared By" wire:model="preparedBy" readonly />
                        <x-ts-input label="Approved By" wire:model="approvedBy" readonly />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate decimal wire:model="aflAmount" label="AFL Amount" readonly symbol
                            currency />
                        <x-ts-currency wire:model="totalExpense" label="Total Expense" readonly symbol currency />
                    </div>
                </div>
                <div>
                    <x-ts-currency label="Return Amount" wire:model="amountReturned" mutate symbol />
                    <x-ts-textarea label="Notes" resize maxlength="225" count placeholder="Add note here..."
                        wire:model="notes" />
                </div>

            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-ts-button icon="arrow-left" outline :href="route('cash-return.summary-tab')">Back</x-ts-button>
                    <div class="whitespace-nowrap content-center">
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
            </x-slot:footer>
        </x-ts-card>
    </div>
    <x-ts-back-to-top />
</div>
