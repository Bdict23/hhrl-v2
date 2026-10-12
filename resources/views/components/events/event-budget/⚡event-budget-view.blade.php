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
use App\Services\Event\BanquetProcurementService;
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
    $eventStartDate, 
    $eventEndDate,
    $customerName, 
    $servicesList = [], 
    $menuList = [], 
    $serviceTotal = 0.00, 
    $menuTotal = 0.00,  
    $totalGrossOrder = 0.00,
    $reviewedBy,
    $status,
    $notes,
    $approvedBy,
    $allocatedBudget,
    $budgetPercentage,
    $addToGross = false, 
    $eventArrivalTime,
    $reference,
    $eventReference,
    $budgetId,
    $step; 
    public bool $printPreviewModal = false; 

    public function mount($id)
    {
        $this->budgetId = $id;
        $this->fetchData(app(BanquetProcurementService::class));
    }
    public function fetchData(BanquetProcurementService $service)
    {
        try {
            $budget = $service->viewBudget($this->budgetId);
            if($budget)
            {
                $this->reference = $budget->reference_number ?? '';
                $this->eventId = $budget->event_id;
                $this->showEvent($budget->event_id);
                $this->allocatedBudget = $budget->suggested_amount;
                $this->reviewedBy = $budget->noted_by;
                $this->approvedBy = $budget->approved_by;
                $this->notes = $budget->notes;
                $this->status = $budget->status;
                $this->addToGross = $budget->services_included;
                if ($this->addToGross) {
                    $this->serviceTotal = collect($this->servicesList)->sum('total');
                    $this->totalGrossOrder = $this->menuTotal + $this->serviceTotal;
                }else {
                    $this->serviceTotal = 0.00;
                    $this->totalGrossOrder = $this->menuTotal;
                }
                $this->updatedAllocatedBudget();
                $currentStep = $budget->status;
                if($currentStep == 'PREPARING'){
                    $this->step = '1';
                }elseif($currentStep == 'PENDING'){
                    $this->step = '2';
                }elseif($currentStep == 'APPROVED'){
                    $this->step = '3';
                }else{
                    $this->step = '2';
                }

            }
        } catch (\Exception $e) {
            \Log::error("Viewing data Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while retrieving: ' . $e->getMessage())->send();
        }
    }
    public function updatedAddToGross($areOn)
    {
        // dd($areOn);
        if ($areOn && $this->eventId) {
            $this->serviceTotal = collect($this->servicesList)->sum('total');
            $this->totalGrossOrder = $this->menuTotal + $this->serviceTotal;
        }else {
            $this->serviceTotal = 0.00;
            $this->totalGrossOrder = $this->menuTotal;
        }
        $this->updatedAllocatedBudget();
    }
    public function showEvent($id)
    {
        if($id)
            {
                $event = Event::findOrFail($id);
                $this->eventReference = $event->reference;
                $this->addToGross = false;
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
                $this->totalGrossOrder = $this->menuTotal;
            }
            else{
                $this->reset();
            }
    }

    public function updatedAllocatedBudget()
    {
        if(!$this->eventId){
            return;
        }
        if($this->allocatedBudget > 0 && $this->totalGrossOrder > 0){
            $this->budgetPercentage = number_format(((str_replace(',','',$this->allocatedBudget)) / $this->totalGrossOrder) * 100, 2);
        }else{
            return;
        }
    }
    public function updatedbudgetPercentage()
    {
       if(!$this->eventId){
            return;
        }
        if($this->budgetPercentage > 0){
            $this->allocatedBudget = (number_format($this->budgetPercentage ?? 0,2) / 100) * $this->totalGrossOrder;
        } 
    }

      public function edit(){
        return redirect()->route('event-budget-edit', ['id' => $this->budgetId]);
    }

    public function exportPdf()
    {
        $logoPath = public_path('assets/images/1772440510.png');
        $logo = '';
        if (file_exists($logoPath)) {
            $logo = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        $reviewerEmp = \App\Models\Business\Employee::find($this->reviewedBy);
        $approverEmp = \App\Models\Business\Employee::find($this->approvedBy);
        $preparerEmp = auth()->user()->employee;

        return response()->streamDownload(function () use ($logo, $reviewerEmp, $approverEmp, $preparerEmp) {
            echo \Spatie\LaravelPdf\Facades\Pdf::view('pdf.event-budget', [
                'reference' => $this->reference,
                'eventReference' => $this->eventReference,
                'status' => $this->status,
                'customerName' => $this->customerName,
                'addToGross' => $this->addToGross,
                'eventStartDate' => $this->eventStartDate,
                'eventEndDate' => $this->eventEndDate,
                'eventArrivalTime' => $this->eventArrivalTime,
                'eventDepartureTime' => $this->eventDepartureTime,
                'menuTotal' => $this->menuTotal,
                'serviceTotal' => $this->serviceTotal,
                'totalGrossOrder' => $this->totalGrossOrder,
                'allocatedBudget' => $this->allocatedBudget,
                'budgetPercentage' => $this->budgetPercentage,
                'menuList' => $this->menuList,
                'servicesList' => $this->servicesList,
                'notes' => $this->notes,
                'preparer' => $preparerEmp,
                'reviewer' => $reviewerEmp,
                'approver' => $approverEmp,
                'logo' => $logo,
                'company' => auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure',
                'branch' => auth()->user()->branch?->branch_name ?? 'Main Branch',
                'branch_address' => auth()->user()->branch?->branch_address ?? '',
            ])->format('a4')->generatePdfContent();
        }, 'Event-Budget-' . ($this->reference ?: 'export') . '.pdf');
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
        ->question('Update event budget?', 'Are you sure to save this event budget as draft ?')
        ->confirm(
            'Confirm',
            'saveChanges', //pass a functio to call
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
        ->question('Save event budget?', 'Are you sure to save this event budget as final?')
        ->confirm(
            'Confirm',
            'saveChanges', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function saveChanges(BanquetProcurementService $service)
    {
        try {
            // 3. Prepare the data for the Service
            // We structure it to match the $data array expected by the Service
            $data = [
                'id'                        => $this->budgetId,
                'prepared_by'               => auth()->user()->emp_id,
                'status'                    => $this->status,
                'notes'                     => $this->notes,
                'suggested_amount'          => str_replace(',','',$this->allocatedBudget),
                'reviewed_by'               => $this->reviewedBy,
                'approved_by'               => $this->approvedBy,
                'notes'                     => $this->notes,
                'services_included'         => $this->addToGross,
            ];

            // 4. Call the Service
            $po = $service->updateEventBudget($data);

            // 5. Success Feedback
            $this->reset();
            $this->dialog()
            ->success('Success!', "Event budget {$po->reference} created successfully!")
            ->flash() 
            ->send();
            return redirect()->route('event-budget-summary');


        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PO Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
    }
    public function validationRule()
    {
        $this->validate([
            'allocatedBudget'      =>  'required',
            'reviewedBy'           =>  'required|exists:employees,id',
            'approvedBy'           =>  'required|exists:employees,id',
            'notes'                =>  'nullable|string|max:150',
            'eventId'              =>  'required|exists:banquet_events,id'
        ]);
    }
 
};
?>

<div>
    <div class="flex justify-between">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Event', 'link' => route('event-budget-summary'), 'icon' => 'calendar' ],
                              ['label' => 'Event budget Summary', 'link' => route('event-budget-summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Edit Event budget', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
                  <h2>{{ $reference }}</h2>
    </div>

    <div class="grid gap-4 mb-10">

        {{-- FORM TOP --}}
        <x-ts-card>
            <div class="grid grid-cols-3 w-full">
                <div class="grid gap-3 p-2">
                    <x-ts-input label="BANQUET EVENT" wire:model="eventReference" readonly/>

                    <x-ts-input   label="CUSTOMER" wire:model="customerName" readonly/>
                </div>
                <div class="grid gap-3 p-2">
                    <x-ts-input label="START DATE" wire:model="eventStartDate" readonly/>
                    <x-ts-input   label="ARRIVAL TIME" wire:model="eventArrivalTime" readonly/>
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
                                    <div class="font-semibold text-3xl"><span>{{ number_format(collect($this->servicesList)->sum('total'), 2) }}</span></div>
                                    <x-slot:right>
                                        <div class="mr-10 p-2">
                                            <x-ts-toggle label="Add to Gross Amount" wire:model.live="addToGross" disabled/>
                                        </div>
                                    </x-slot:right>
                                </x-ts-stats>
                            </div>
                        </x-slot:footer>
                </x-ts-card>
            </x-ts-tab.items>
            
        </x-ts-tab>

        {{-- FORM 2 --}}
        <x-ts-card>
            <div class="grid grid-cols-2">
                <div class="grid gap-4 p-3">
                    <div class="w-full  rounded-xl bg-slate-100/70 p-5 shadow-sm border border-slate-200/60 font-sans">
                        <!-- Card Header -->
                        <h3 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200/80">
                            Financial Summary
                        </h3>
                        <!-- Breakdown Rows -->
                        <div class="space-y-2 text-sm text-slate-600">
                            <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <div class="flex gap-2 align-middle content-center">
                                    <span>Services &amp; Misc Total:</span>
                                </div>
                                <span class="font-medium text-slate-800">₱ {{ number_format($serviceTotal,2) }}</span>
                            </div>

                            <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <span>Menu Total:</span>
                                <span class="font-medium text-slate-800">₱ {{ number_format($menuTotal,2) }}</span>
                            </div>
                        </div>

                        <!-- Grand Total -->
                        <div class="flex justify-between items-center mt-4 pt-1">
                            <span class="text-base font-extrabold tracking-wider text-slate-900 uppercase">
                            Grand Total:
                            </span>
                            <span class="text-xl font-extrabold text-emerald-600">
                                ₱ {{ number_format($totalGrossOrder, 2) }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <x-ts-step wire:model="step" circles>
                                <x-ts-step.items step="1"
                                            title="Create event budget"
                                            description="Step 1">
                                </x-ts-tep.items>
                                <x-ts-step.items step="2"
                                            title="Approval"
                                            description="Step 4">
                                </x-ts-step.items>
                                <x-ts-step.items step="3"
                                            completed
                                            title="Completed"
                                            description="Step 3">
                                        <b>Budget Created!</b>
                                </x-ts-step.items>
                        </x-ts-step>
                    </div>
                </div>
                <div class="grid gap-2">
                    <div class="grid grid-cols-2 gap-4">
                        <x-ts-currency mutate  symbol label="ALLOCATED BUDGET" wire:model.live="allocatedBudget" readonly/>
                        <x-ts-number label="BUDGET PERCENTAGE" wire:model.live.debounce.750ms="budgetPercentage" readonly/>
                    </div>
                    <x-ts-textarea label="Notes" resize maxlength="150" count placeholder="Add note here..." wire:model="notes" readonly/>
                    <div class="grid gap-2 grid-cols-2">
                        <x-ts-select.styled
                        :request="route('api.liquidate-event.active.reviewers', ['branch_id' => auth()->user()->branch_id ])"
                        select="label:fullName|value:id|description:position"
                        wire:model="reviewedBy"
                        label="REVIEWED BY"
                        :placeholders="[
                        'default' => 'Select',
                        'empty'   => 'No reviewers found',
                        ]"  readonly/>

                        <x-ts-select.styled
                            :request="route('api.liquidate-event.active.approvers', ['branch_id' => auth()->user()->branch_id])"
                            wire:model="approvedBy"
                            select="label:fullName|value:id|description:position"
                            label="APPROVED BY"
                            :placeholders="[
                                'default' => 'Select    ',
                                'empty'   => 'No aapprovers found',
                            ]" readonly />
                    </div>
                    <div class=" justify-end flex gap-3">
                        @if($status == 'PREPARING')
                            <x-ts-button icon="pencil-square" class="underline"  flat  wire:click="edit()">Edit</x-ts-button>
                        @else
                            <x-ts-button icon="pencil-square" class="underline"  flat  disabled>Edit</x-ts-button>
                        @endif
                        <div class="flex items-center gap-2">
                            <x-ts-button icon="document-arrow-down" class="underline cursor-pointer" wire:click="exportPdf">Export to PDF</x-ts-button>
                            <x-ts-button icon="eye" class="underline cursor-pointer" wire:click="$set('printPreviewModal', true)">Preview</x-ts-button>
                        </div>
                    </div>
                </div>
            </div>
        </x-ts-card>
    </div>

    {{-- BANQUET EVENT BUDGET PRINTABLE PREVIEW MODAL --}}
    <x-ts-modal wire="printPreviewModal" size="6xl" center>
        <x-slot:title>
            <div class="flex items-center gap-2">
                <x-ts-icon name="document-arrow-down" class="w-5 h-5 text-primary-600" />
                <span class="font-bold text-slate-800 dark:text-slate-100">
                    Export to PDF - Event Budget Allocation ({{ $reference }})
                </span>
            </div>
        </x-slot:title>

        <div class="space-y-4">
            {{-- Toolbar / Screen Controls (Hidden in Print) --}}
            <div class="flex items-center justify-end p-3 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 print:hidden">
                
                <div class="flex items-center gap-2">
                    <x-ts-button flat sm text="Close" wire:click="$set('printPreviewModal', false)" />
                    <x-ts-button sm icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf" color="primary" />
                </div>
            </div>

            {{-- Document Sheet --}}
            <div id="printable-budget-view-preview" class="bg-white text-slate-900 p-6 sm:p-10 rounded-2xl border border-slate-200 shadow-sm print:border-none print:shadow-none print:p-0 print:m-0 print:w-full">
                {{-- Header with Company Logo --}}
                <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5 mb-6">
                    <div class="flex items-center gap-4">
                        <img src="{{ asset('assets/images/1772440510.png') }}" alt="Company Logo" class="h-20 w-auto object-contain" />
                        <div>
                            <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wide text-slate-900">
                                {{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }}
                            </h1>
                            <p class="text-xs text-slate-600 font-medium">
                                {{ auth()->user()->branch?->branch_name ?? 'Main Branch' }}
                                @if(auth()->user()->branch?->branch_address)
                                    • {{ auth()->user()->branch->branch_address }}
                                @endif
                            </p>
                            <p class="text-xs text-slate-500 font-medium">
                                Banquet Event Procurement / Budget Allocation
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 rounded bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">
                            EVENT BUDGET
                        </span>
                        <div class="mt-2 text-lg font-black font-mono text-slate-900">
                            {{ $reference }}
                        </div>
                        <div class="text-xs text-slate-500">
                            Event Ref: <span class="font-mono font-bold">{{ $eventReference }}</span>
                        </div>
                        <div class="mt-1">
                            <!-- <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded {{ $status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800' : ($status === 'REJECTED' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                {{ $status }}
                            </span> -->
                        </div>
                    </div>
                </div>

                {{-- Event Logistics Meta (NO logged in user info) --}}
                <div class="grid grid-cols-2 gap-4 mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                            Customer & Configuration
                        </h4>
                        <div class="space-y-1">
                            <div><span class="text-slate-500 font-medium">Customer / Client:</span> <strong class="text-slate-900">{{ $customerName ?: 'N/A' }}</strong></div>
                            <div><span class="text-slate-500 font-medium">Include Services in Gross:</span> <span class="text-slate-800 font-semibold">{{ $addToGross ? 'Yes (Included)' : 'No' }}</span></div>
                            <div><span class="text-slate-500 font-medium">Event Reference:</span> <span class="text-slate-800 font-mono">{{ $eventReference }}</span></div>
                        </div>
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                            Event Schedule & Timing
                        </h4>
                        <div class="space-y-1">
                            <div><span class="text-slate-500 font-medium">Event Dates:</span> <span class="text-slate-800">{{ $eventStartDate }} to {{ $eventEndDate }}</span></div>
                            <div><span class="text-slate-500 font-medium">Event Schedule:</span> <span class="text-slate-800">{{ $eventArrivalTime }} to {{ $eventDepartureTime }}</span></div>
                        </div>
                    </div>
                </div>

                {{-- Financial & Budget Allocation Highlight Cards --}}
                <div class="grid grid-cols-4 gap-3 mb-6">
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Menu Subtotal</span>
                        <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $menuTotal, 2) }}</div>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Services Subtotal</span>
                        <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $serviceTotal, 2) }}</div>
                    </div>
                    <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Gross Order Total</span>
                        <div class="text-sm font-mono font-bold text-slate-900 mt-1">₱{{ number_format((float) $totalGrossOrder, 2) }}</div>
                    </div>
                    <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 block">Allocated Budget ({{ $budgetPercentage }}%)</span>
                        <div class="text-base font-mono font-black text-emerald-700 mt-1">₱{{ number_format((float) str_replace(',', '', $allocatedBudget), 2) }}</div>
                    </div>
                </div>

                {{-- 1. Menu Items Table --}}
                @if(!empty($menuList))
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                            <span>1. Menu Items Breakdown</span>
                            <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $menuTotal, 2) }}</span>
                        </h3>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                        <th class="py-2 px-3 w-10 text-center">#</th>
                                        <th class="py-2 px-3">Menu / Recipe</th>
                                        <th class="py-2 px-3">Category</th>
                                        <th class="py-2 px-3 text-right">Rate</th>
                                        <th class="py-2 px-3 text-right">Qty</th>
                                        <th class="py-2 px-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach($menuList as $mIdx => $mItem)
                                        <tr>
                                            <td class="py-1.5 px-3 text-center text-slate-400">{{ $mIdx + 1 }}</td>
                                            <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $mItem['menu_name'] ?? 'N/A' }}</td>
                                            <td class="py-1.5 px-3 text-slate-600">{{ $mItem['category'] ?? 'N/A' }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($mItem['amount'] ?? 0), 2) }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $mItem['qty'] ?? 0 }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) ($mItem['total'] ?? 0), 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- 2. Services Breakdown --}}
                @if(!empty($servicesList))
                    <div class="mb-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                            <span>2. Services & Miscellaneous Breakdown</span>
                            <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) collect($servicesList)->sum('total'), 2) }}</span>
                        </h3>
                        <div class="overflow-hidden rounded-lg border border-slate-200">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                        <th class="py-2 px-3 w-10 text-center">#</th>
                                        <th class="py-2 px-3">Service Name</th>
                                        <th class="py-2 px-3 text-right">Rate / Income</th>
                                        <th class="py-2 px-3 text-right">Qty</th>
                                        <th class="py-2 px-3 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @foreach($servicesList as $sIdx => $sItem)
                                        <tr>
                                            <td class="py-1.5 px-3 text-center text-slate-400">{{ $sIdx + 1 }}</td>
                                            <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $sItem['title'] ?? 'N/A' }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($sItem['income'] ?? 0), 2) }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $sItem['qty'] ?? 0 }}</td>
                                            <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) ($sItem['total'] ?? 0), 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Notes --}}
                @if($notes)
                    <div class="mb-6 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Notes / Justification:</span>
                        <p class="mt-1 text-slate-800 whitespace-pre-line">{{ $notes }}</p>
                    </div>
                @endif

                {{-- Signatures Section --}}
                @php
                    $reviewerEmp = \App\Models\Business\Employee::find($reviewedBy);
                    $approverEmp = \App\Models\Business\Employee::find($approvedBy);
                @endphp
                <div class="grid grid-cols-3 gap-6 pt-6 mt-6 border-t border-slate-200 text-xs">
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ auth()->user()->employee?->full_name ?? auth()->user()->name }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Prepared By</div>
                        <div class="text-[10px] text-slate-400">{{ auth()->user()->employee?->position_name ?? 'Procurement Officer' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ $reviewerEmp?->full_name ?? '_________________________' }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Reviewed / Noted By</div>
                        <div class="text-[10px] text-slate-400">{{ $reviewerEmp?->position_name ?? 'Financial Reviewer' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="h-10"></div>
                        <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                            {{ $approverEmp?->full_name ?? '_________________________' }}
                        </div>
                        <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Approved By</div>
                        <div class="text-[10px] text-slate-400">{{ $approverEmp?->position_name ?? 'General Manager' }}</div>
                    </div>
                </div>

                {{-- Document Tracking Footer --}}
                <div class="mt-8 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
                    <div>{{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }} • Banquet Event Budget</div>
                    <div>Generated on {{ now()->format('M d, Y h:i:s A') }}</div>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-ts-button flat text="Close" wire:click="$set('printPreviewModal', false)" />
                <x-ts-button icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf" color="primary" />
            </div>
        </x-slot:footer>
    </x-ts-modal>

    <style>
        @media print {
            body, html {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            body * {
                visibility: hidden !important;
            }
            #printable-budget-view-preview, #printable-budget-view-preview * {
                visibility: visible !important;
            }
            #printable-budget-view-preview {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 1.5cm !important;
                box-shadow: none !important;
                border: none !important;
                background: #ffffff !important;
            }
            .print\:hidden {
                display: none !important;
            }
        }
    </style>

    <x-ts-back-to-top />
</div>
