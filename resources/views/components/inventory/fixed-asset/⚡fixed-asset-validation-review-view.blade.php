<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Services\Inventory\FixedAssetService;


use App\Models\Inventory\FixedAsset\AssetBatchHeader;

new class extends Component
{
    use Interactions;

    public $step;
    public $reference;

    public $batchId;
    public $isFinal;
    public $isCompleted;
    public $dateIssued, $purpose, $type_id, $purchaseOrderId, $notes, $approvedBy, $reviewedBy;

    public $header;
    public $rows = [];
    public $grand_total = 0;

   public function mount($id)
    {
        $this->batchId = $id;
        $this->loadData();
    }

    public function loadData(){
         // Load the header with all necessary relationships
        $this->header = AssetBatchHeader::with([
            'assetBatchDetails.item',
            'type',
            'branch',
            'reviewedBy',
            'approvedBy',
            'preparedBy',
            'purchaseOrder',
        ])->findOrFail($this->batchId);

        // Group the details to reconstruct the "Line Items" seen during registration
        $this->rows = $this->header->assetBatchDetails->groupBy('item_id')->map(function ($group) {
            $firstRecord = $group->first();

            // An item is considered serialized if any record in the group has a serial number
            $isSerialized = $group->contains(fn($detail) => !empty($detail->serial));

            return [
                'item_code'        => $firstRecord->item->item_code,
                'item_description' => $firstRecord->item->item_description,
                'is_serialized'    => $isSerialized,
                'total_qty'        => $group->sum('qty'),
                // For main row display: show 'VARIOUS' if items have different conditions
                'condition'        => $isSerialized ? '(various items)' : $firstRecord->condition,
                'useful_life'      => $isSerialized ? '(various items)' : $firstRecord->lifespan,
                'sub_total'        => $isSerialized
                                        ? $group->sum(fn($d) => $d->cost * $d->qty)
                                        : $firstRecord->cost,
                // All individual records for the expandable sub-table
                'serialized_items' => $group->toArray(),
            ];
        })->values()->toArray();

        $this->grand_total = collect($this->rows)->sum('sub_total');

         // Populate form fields with data from the header
        $this->purchaseOrderId = $this->header->requisition_id;
        $this->dateIssued = $this->header->issued_date;
        $this->type_id = $this->header->type_id;;
        $this->purpose = $this->header->purpose;
        $this->notes = $this->header->note;
        $this->approvedBy = $this->header->approved_by;
        $this->reviewedBy = $this->header->reviewed_by;
        $this->isFinal = $this->header->status != 'DRAFT' ? true : false;
        $this->isCompleted = $this->header->status == 'COMPLETED' ? true : false;
        $this->reference = $this->header->purchaseOrder->requisition_number ?? 'N/A';

        //step logic
        if($this->header->status == 'DRAFT'){
            $this->step = 1;
        }elseif($this->header->status == 'OPEN' && $this->header->approved_date == null && $this->header->reviewed_date == null){
            $this->step = 2;
        }elseif($this->header->status == 'OPEN' && $this->header->reviewed_date != null && $this->header->approved_date == null){
            $this->step = 3;
        }elseif($this->header->status == 'CLOSED'){
            $this->step = 4;
        }else{
            $this->step = 1;
        }

    }

    public function reviewAction(){
        $this->dialog()
        ->question('Update Status', 'Mark this batch as reviewed ?')
        ->confirm(
            'Yes! Confirm',
            'applyReview',
            )
        ->cancel('Cancel')
        ->send();
    }
    public function reviseAction(){
        $this->dialog()
        ->question('Revise Batch', 'Mark this batch as for revision ?')
        ->confirm(
            'Yes! Confirm',
            'applyRevise',
            )
        ->cancel('Cancel')
        ->send();
    }
    public function applyRevise(FixedAssetService $service){
        try {
            $service->batchRevised($this->batchId);
            $this->toast()->success('Success', 'Asset Batch has been marked as for revision successfully!')->send();
            //refresh data to reflect changes
            $this->loadData();
        } catch (\Exception $e) {
           $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

    public function applyReview(FixedAssetService $service){
        try {
            $service->batchReviewed($this->batchId);
            $this->toast()->success('Success', 'Asset Batch has been marked as reviewed successfully!')->send();
            //refresh data to reflect changes
            $this->loadData();
        } catch (\Exception $e) {
           $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }

    public function with(): array
    {
        return [
            'headers' => [
                ['index' => 'item_code', 'label' => 'Code'],
                ['index' => 'item_description', 'label' => 'Description'],
                ['index' => 'total_qty', 'label' => 'Qty'],
                ['index' => 'condition', 'label' => 'Condition'],
                ['index' => 'useful_life', 'label' => 'Useful Life'],
                ['index' => 'sub_total', 'label' => 'Sub Total'],
            ],
        ];
    }

};
?>

<div>
   <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Validation','link' => route('fixed-asset.validation-summary'), 'icon' => 'check-badge' ],
                              ['label' => 'Fixed Asset', 'link' => route('fixed-asset.validation-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Fixed Asset Review', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-3 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-input label="Purchase Order Reference" wire:model="reference" readonly/>
                    <x-ts-date format="DD [of] MMMM [of] YYYY" wire:model='dateIssued' label="Date Issued" readonly/>
                    @error('dateIssued')
                        <p class="text-red-500 text-sm font-semibold">{{ $message }}</p>
                    @enderror
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.active.asset-registration-type', ['branch_id' => auth()->user()->branch_id])"
                        label="Type"
                        select="label:name|value:id"
                        placeholder="Select"
                        wire:model='type_id'
                        readonly

                        />
                    <x-ts-input label="Purpose" wire:model="purpose" readonly />
                </div>
                <div class="p-10 justify-center">
                    {{-- <span class="inline-flex items-center justify-center rounded-full border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 w-full">DRAFT</span> --}}
                    <x-ts-badge
                    :text="$isFinal ? 'FINALIZED' : 'DRAFT'"
                    light
                    class="w-full justify-center" lg/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <div class="w-full">
            <x-ts-card>
                <x-ts-table :headers="$headers" :rows="$rows" striped expandable>
                    {{-- Format Sub-Total --}}
                    @interact('column_sub_total', $row)
                        <span class="font-bold">₱ {{ number_format($row['sub_total'], 2) }}</span>
                    @endinteract

                    {{-- Conditional Sub-Table Logic --}}
                    @interact('sub_table', $row)
                        @if($row['is_serialized'])
                            <div class="p-4 bg-blue-50/50 rounded-lg border border-blue-100">
                                <h4 class="text-xs font-bold mb-3 uppercase text-blue-800">Serial Number Details</h4>
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-gray-600 border-b border-blue-200">
                                            <th class="p-2 text-left">Asset Tag</th>
                                            <th class="p-2 text-left">Serial Number</th>
                                            <th class="p-2 text-left">Condition</th>
                                            <th class="p-2 text-left">Ends On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['serialized_items'] as $detail)
                                            <tr class="border-b border-blue-100 last:border-0">
                                                <td class="p-2 font-mono text-blue-700">{{ $detail['code'] }}</td>
                                                <td class="p-2 font-bold">{{ $detail['serial'] }}</td>
                                                <td class="p-2">{{ $detail['condition'] }}</td>
                                                <td class="p-2">{{ \Carbon\Carbon::parse($detail['span_ended'])->format('M d, Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            {{-- sub-table for non-serialized items --}}
                            <div class="p-4 bg-blue-50/50 rounded-lg border border-blue-100">
                                <h4 class="text-xs font-bold mb-3 uppercase text-blue-800">additional Details</h4>
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-gray-600 border-b border-blue-200">
                                            <th class="p-2 text-left">Asset Tag</th>
                                            <th class="p-2 text-left">Ends On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($row['serialized_items'] as $detail)
                                            <tr class="border-b border-blue-100 last:border-0">
                                                <td class="p-2 font-mono text-blue-700">{{ $detail['code'] }}</td>
                                                <td class="p-2">{{ \Carbon\Carbon::parse($detail['span_ended'])->format('M d, Y') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endinteract
                </x-ts-table>
            </x-ts-card>
        </div>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-3 p-3">
                    <x-ts-textarea label="Notes" resize maxlength="250" count placeholder="Add note here..." wire:model="notes"/>
                    <x-ts-stats :number="$grand_total" title="Total Cost" animated>
                                <x-slot:icon>
                                    <x-icon-peso class="w-6 h-6" />
                                </x-slot:icon>
                                <x-slot:right>
                                    <x-ts-button icon="check" flat class="underline" wire:click="reviewAction" :disabled="$step != 2">Reviewed</x-ts-button>
                                    <x-ts-button icon="arrow-path-rounded-square" wire:click="reviseAction" flat class="underline" :disabled="$step != 2">Revise</x-ts-button>
                                 </x-slot:right>

                    </x-ts-stats>
                </div>
                <div class="grid gap-10 p-3">
                    <div class="grid grid-cols-4 gap-3">
                        <div class="col-span-2">
                            <x-ts-select.styled
                            :request="route('api.active.asset-registration-reviewers', ['branch_id' => auth()->user()->branch_id ])"
                            select="label:fullName|value:id|description:position"
                            wire:model="reviewedBy"
                            label="Reviewed By"
                            :placeholders="[
                            'default' => 'Select',
                            'empty'   => 'No reviewers found',
                            ]"  readonly/>
                        </div>

                        <div class="col-span-2">
                            <x-ts-select.styled
                                :request="route('api.active.asset-registration-approvers', ['branch_id' => auth()->user()->branch_id])"
                                wire:model="approvedBy"
                                select="label:fullName|value:id|description:position"
                                label="Approved By"
                                :placeholders="[
                                    'default' => 'Select    ',
                                    'empty'   => 'No aapprovers found',
                                ]" readonly />
                        </div>
                    </div>

                    <div>
                        <x-ts-step wire:model.live="step" circles>
                            <x-ts-step.items step="1"
                                        title="Create Batch"
                                        description="Step 1">
                            </x-ts-tep.items>
                            <x-ts-step.items step="2"
                                        title="For Review"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        title="For Approval"
                                        description="Step 3">
                            </x-ts-step.items>
                            <x-ts-step.items step="4"
                                        completed
                                        title="Completed"
                                        description="Step 4">
                                        <b>Batch Completed!</b>
                            </x-ts-step.items>
                        </x-ts-step>
                    </div>
                </div>
            </div>
        </x-ts-card>
    </div>
</div>
