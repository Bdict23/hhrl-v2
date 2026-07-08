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

    public $liquidationId, $eventDate, $event, $eventReference,$eventId, $disburser, $preparedBy, $approvedBy, $checkAmount, $totalExpense, $amountReturned, $notes, $amountToReturn;
    public $status = 'DRAFT';
    public function updatedLiquidationId($value)
    {
        if ($value) {
            $liquidation = EventLiquidation::find($value);
            if ($liquidation) {
                $this->eventDate = $liquidation->created_at;
                $this->eventReference = $liquidation->event?->reference;
                $this->event = $liquidation->event?->event_name;
                $this->eventId = $liquidation->event_id;
                $this->disburser = $liquidation->receivedBy?->full_name;
                $this->preparedBy = $liquidation->preparedBy?->full_name;
                $this->approvedBy = $liquidation->approvedBy?->full_name;
                $this->checkAmount = $liquidation->event?->acknowledgment->check_amount;
                $this->totalExpense = $liquidation->total_incurred;
                $this->amountToReturn = $liquidation->event?->acknowledgment->check_amount - $liquidation->total_incurred;
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
            ->question('Save Cash return - Event liquidation ?', 'Are you sure to save this cash return as draft?')
            ->confirm(
                'Confirm',
                'store', //pass a functio to call
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
            ->question('Save Cash return - Event liquidation ?', 'Are you sure to save this cash return as draft?')
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
                'prepared_by' => Auth::user()->emp_id,
                'amount_returned' => str_replace(',', '', $this->amountReturned),
                'notes' => $this->notes,
                'event_id' => $this->eventId,
                'liquidation_id' => $this->liquidationId,
            ];
            $crs = $service->createEventCrs($data);
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
            ['label' => 'Create cash return - Event liquidation', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-select.styled label="EVENT LIQUIDATION" :request="route('api.get.cash-return.event-liquidation', ['branch_id' => auth()->user()->branch_id])"
                        select="label:reference|value:id|description:description" wire:model.live="liquidationId" />

                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='eventDate' label="LIQUIDATION DATE" disabled />
                    <x-ts-input label="EVENT REFERENCE" wire:model='eventReference' readonly />
                    <x-ts-input label="EVENT NAME" wire:model="event" readonly />
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-input label="PREPARED BY" wire:model="preparedBy" readonly />
                        <x-ts-input label="APPROVED BY" wire:model="approvedBy" readonly />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <x-ts-currency mutate decimal wire:model="checkAmount" label="CHECK AMOUNT" readonly symbol
                            currency />
                        <x-ts-currency wire:model="totalExpense" label="EVENT EXPENSE" readonly symbol currency />
                    </div>
                </div>
                <div>
                    <x-ts-currency label="RETURN AMOUNT" wire:model="amountReturned" mutate symbol />
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
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE
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
