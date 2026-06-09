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

    public $aflId, $aflDate, $event, $disburser, $preparedBy, $approvedBy, $aflAmount, $totalExpense, $amountReturned, $notes, $amountToReturn, $aflRef;
    public $status = 'DRAFT',
        $crsId,
        $crsData;

    public function mount($id)
    {
        $this->crsId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $this->crsData = CashReturn::find($this->crsId);
        $this->aflId = $this->crsData->advances_liquidation_id;
        if ($this->crsData) {
            $afl = AdvancesForLiquidation::find($this->aflId);
            if ($afl) {
                $this->aflRef = $afl->reference;
                $this->aflDate = $afl->created_at;
                $this->event = $afl->event?->event_name;
                $this->disburser = $afl->receivedBy?->full_name;
                $this->preparedBy = $afl->preparedBy?->full_name;
                $this->approvedBy = $afl->approvedBy?->full_name;
                $this->aflAmount = $afl->amount_received;
                $this->totalExpense = AdvancesForLiquidationService::totalExpense($this->aflId);
                $this->amountToReturn = $this->crsData->amount_returned;
                $this->amountReturned = $this->amountToReturn;
            }
        }
    }
}; ?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Transaction', 'link' => route('cash-return.summary-tab'), 'icon' => 'archive-box'],
            ['label' => 'Cash Return Summary', 'link' => route('cash-return.summary-tab'), 'icon' => 'list-bullet'],
            ['label' => 'Advances for liquidation cash return view', 'icon' => 'pencil-square'],
        ]" class="mb-3" />
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-2 w-full p-3 gap-3">
                <div class="grid gap-3">
                    <x-ts-input label="Advances for liquidation" wire:model="aflRef" readonly />

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
                    <x-ts-currency label="Return Amount" wire:model="amountReturned" mutate symbol readonly />
                    <x-ts-textarea label="Notes" resize maxlength="225" count placeholder="Add note here..."
                        wire:model="notes" readonly />
                </div>

            </div>
            <x-slot:footer>
                <div class="flex justify-end gap-2">
                    <x-ts-button icon="arrow-left" outline :href="route('cash-return.summary-tab')">Back</x-ts-button>
                    @if ($crsData->status == 'DRAFT')
                        <x-ts-button icon="pencil-square" :href="route('cash-return.afl-crs.edit', ['id' => $crsId])">Edit</x-ts-button>
                    @else
                        <x-ts-button icon="pencil-square" disabled>Edit</x-ts-button>
                    @endif

                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>
    <x-ts-back-to-top />
</div>
