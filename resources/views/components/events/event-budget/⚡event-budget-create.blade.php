<?php

use Livewire\Component;
use TallStackUi\Traits\Interactions;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
// use App\Models\Inventory\Receiving;
use App\Services\Transaction\AcknowledgementReceiptService; 
use App\Services\Event\EventLiquidationService;
use App\Models\BanquetEvent\Event;
use Carbon\Carbon;


new class extends Component
{
    use WithPagination;
    use Interactions;

    // NEW VARIABLES
    public 
    $eventId,
    $eventDepartureTime,
    $eventStartDate, // added
    $eventEndDate,
    $customerName, //added
    $servicesList = [], //added
    $menuList = [], //added
    $serviceTotal = 0.00,
    $pcvTotalMutate = 0.00,
    $menuTotal = 0.00,
    $receiveOrderTotal = 0.00,
    $pcvTotalReturn = 0.00,
    $withdrawalTotal = 0.00,
    $checkData,
    $reviewedBy,
    $status,
    $notes,
    $approvedBy,
    $eventArrivalTime; // added

    public function updatedEventId($id)
    {
        if($id)
            {
                $event = Event::findOrFail($id);
                $this->customerName = $event->customer->full_name;
                $this->eventStartDate = Carbon::parse($event->start_date)->format('M. d, Y');
                $this->eventEndDate = Carbon::parse($event->end_date)->format('M. d, Y');
                $this->eventArrivalTime = Carbon::parse($event->arrival_time)->format('h:i A');
                $this->eventDepartureTime = Carbon::parse($event->departure_time)->format('h:i A');

                foreach ($event->services as $eventService) {
                    $income = $eventService->service->service_type == 'EXTERNAL' ? $eventService->price->amount ?? 0 : $eventService->cost->amount ?? 0;
                    $this->servicesList [] = 
                    [
                        'title' => $eventService->service->service_name,
                        'qty' => $eventService->qty,
                        'income' => $income,
                        'total' => $eventService->qty * $income,
                    ];
                }
                foreach ($event->menus as $menu) {
                    $this->menuList [] = [
                        'image' => $menu->recipe->menu_image,
                        'menu_name' => $menu->recipe->menu_name,
                        'category' => $menu->recipe->category->category_name,
                        'qty' => $menu->qty,
                        'amount' => $menu->rate->amount,
                        'total' => $menu->qty * $menu->rate->amount,
                    ];
                }
                $this->menuTotal = collect($this->menuList)->sum('total');
                $this->serviceTotal = collect($this->servicesList)->sum('total');
            }
            else{
                $this->reset();
            }
    }


    public function with(): array
    {
        return [
            'servicesHeader' => [
                ['index' => 'title', 'label' => 'title'],
                ['index' => 'qty', 'label' => 'qty' ],
                ['index' => 'income', 'label' => 'income' ],
                ['index' => 'total', 'label' => 'total' ],
            ],
            'menuHeader' => [
                ['index' => 'image', 'label' => 'image'],
                ['index' => 'menu_name', 'label' => 'menu'],
                ['index' => 'category', 'label' => 'category' ],
                ['index' => 'qty', 'label' => 'qty' ],
                ['index' => 'amount', 'label' => 'amount'],
                ['index' => 'total', 'label' => 'total' ],
            ],
        ];
    }

    public function saveAsDraftAction(): void
    {

        // 1. Validate the UI State
        $this->validationRule();

        $this->status = "DRAFT";
        // 2. show confirmation dialog
        $this->dialog()
        ->question('Save liquidation?', 'Are you sure to save this liquidation as draft ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function saveAsFinalAction(): void
    {
        // 1. Validate the UI State
        $this->validationRule();
        $this->status = "FINAL";
        // 2. show confirmation dialog
         $this->dialog()
        ->question('Save liquidation?', 'Are you sure to save this liquidation as final?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function store(EventLiquidationService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id'   => Auth::user()->branch_id,
                'company_id'    => Auth::user()->branch->company_id,
                'event_id' => $this->eventId,
                'prepared_by'  => auth()->user()->emp_id,
                'status'  => $this->status,
                'notes'       => $this->notes,
                'total_incurred' => $this->pcvTotal,
                'reviewed_by' => $this->reviewedBy,
                'approved_by' => $this->approvedBy,
            ];

            // 4. Call the Service
            $po = $service->createLiquidation($data);

            // 5. Success Feedback
            $this->reset();
            $this->dialog()
            ->success('Success!', "Event liquidation {$po->reference} created successfully!")
            ->flash() 
            ->send();
            return redirect()->route('event-liquidation-summary');


        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PO Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
    public function validationRule()
    {
        $this->validate([
            'pcvTotal'      => 'numeric|min:1',
            'reviewedBy'    =>'required|exists:employees,id',
            'approvedBy'    =>'required|exists:employees,id',
            'notes'         =>'nullable|string|max:150',
            'eventId'      => 'required|exists:banquet_events,id'
        ]);
    }
 
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Event', 'link' => route('event-liquidation-summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Event Liquidation Summary', 'link' => route('event-liquidation-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create Event Liquidation', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    </div>

    <div class="grid gap-4 mb-10">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-3 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-select.styled
                        :request="route('api.event-procurement.active.event', ['branch_id' => Auth::user()->branch_id])"
                        label="BANQUET EVENT"
                        wire:model.live='eventId'
                        select="label:reference|value:id|description:event_name"
                        :placeholders="[
                            'default' => 'Select event',
                            'search'  => 'Search event',
                            'empty'   => 'No active event found',
                        ]"
                    />

                    <x-ts-input   label="CUSTOMER" wire:model="customerName" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="START DATE" wire:model="eventStartDate" readonly/>
                    <x-ts-currency mutate symbol currency label="ARRIVAL TIME" wire:model="eventArrivalTime" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="END DATE" readonly wire:model="eventEndDate"/>
                    <x-ts-input label="DEPARTURE TIME" readonly wire:model="eventDepartureTime"/>
                </div>
            </div>
        </x-ts-card>

        {{-- TABLE --}}
        <x-ts-tab selected="MENU">
            <x-ts-tab.items tab="MENU">
                <x-ts-card>
                    <x-ts-table :headers="$menuHeader" :rows="$menuList" striped expandable loading >
                        @interact('column_image', $row)
                            <x-ts-avatar image="{{ asset('storage/'.$row['image']) }}" md text="AIR" square />
                        @endinteract
                        @interact('column_amount', $row)
                           ₱ {{ number_format($row['amount'],2) }}
                        @endinteract
                        @interact('column_total', $row)
                            ₱ {{  number_format($row['total'], 2) }}
                        @endinteract
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($menuTotal, 2)}}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
                
            </x-ts-tab.items>
            <x-ts-tab.items tab="SERVICES AND MISCELLANEOUS">
                <x-ts-card>
                    <x-ts-table :headers="$servicesHeader" :rows="$servicesList" striped expandable loading highlight>
                         @interact('column_income', $row)
                           ₱ {{ number_format($row['income'],2) }}
                        @endinteract
                        @interact('column_total', $row)
                           ₱ {{ number_format($row['total'],2) }}
                        @endinteract
                        {{-- @interact('sub_table', $row)
                            <x-ts-table :headers="[
                                ['index' => 'id', 'label' => 'id'],
                                ['index' => 'item_code', 'label' => 'Code'],
                                ['index' => 'item_description', 'label' => 'Description'],
                                ['index' => 'brand', 'label' => 'Brand'],
                                ['index' => 'category', 'label' => 'Category'],
                                ['index' => 'classification', 'label' => 'Classification'],
                                ['index' => 'subClass', 'label' => 'Sub-Classification'],
                            ]"
                            :rows="[[
                                'id'                => $row['id'],
                                'item_code'         => $row['item_code'],
                                'item_description'  => $row['item_description'],
                                'brand'             => $row['brand'],
                                'category'          => $row['category'],
                                'classification'    => $row['classification'],
                                'subClass'          => $row['subClass'],
                            ]]" />
                        @endinteract --}}
                        
                    </x-ts-table>
                    <x-slot:footer>
                            <div class="flex justify-end mt-3">
                                <x-ts-stats
                                    title="Total amount">
                                    <x-slot:icon>
                                        <x-icon-peso class="w-6 h-6" />
                                    </x-slot:icon>
                                    <div class="font-semibold text-3xl"><span>{{ number_format($serviceTotal, 2) }}</span></div>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
            </x-ts-tab.items>
            
        </x-ts-tab>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-2 p-3 col-span-1">
                    <div class="grid grid-cols-2 gap-4 w-full">
                        <x-ts-currency symbol label="ALLOCATED BUDGET" />
                        <x-ts-number label="BUDGET PERCENTAGE" />
                    </div>
                    <div class="w-full  rounded-xl bg-slate-100/70 p-5 shadow-sm border border-slate-200/60 font-sans col-span-2">
                        <!-- Card Header -->
                        <h3 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200/80">
                            Financial Summary
                        </h3>
                        <!-- Breakdown Rows -->
                        <div class="space-y-2 text-sm text-slate-600">
                            <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <span>Services &amp; Misc Total:</span>
                                <span class="font-medium text-slate-800">₱ 0.00</span>
                            </div>

                            <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <span>Menu Total:</span>
                                <span class="font-medium text-slate-800">₱ 0.00</span>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="flex justify-between items-center mt-4 pt-1">
                            <span class="text-base font-extrabold tracking-wider text-slate-900 uppercase">
                            Grand Total:
                            </span>
                            <span class="text-xl font-extrabold text-emerald-600">
                                ₱ 0.00
                            </span>
                        </div>
                    </div>
                </div>
                <div class="grid gap-2 p-3">
                    <div class="h-full mt-5 col-span-1">
                        <x-ts-textarea label="Notes" resize maxlength="300" count placeholder="Add note here..." wire:model="notes"/>
                    </div>
                    <div class="col-span-2 grid gap-2 grid-cols-2">
                        <x-ts-select.styled
                        :request="route('api.liquidate-event.active.reviewers', ['branch_id' => auth()->user()->branch_id ])"
                        select="label:fullName|value:id|description:position"
                        wire:model="reviewedBy"
                        label="REVIEWED BY"
                        :placeholders="[
                        'default' => 'Select',
                        'empty'   => 'No reviewers found',
                        ]" ... required/>

                        <x-ts-select.styled
                            :request="route('api.liquidate-event.active.approvers', ['branch_id' => auth()->user()->branch_id])"
                            wire:model="approvedBy"
                            select="label:fullName|value:id|description:position"
                            label="APPROVED BY"
                            :placeholders="[
                                'default' => 'Select    ',
                                'empty'   => 'No aapprovers found',
                            ]" required />
                    </div>
                        <div class="mt-3">
                        <x-ts-step selected="1" circles>
                            <x-ts-step.items step="1"
                                        title="Create Liquidation"
                                        description="Step 1">
                            </x-ts-tep.items>
                            <x-ts-step.items step="2"
                                        title="Review"
                                        description="Step 2">
                            </x-ts-step.items>
                            <x-ts-step.items step="3"
                                        completed
                                        title="Settlement"
                                        description="Step 3">
                            </x-ts-step.items>
                            <x-ts-step.items step="4"
                                        completed
                                        title="Approved"
                                        description="Step 4">
                            </x-ts-step.items>
                            <x-ts-step.items step="5"
                                        completed
                                        title="Completed"
                                        description="Step 6">
                                        <b>Event Liquidated!</b>
                            </x-ts-step.items>
                        </x-ts-step>
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="flex justify-end">
                    <x-ts-dropdown>
                        <x-slot:action>
                            <x-ts-button x-on:click="show = !show" md icon="chevron-down" position="right">SAVE AS</x-ts-button>
                        </x-slot:action>
                        <x-ts-dropdown.items outline icon="archive-box-arrow-down" text="DRAFT"
                            wire:click="saveAsDraftAction()" />
                        <x-ts-dropdown.items icon="clipboard-document-check" text="FINAL" separator
                            wire:click="saveAsFinalAction()" />
                    </x-ts-dropdown>
                </div>
            </x-slot:footer>
        </x-ts-card>
    </div>

    <x-ts-back-to-top />
</div>
