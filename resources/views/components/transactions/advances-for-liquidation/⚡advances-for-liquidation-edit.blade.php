<?php

use Livewire\Component;
use App\Models\Business\Customer;
use TallStackUi\Traits\Interactions;
use App\Services\Transaction\AdvancesForLiquidationService;
use App\Models\Transaction\AdvancesForLiquidation;




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
    public $aflId;
    public $reference;



    protected $rules =[
        'approvedById' => 'required|exists:employees,id',
        'preparedById' => 'required|exists:employees,id',
        'eventId' => 'nullable|exists:banquet_events,id',
        ];

    public function mount($id)
    {
        $this->aflId = $id;
        $this->fetchData();
    }

    public function fetchData()
    {
        $data = AdvancesForLiquidation::find($this->aflId);
        $this->approvedById = $data->approved_by;
        $this->preparedById = $data->prepared_by;
        $this->note = $data->notes;
        $this->disburserId = $data->received_by;
        $this->receivedAmount = $data->amount_received;
        $this->eventId = $data->event_id;
        $this->status = $data->status;
        $this->reference = $data->reference;


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
                'amount_received' => $this->receivedAmount,
                'event_id' => $this->eventId,
                'note' => $this->note,
                'status' => $this->status,
                'id' => $this->aflId,
            ];

            // 4. Call the Service
            $afl = $advancesForLiquidationService->update($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "AFL {$afl->reference} created successfully!")->send();
            $this->reset();
            return redirect()->route('afl.summary');
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
    }

};
?>

<div class="p-6 font-sans">
    <div class="mb-3 flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                                  ['label' => 'Transaction', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'archive-box' ],
                                  ['label' => 'Advance for liquidation Summary', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'list-bullet'],
                                  ['label' => 'Create advances for liquidation', 'icon' => 'pencil-square'],
                      ]"  class="mb-3"/>

                      <label class="text-2xl italic">( {{ $reference }} )</label>
    </div>
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
                            wire:model='eventId'
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
                <div class="flex items-center space-x-2 mb-4">
                    <span class="w-1.5 h-4 bg-emerald-700 rounded-full"></span>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">AFL Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                       <x-ts-input label="AMOUNT RECEIVED *" wire:model.live="receivedAmount" mutate decimal symbol/>
                    </div>

                    <div class="md:col-span-6">
                            <x-ts-input label="AMOUNT RETURNED" disabled decimal symbol/>
                    </div>

                    <div class="md:col-span-12">
                        <x-ts-input label="TOTAL AFL AMOUNT" :value="number_format($receivedAmount,2)" disabled mutate decimal symbol/>
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
                                <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">UPDATE AS</x-ts-button>
                            </x-slot:action>
                            <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT" wire:click="saveAsDraftAction()"/>
                            <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator  wire:click="saveAsFinalAction()" />
                        </x-ts-dropdown>
                    </div>
            </div>
    </x-ts-card>
    <x-ts-loading delay="short" loading="update" />
</div>
