<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction\CashReturn;
use App\Models\BanquetEvent\EventLiquidation;
use App\Services\Transaction\CashReturnService;
use App\Services\Transaction\AdvancesForLiquidationService;

new class extends Component {
    use Interactions;

    protected $rules = [
        'notes' => 'nullable|max:225',
    ];

    public 
    $liquidationReference,
    $liquidationId, 
    $liquidationDate, 
    $event, 
    $eventReference,
    $eventId, 
    $preparedBy, 
    $approvedBy, 
    $checkAmount, 
    $totalExpense, 
    $amountReturned, 
    $notes, 
    $amountToReturn,
    $cashReturnId;
    public $status = 'DRAFT';

    public function mount($id)
    {
        $cashReturn = CashReturn::find($id);
        if ($cashReturn) {
            $this->cashReturnId = $cashReturn->id;
            $this->liquidationId = $cashReturn->event?->banquetEventLiquidation->id;
            $this->liquidationReference = $cashReturn->event?->banquetEventLiquidation->reference;
            $this->liquidationDate = $cashReturn->event?->banquetEventLiquidation->created_at;
            $this->eventReference = $cashReturn->event?->reference;
            $this->event = $cashReturn->event?->event_name;
            $this->eventId = $cashReturn->event?->id;
            $this->preparedBy = $cashReturn->preparedBy?->full_name;
            $this->approvedBy = $cashReturn->event?->banquetEventLiquidation->approvedBy?->full_name;
            $this->checkAmount = $cashReturn->event?->banquetEventLiquidation->event?->acknowledgment?->check_amount;
            $this->totalExpense = $cashReturn->event?->banquetEventLiquidation->total_incurred;
            $this->amountToReturn = $cashReturn->amount_returned;
            $this->amountReturned = str_replace(',', '', number_format($cashReturn->amount_returned, 2));
            $this->notes = $cashReturn->notes;
            $this->status = $cashReturn->status;
        }
    }
    public function updatedLiquidationId($value)
    {
        if ($value) {
            $liquidation = EventLiquidation::find($value);
            if ($liquidation) {
                $this->liquidationDate = $liquidation->created_at;
                $this->eventReference = $liquidation->event?->reference;
                $this->event = $liquidation->event?->event_name;
                $this->eventId = $liquidation->event_id;
                $this->preparedBy = $liquidation->preparedBy?->full_name;
                $this->approvedBy = $liquidation->approvedBy?->full_name;
                $this->checkAmount = $liquidation->event?->acknowledgment->check_amount;
                $this->totalExpense = $liquidation->total_incurred;
                $this->amountToReturn = ($liquidation->event?->acknowledgment->check_amount - $liquidation->total_incurred);
                $this->amountReturned = $this->amountToReturn;
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
            ->question('Update Cash return - Event liquidation ?', 'Are you sure to update this cash return as draft?')
            ->confirm(
                'Confirm',
                'updateCashReturn', //pass a functio to call
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
            ->question('Update Cash return - Event liquidation ?', 'Are you sure to update this cash return as final?')
            ->confirm(
                'Confirm',
                'updateCashReturn', //pass a functio to call
            )
            ->cancel('Cancel')
            ->send();
    }
    public function updateCashReturn(CashReturnService $service)
    {
        try {
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'notes' => $this->notes,
                'liquidation_id' => $this->liquidationId,
                'id' => $this->cashReturnId,
            ];
            $crs = $service->updateEventCrs($data);
            $this->toast()
                ->success('Success', "Cash Return {$crs->reference} updated successfully!")
                ->send();
            $this->reset();
            return redirect()->route('cash-return.summary-tab');
        } catch (\Exception $e) {
            \Log::error('Cash return Update Failed: ' . $e->getMessage());
            $this->toast()
                ->error('Error', 'Something went wrong while updating: ' . $e->getMessage())
                ->send();
        }
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' => route('cash-return.summary-tab'), 'icon' => 'archive-box'],
            ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
            ['label' => 'Edit cash return - Event liquidation', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-input label='EVENT LIQUIDATION' readonly wire:model='liquidationReference' />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='liquidationDate' label="LIQUIDATION DATE" disabled />
                    <x-ts-input label="EVENT REFERENCE" wire:model='eventReference' readonly />
                    <x-ts-input label="EVENT NAME" wire:model="event" readonly />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-input label="PREPARED BY" wire:model="preparedBy" readonly />
                        <x-ts-input label="APPROVED BY" wire:model="approvedBy" readonly />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate  wire:model="checkAmount" label="CHECK AMOUNT" readonly symbol
                            currency />
                        <x-ts-currency wire:model="totalExpense" label="EVENT EXPENSE" readonly symbol currency/>
                    </div>
                </div>
                <div>
                    <x-ts-currency label="RETURN AMOUNT" wire:model="amountReturned" mutate symbol readonly/>
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
