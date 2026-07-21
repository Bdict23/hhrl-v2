<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\AdvancesForLiquidationService;
use App\Models\BanquetEvent\Event;



new class extends Component
{
    use Interactions;

    public $approvedById;
    public $preparedById;
    public $note;
    public $disburserId;
    public $receivedAmount;
    public $eventId;
    public $status;

    protected $rules =[
        'approvedById' => 'required|exists:employees,id',
        'preparedById' => 'required|exists:employees,id',
        'eventId' => 'nullable|exists:banquet_events,id',
        'disburserId' => 'required|exists:employees,id',
        ];

    public function mount()
    {
        $this->preparedById = Auth::user()->emp_id;
    }

    public function updatedEventId($value)
    {
        if ($value) {
            $event = Event::find($value);
            if ($event) {
                $this->note = "AFL for event : {$event->event_name} ({$event->reference})";
                $this->receivedAmount = $event->banquetEventBudget->suggested_amount ?? 0.00;
            }
        }
    }

    public function saveAsDraftAction(){
        $validated = $this->validate();
         $this->status = 'DRAFT';
         $this->dialog()
        ->question('New AFL', 'Are you sure to save this AFL as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function saveAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'OPEN';
        $this->dialog()
        ->question('New AFL', 'Are you sure to save this AFL as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(AdvancesForLiquidationService $advancesForLiquidationService)
    {
        try {
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'status' => $this->status,
                'prepared_by' => Auth::user()->emp_id,
                'received_by' => $this->disburserId,
                'date_received' => now(),
                'approved_by' => $this->approvedById,
                'amount_received' => str_replace(",", "", $this->receivedAmount),
                'event_id' => $this->eventId,
                'note' => $this->note,
            ];

            // 4. Call the Service
            $afl = $advancesForLiquidationService->create($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "AFL {$afl->reference} created successfully!")->send();
            $this->reset();
            } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("Acknowledgement Receipt Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
    public function resetForm()
    {
        $this->eventId = null;
        $this->note = null;
        $this->disburserId = null;
        $this->receivedAmount = null;
        $this->preparedById = null;
        $this->approvedById = null;
        $this->preparedById = Auth::user()->emp_id;

    }

};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction', 'link' => route('afl.summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Advance for liquidation Summary', 'link' => route('afl.summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create advances for liquidation', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    <x-ts-card>
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold tracking-tight uppercase">ADVANCES FOR LIQUIDATION</h2>
        </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="grid grid-cols-9 md:col-span-12 gap-10">
                    <div class="md:col-span-4">
                        <x-ts-select.styled
                            :request="route('api.get.active.funded-event', ['branch_id' => auth()->user()->branch_id])"
                            label="ASSOCIATED EVENT (Funded)"
                            select="label:event_name|value:id|description:reference"
                            placeholder="Select event"
                            wire:model.live='eventId'
                        />
                    </div>
                </div>

                <div class="md:col-span-12">
                        <x-ts-select.styled searchable
                                :request="route('api.get.disbursers', ['branch_id' => auth()->user()->branch_id])"
                                label="DISBURSER *"
                                select="label:full_name|value:id|description:position_name"
                                placeholder="Select disburser"
                                wire:model.live="disburserId"
                                required/>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-currency label="AMOUNT RECEIVED *" wire:model.live="receivedAmount" mutate symbol currency/>
                    </div>

                    <div class="md:col-span-8 flex flex-col justify-between space-y-5">
                        <div>
                           <x-ts-select.styled searchable
                                            :request="route('api.get.disbursers', ['branch_id' => auth()->user()->branch_id])"
                                            label="PREPARED BY"
                                            select="label:full_name|value:id|description:position_name"
                                            wire:model.live="preparedById"
                                            disabled
                                            required/>
                        </div>

                        <div>
                            <x-ts-select.styled searchable
                                            :request="route('api.get.afl-approvers', ['branch_id' => auth()->user()->branch_id])"
                                            label="APPROVED BY *"
                                            select="label:full_name|value:id|description:position_name"
                                            placeholder="Select approver"
                                            wire:model.live="approvedById"
                                            required/>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-textarea label="NOTE" wire:model="note" count maxlength="150" resize class="md:h-28"></x-ts-textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end items-center space-x-3">
                <x-ts-button  wire:click="resetForm" flat>Reset</x-ts-button>
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
    <x-ts-loading delay="short" loading="store" />
</div>
