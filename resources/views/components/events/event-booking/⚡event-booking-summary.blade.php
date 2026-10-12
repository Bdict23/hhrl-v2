<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BanquetEvent\Event;
use TallStackUi\Traits\Interactions;
use App\Services\Event\BanquetEventService;




new class extends Component
{
    use WithPagination;
    use Interactions;


    public ?int $quantity = 10;
    public ?string $search = null;
    public ?string $status = null;
    public ?array $dates = null;
    public $activeEvents = [];
    public bool $viewSlide = false;
    public $confirmedEventId = null;
    public bool $printPreviewModal = false;
    public ?int $previewEventId = null;

    public function openPrintPreview($eventId): void
    {
        $this->previewEventId = $eventId;
        $this->printPreviewModal = true;
    }

    public function exportPdf(?int $eventId = null)
    {
        $targetId = $eventId ?: $this->previewEventId ?: $this->selectedEventId;
        if (! $targetId) {
            return;
        }

        $event = Event::with([
            'customer',
            'menus.recipe.category',
            'menus.rate',
            'menus.price',
            'services.service',
            'services.price',
            'venues.venue',
            'venues.rate',
            'preparedBy.position',
            'reviewedBy.position',
            'approvedBy.position',
        ])->findOrFail($targetId);

        $logoPath = public_path('assets/images/1772440510.png');
        $logo = '';
        if (file_exists($logoPath)) {
            $logo = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        return response()->streamDownload(function () use ($event, $logo) {
            echo \Spatie\LaravelPdf\Facades\Pdf::view('pdf.event-booking', [
                'event' => $event,
                'logo' => $logo,
                'company' => auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure',
                'branch' => auth()->user()->branch?->branch_name ?? 'Main Branch',
                'branch_address' => auth()->user()->branch?->branch_address ?? '',
            ])->format('a4')->generatePdfContent();
        }, 'BEO-' . ($event->reference ?: $event->id) . '.pdf');
    }

    public $grand_total = 0,
            $eventName,
            $reference,
            $customer,
            $startDate,
            $endDate,
            $arrivalTime,
            $departureTime,
            $address,
            $guestCount,
            $reviewedBy,
            $approvedBy,
            $notes,
            $step = 1,
            $selectedEventId,
            $menuTotal = '0.00',
            $serviceTotal = '0.00',
            $venueTotal = '0.00';

    public array $sort = [
            'column' => 'start_date',
            'direction' => 'desc',
        ];

    public function mount(){
        $this->activeEvents = Event::where('branch_id', auth()->user()->branch_id)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('start_date', 'asc')
            ->whereNotIn('status',['CANCELLED','UNATTENDED'])
            ->get();
    }

    public function showDetails($eventId)
    {
        $event = Event::findOrFail($eventId);
        if($event){
            $this->viewSlide = true;
            $this->eventName = $event->event_name;
            $this->reference = $event->reference;
            $this->customer = $event->customer->full_name;
            $this->startDate = $event->start_date;
            $this->endDate = $event->end_date;
            $this->notes = $event->notes;
            $this->reviewedBy = $event->reviewedBy->full_name;
            $this->approvedBy = $event->approvedBy->full_name;
            $this->status = $event->status;
            $this->grand_total = number_format($event->total_amount,2);
            $this->arrivalTime = date('g:i A', strtotime($event->arrival_time));
            $this->departureTime = date('g:i A', strtotime($event->departure_time));;
            $this->address = $event->event_address;
            $this->guestCount = $event->guest_count;
            $this->selectedEventId = $event->id;
            $this->menuTotal =  number_format($event->menus->sum('total_amount'), 2);
            $this->serviceTotal =  number_format($event->services->sum('total_amount'), 2);
            $this->venueTotal =  number_format($event->venues->sum('total_amount'), 2);
            $this->resetPage('foodPage');
            $this->resetPage('servicePage');
            $this->resetPage('venuePage');
            if($event->budgetAllocation?->status == 'APPROVED'){
                $this->step = 4;
            }elseif($event->budgetAllocation?->status == 'PREPARING'){
                $this->step = 3;
            }else{
                $this->step = match($this->status) {
                'PENDING' => 1,
                'CONFIRMED' => 2,
                'CLOSED' => 5,
                default => 1,
            };
            }
            
        }
    }

    public function confirmEventAction($eventId, $reference)
    {
        $this->confirmedEventId = $eventId;
        $this->dialog()
        ->question('Confirm Event?', "Are you sure to confirm '$reference' event ?")
        ->confirm(
            'Confirm',
            'confirmEvent', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }
    public function confirmEvent(BanquetEventService $service)
    {
        try {
        if ($this->confirmedEventId) {
            $event = $service->confirmEvent($this->confirmedEventId);
            $this->toast()->success('Success', "Event '{$event->reference}' has been confirmed successfully.")->send();
            $this->confirmedEventId = null; // Reset the confirmed event ID after confirmation
        }
         } catch (\Exception $e) {
             \Log::error("Event Update Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while confirming the event: ' . $e->getMessage())->send();
         }
    }

    public function rollbackEventAction($eventId, $reference)
    {
        $this->confirmedEventId = $eventId;
        $this->dialog()
        ->question('Rollback Event?', "Are you sure to rollback '$reference' event ?")
        ->confirm(
            'Rollback',
            'rollbackEvent', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function rollbackEvent(BanquetEventService $service)
    {
    try {
        if ($this->confirmedEventId) {
            $event = $service->rollbackEvent($this->confirmedEventId);
            $this->toast()->success('Success', "Event '{$event->reference}' has been rolled back successfully.")->send();
            $this->confirmedEventId = null; // Reset the confirmed event ID after confirmation
        }
         } catch (\Exception $e) {
             \Log::error("Event Rollback Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while rolling back the event: ' . $e->getMessage())->send();
         }
    }

    
    public function with(): array
    {

        return [
            'headers' => [
                ['index' => 'status', 'label' => 'Status'],
                ['index' => 'reference', 'label' => 'reference', 'sortable' => false],
                ['index' => 'event_name', 'label' => 'Department', 'sortable' => false],
                ['index' => 'total_amount', 'label' => 'Event Amount' , 'sortable' => false],
                ['index' => 'start_date', 'label' => 'Start Date' , 'sortable' => false],
                ['index' => 'end_date', 'label' => 'End Date' , 'sortable' => false],
                ['index' => 'arrival_time', 'label' => 'Arrival' , 'sortable' => false],
                ['index' => 'created_by', 'label' => 'Prepared By',  'sortable' => false],
                ['index' => 'action', 'label' => 'action'],

            ],
            'foodHeaders' => [
                ['index' => 'menu_image', 'label' => 'image'],
                ['index' => 'menu_name', 'label' => 'recipe name'],
                ['index' => 'category_id', 'label' => 'category'],
                ['index' => 'price_id', 'label' => 'rate', 'sortable' => false],
                ['index' => 'qty', 'label' => 'quantity', 'sortable' => false],
                ['index' => 'total_amount', 'label' => 'total' , 'sortable' => false],
            ],
             'serviceHeaders' => [
                ['index' => 'service_id', 'label' => 'service'],
                ['index' => 'price_id', 'label' => 'rate', 'sortable' => false],
                ['index' => 'qty', 'label' => 'quantity', 'sortable' => false],
                ['index' => 'total_amount', 'label' => 'total' , 'sortable' => false],
            ],
             'venueHeaders' => [
                ['index' => 'venue_id', 'label' => 'venue'],
                ['index' => 'price_id', 'label' => 'rate', 'sortable' => false],
                ['index' => 'qty', 'label' => 'quantity', 'sortable' => false],
                ['index' => 'total_amount', 'label' => 'total' , 'sortable' => false],
            ],
            'eventFood' => $this->selectedEventId
                ? Event::findOrFail($this->selectedEventId)->menus()->paginate(5, ['*'], 'foodPage')
                : [],
            'eventService' => $this->selectedEventId
                ? Event::findOrFail($this->selectedEventId)->services()->paginate(5, ['*'], 'servicePage')
                : [],
            'eventVenue' => $this->selectedEventId
                ? Event::findOrFail($this->selectedEventId)->venues()->paginate(5, ['*'], 'venuePage')
                : [],
            'rows' => Event::query()
                ->when($this->search, function (Builder $query) {
                    return $query->where('reference', 'like', "%{$this->search}%")->where('branch_id', auth()->user()->branch_id);
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('start_date', $this->dates)->where('branch_id', auth()->user()->branch_id);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status)->where('branch_id', auth()->user()->branch_id);
                })
                ->where('branch_id', auth()->user()->branch_id)
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString(),
            'previewEvent' => $this->previewEventId
                ? Event::with([
                    'customer',
                    'menus.recipe.category',
                    'menus.rate',
                    'services.service',
                    'services.price',
                    'venues.venue',
                    'venues.rate',
                    'preparedBy.position',
                    'reviewedBy.position',
                    'approvedBy.position',
                ])->find($this->previewEventId)
                : null,
        ];
    }
};
?>

<div>
    <div class="lg:flex lg:justify-between grid mb-4">
        <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
            ['label' => 'Events','link' => route('event-booking-summary'), 'icon' => 'calendar' ],
            ['label' => 'Event Booking Summary', 'icon' => 'list-bullet'],
            ]"/>      
    </div>

    <x-ts-tab selected="Upcoming Events">
        <x-ts-tab.items tab="All Events">
            <x-slot:right>
                <x-icon-calendars class="w-5 h-5" />
            </x-slot:right>
            <div class="lg:flex gap-3 grid grid-cols-3 justify-end">
                    <x-ts-select.native wire:model.live="status"
                            placeholder="All Statuses"
                            :options="[
                            ['name' => 'All', 'id' => null],
                            ['name' => 'PENDING', 'id' => 'PENDING'],
                            ['name' => 'CONFIRMED', 'id' => 'CONFIRMED'],
                            ['name' => 'CLOSED', 'id' => 'CLOSED'],
                            ['name' => 'UNATTENDED', 'id' => 'UNATTENDED'],
                            ['name' => 'CANCELLED', 'id' => 'CANCELLED'],
                    ]" select="label:name|value:id" />
                    <x-ts-date wire:model.live="dates" range placeholder="Date range" />
                </div>
            <div>
                <x-ts-table :$headers :$rows :$sort paginate persistent filter loading striped >
                    @interact('column_status', $row)
                        <div class="flex items-center gap-2">
                            @if($row->status == 'PENDING')
                                <x-ts-badge text="DRAFT" color="secondary" />
                            @elseif($row->status == 'CONFIRMED')
                                <x-ts-badge :text="$row->status" color="cyan" />
                            @elseif($row->status == 'UNATTENDED')
                                <x-ts-badge :text="$row->status" color="rose" />
                            @elseif($row->status == 'CANCELLED')
                                <x-ts-badge :text="$row->status" color="rose" />
                            @elseif($row->status == 'CLOSED')
                                <x-ts-badge :text="$row->status" color="green" />
                            @endif
                        </div>
                    @endinteract
                    @interact('column_total_amount', $row)
                        ₱ {{  number_format(($row->total_amount) ?? 0 , 2) }}
                    @endinteract
                    @interact('column_start_date', $row)
                        {{ \Illuminate\Support\Carbon::parse($row->start_date)->format('M. d, Y') }}
                    @endinteract
                    @interact('column_end_date', $row)
                        {{ \Illuminate\Support\Carbon::parse($row->end_date)->format('M. d, Y') }}
                    @endinteract
                    @interact('column_arrival_time', $row)
                        {{ date('g:i A', strtotime($row->arrival_time)) }}
                    @endinteract
                    @interact('column_created_by', $row)
                        <div class="flex items-center gap-2">
                            <x-ts-badge :text="$row->preparedBy?->full_name ?? 'Unknown'" outline />
                        </div>
                    @endinteract
                    
                    
                    @interact('column_action', $row)
                    <x-ts-dropdown icon="ellipsis-vertical" static lg>
                        @if ($row->status == 'PENDING')
                            <a href="{{ route('event-booking-edit', ['id' => $row->id]) }}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                            <x-ts-dropdown.items text="Confirm" icon="check-badge" wire:click="confirmEventAction({{ $row->id }}, '{{ $row->reference }}')" />
                        @endif
                        @if( $row->status == 'CONFIRMED')
                            <x-ts-dropdown.items text="Rollback" icon="arrow-path" wire:click="rollbackEventAction({{ $row->id }}, '{{ $row->reference }}')" />
                        @endif
                        <a href="{{ route('event-booking-view', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <x-ts-dropdown.items text="Export to PDF" separator icon="document-arrow-down" wire:click="exportPdf({{ $row->id }})" />
                        <x-ts-dropdown.items text="Preview" icon="eye" wire:click="openPrintPreview({{ $row->id }})" />
                    </x-ts-dropdown>
                @endinteract
                </x-ts-table>
            </div>
        </x-ts-tab.items>
        <x-ts-tab.items tab="Upcoming Events">
            <x-slot:right>
                <x-icon-calendar-clock class="w-5 h-5" />
            </x-slot:right>
            <div class="grid grid-cols-4 gap-5">
                @foreach ($activeEvents as $event)
                    <x-ts-card color="primary" light icon="calendar" round="2xl" loading="showDetails({{$event->id}})">
                        <x-slot:header>
                                {{$event->event_name}} <x-icon-calendar class="w-5 h-5" />
                        </x-slot:header>
                        <p class="card-text flex">  <x-icon-map-pinned class="w-5 h-5" />{{ $event->event_address }}</p>
                        <div>
                            <div class="flex text-xs">
                                <x-icon-calendar-check class="w-5 h-5" />
                                <strong> {{ \Carbon\Carbon::parse($event->start_date)->format('M. d, Y') }} </strong>
                                &nbsp;({{ \Carbon\Carbon::parse($event->arrival_time)->format('g A') }})
                                until &nbsp;
                                <strong>{{ \Carbon\Carbon::parse($event->end_date)->format('M. d, Y') }}</strong>
                                &nbsp;({{ \Carbon\Carbon::parse($event->departure_time)->format('g:i A') }})
                            </div><br>
                            <div class="flex">
                                <x-icon-customers class="w-5 h-5" />{{ $event->guest_count }} Guests
                            </div><br>
                            
                            <div class="flex gap-2">
                                @php
                                    $startDate = \Carbon\Carbon::parse($event->start_date)->startOfDay();
                                    $now = \Carbon\Carbon::now()->startOfDay();
                                    $daysLeft = $now->diffInDays($startDate);
                                @endphp
                                @if($startDate->isPast())
                                 <x-ts-badge text="Current Date" light color="cyan"/>
                                @elseif($daysLeft >= 8)
                                 <x-ts-badge text="{{ $daysLeft }} Days left." light color="green"/>
                                @elseif($daysLeft >= 5)
                                 <x-ts-badge text="{{ $daysLeft }} Days left" light color="fuchsia"/>
                                 @elseif($daysLeft == 1)
                                 <x-ts-badge text="{{ $daysLeft }} Day left" light color="red"/>
                                @else
                                 <x-ts-badge text="{{ $daysLeft }} Days left" light color="pink"/>
                                @endif
                                @if($event->status == 'PENDING')
                                     <x-ts-badge text="{{$event->status}}" outline color="zinc" />
                                @else
                                     <x-ts-badge text="{{$event->status}}" outline />
                                @endif
                            </div>
                        </div>
                        <x-slot:footer>
                            <div class="flex justify-end">
                                <x-ts-button flat sm wire:click="showDetails({{$event->id}})" class="underline">MORE DETAILS</x-ts-button>
                            </div>
                        </x-slot:footer>
                    </x-ts-card>
                @endforeach
            </div>
        </x-ts-tab.items>
        <x-ts-slide id="title-slide" wire="viewSlide" size="7xl">
            <x-slot:title>
                <div class="flex items-center justify-between w-full pr-8">
                    <span class="text-4xl">{{ $eventName }}</span>
                    @if($selectedEventId)
                        <div class="flex items-center gap-2">
                            <x-ts-button icon="document-arrow-down" sm flat wire:click="exportPdf({{ $selectedEventId }})" text="Export to PDF" class="underline cursor-pointer" />
                            <x-ts-button icon="eye" sm flat wire:click="openPrintPreview({{ $selectedEventId }})" text="Preview" class="underline cursor-pointer" />
                        </div>
                    @endif
                </div>
            </x-slot:title>
             <x-ts-card>
                <div class="grid grid-cols-4 w-full h-full mb-4">
                    <div class="grid gap-3 p-2">
                        <x-ts-input wire:model="reference" label="REFERENCE" readonly/>
                        <x-ts-input wire:model="customer" label="CUSTOMER" readonly/>
                    </div>
                    <div class="grid gap-3 p-2">
                        <x-ts-input wire:model="startDate" label="START DATE" readonly/>
                        <x-ts-input wire:model="arrivalTime" label="ARRIVAL TIME" readonly/>
                    </div>
                    <div class="grid gap-3 p-2">
                        <x-ts-input wire:model="endDate" label="END DATE" readonly/>
                        <x-ts-input wire:model="departureTime" label="CUSTOMER" readonly/>
                        
                    </div>
                    <div class="grid gap-3 p-2">
                        <x-ts-input wire:model="address" label="ADDRESS" readonly/>
                        <x-ts-input wire:model="guestCount" label="GUEST COUNT / PAX. COUNT" readonly/>
                    </div>
                </div>
            </x-ts-card>

            <div class="mb-3">
                <x-ts-tab selected="Menu">
                    <x-ts-tab.items tab="Menu">
                        <x-ts-table :headers="$foodHeaders" :rows="$eventFood" :$sort loading striped expandable paginate>
                            @interact('column_menu_image', $row)
                                <x-ts-avatar image="{{ asset('storage/'.$row->recipe?->menu_image) }}" md text="AIR" square />
                            @endinteract
                            @interact('column_menu_name', $row)
                                {{$row->recipe?->menu_name}}
                            @endinteract
                             @interact('column_category_id', $row)
                                {{$row->recipe?->category->category_name}}
                            @endinteract
                            @interact('column_price_id', $row)
                               ₱ {{ number_format($row->price?->amount,2) }}
                            @endinteract
                            @interact('column_total_amount', $row)
                               ₱ {{ number_format($row->total_amount,2) }}
                            @endinteract
                            @interact('sub_table', $row)
                                    <x-ts-table :headers="[
                                        ['index' => 'notes', 'label' => 'notes'],
                                    ]"
                                    :rows="[[
                                        'notes'          => $row->note,
                                    ]]">
                                    @interact('column_notes', $row)
                                        <x-ts-textarea value="{{$row['notes']}}" resize readonly/>
                                    @endinteract
                                    </x-ts-table>
                            @endinteract
                        </x-ts-table>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Services and Miscellaneous">
                        <x-ts-table :headers="$serviceHeaders" :rows="$eventService" :$sort loading striped paginate>
                            @interact('column_service_id', $row)
                                {{$row->service?->service_name}}
                            @endinteract
                            @interact('column_price_id', $row)
                               ₱ {{ number_format($row->price?->amount, 2)}}
                            @endinteract
                        </x-ts-table>
                    </x-ts-tab.items>
                    <x-ts-tab.items tab="Venues">
                        <x-ts-table :headers="$venueHeaders" :rows="$eventVenue" :$sort loading striped paginate>
                            @interact('column_venue_id', $row)
                                {{$row->venue?->venue_name}}
                            @endinteract
                            @interact('column_price_id', $row)
                                ₱ {{number_format($row->rate->amount ?? 0, 2 )}}
                            @endinteract
                            @interact('column_total_amount', $row)
                                ₱ {{number_format($row->total_amount ?? 0, 2)}}
                            @endinteract
                        </x-ts-table>
                    </x-ts-tab.items>
                </x-ts-tab>
            </div>


            <x-ts-card>
                <div class="grid grid-cols-5">
                    <div class="grid gap-2 p-3 col-span-2">                        
                        <div class="w-full max-w-sm rounded-xl bg-slate-100/70 p-5 shadow-sm border border-slate-200/60 font-sans">
                            <!-- Card Header -->
                            <h3 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200/80">
                                Financial Summary
                            </h3>

                            <!-- Breakdown Rows -->
                            <div class="space-y-2 text-sm text-slate-600">
                                <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <span>Menu Total:</span>
                                <span class="font-medium text-slate-800">₱ {{$menuTotal}}</span>
                                </div>

                                <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                <span>Services &amp; Misc Total:</span>
                                <span class="font-medium text-slate-800">₱ {{$serviceTotal}}</span>
                                </div>

                                <div class="flex justify-between items-center py-1 border-b border-slate-200/80">
                                <span>Venue Total:</span>
                                <span class="font-medium text-slate-800">₱ {{$venueTotal}}</span>
                                </div>
                            </div>

                            <!-- Grand Total -->
                            <div class="flex justify-between items-center mt-4 pt-1">
                                <span class="text-base font-extrabold tracking-wider text-slate-900 uppercase">
                                Grand Total:
                                </span>
                                <span class="text-xl font-extrabold text-emerald-600">
                                    ₱ {{ $grand_total }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-2 p-3 col-span-3">
                        <div class="grid grid-cols-4 gap-2">
                            <div class="col-span-4">
                                <x-ts-textarea readonly label="Notes" resize maxlength="250" count placeholder="(No Note Added)" wire:model="notes"/>
                                
                            </div>
                            <div class="col-span-2">
                                <x-ts-input wire:model="reviewedBy" label="Reviewed By" readonly/>
                            </div>

                            <div class="col-span-2">
                                <x-ts-input wire:model="approvedBy" label="Approved By" readonly/>
                            </div>
                        </div>

                        <div>
                            <x-ts-step wire:model="step" circles >
                                <x-ts-step.items step="1"
                                            title="Create Event"
                                            description="Step 1">
                                </x-ts-step.items>
                                 <x-ts-step.items step="2"
                                            title="Confirmation"
                                            description="Step 2">
                                </x-ts-step.items>
                                <x-ts-step.items step="3"
                                            title="Budget Allocation"
                                            description="Step 2">
                                </x-ts-step.items>
                                <x-ts-step.items step="4"
                                            title="For Liquidation"
                                            description="Step 3">
                                </x-ts-step.items>
                                <x-ts-step.items step="5"
                                            completed
                                            title="Completed"
                                            description="Step 6">
                                            <b>Event Completed!</b>
                                </x-ts-step.items>
                            </x-ts-step>
                        </div>
                    </div>
                </div>
            </x-ts-card>
        </x-ts-slide>
    </x-ts-tab>

    {{-- EVENT BOOKING PRINTABLE PREVIEW MODAL --}}
    <x-ts-modal wire="printPreviewModal" size="6xl" center>
        <x-slot:title>
            <div class="flex items-center gap-2">
                <x-ts-icon name="document-arrow-down" class="w-5 h-5 text-primary-600" />
                <span class="font-bold text-slate-800 dark:text-slate-100">
                    Export to PDF - Banquet Event Order ({{ $previewEvent?->reference ?? '' }})
                </span>
            </div>
        </x-slot:title>

        <div class="space-y-4">
            {{-- Toolbar / Screen Controls (Hidden in Print) --}}
            <div class="flex items-center justify-between p-3 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 print:hidden">
                <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                    <x-ts-badge text="PDF Ready" color="green" light />
                    <span>Official Banquet Event Order (BEO) format ready for PDF export.</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-ts-button flat sm text="Close" wire:click="$set('printPreviewModal', false)" />
                    <x-ts-button sm icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf({{ $previewEventId }})" color="primary" />
                </div>
            </div>

            @if($previewEvent)
                {{-- Document Sheet --}}
                <div id="printable-booking-preview" class="bg-white text-slate-900 p-6 sm:p-10 rounded-2xl border border-slate-200 shadow-sm print:border-none print:shadow-none print:p-0 print:m-0 print:w-full">
                    {{-- Company Header & Logo --}}
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
                                    Banquet Sales & Catering Services Department
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-3 py-1 rounded bg-slate-900 text-white text-xs font-bold uppercase tracking-wider">
                                BANQUET EVENT ORDER
                            </span>
                            <div class="mt-2 text-lg font-black font-mono text-slate-900">
                                {{ $previewEvent->reference }}
                            </div>
                            <div class="text-xs text-slate-500">
                                Date Created: {{ $previewEvent->created_at ? $previewEvent->created_at->format('M d, Y') : now()->format('M d, Y') }}
                            </div>
                            <div class="mt-1">
                                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded {{ $previewEvent->status === 'CONFIRMED' ? 'bg-emerald-100 text-emerald-800' : ($previewEvent->status === 'CLOSED' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">
                                    Status: {{ $previewEvent->status }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Client & Event Profile (No logged-in user info) --}}
                    <div class="grid grid-cols-2 gap-4 mb-6 p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                        <div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                                Event & Client Profile
                            </h4>
                            <div class="space-y-1">
                                <div><span class="text-slate-500 font-medium">Event Title:</span> <strong class="text-slate-900">{{ $previewEvent->event_name }}</strong></div>
                                <div><span class="text-slate-500 font-medium">Client / Customer:</span> <span class="text-slate-800 font-semibold">{{ $previewEvent->customer?->full_name ?? 'N/A' }}</span></div>
                                @if($previewEvent->customer?->contact_number || $previewEvent->customer?->email)
                                    <div><span class="text-slate-500 font-medium">Contact:</span> <span class="text-slate-800">{{ $previewEvent->customer?->contact_number ?? 'N/A' }} • {{ $previewEvent->customer?->email ?? 'N/A' }}</span></div>
                                @endif
                            </div>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                                Schedule & Location
                            </h4>
                            <div class="space-y-1">
                                <div><span class="text-slate-500 font-medium">Schedule:</span> <span class="text-slate-800 font-medium">{{ \Carbon\Carbon::parse($previewEvent->start_date)->format('M d, Y') }} ({{ date('g:i A', strtotime($previewEvent->arrival_time)) }}) - {{ \Carbon\Carbon::parse($previewEvent->end_date)->format('M d, Y') }} ({{ date('g:i A', strtotime($previewEvent->departure_time)) }})</span></div>
                                <div><span class="text-slate-500 font-medium">Venue / Address:</span> <span class="text-slate-800">{{ $previewEvent->event_address }}</span></div>
                                <div><span class="text-slate-500 font-medium">Guest Count:</span> <span class="text-slate-800 font-semibold">{{ $previewEvent->guest_count }} Pax</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- 1. Menus Breakdown --}}
                    @if($previewEvent->menus->isNotEmpty())
                        <div class="mb-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                                <span>1. Catering & Menu Packages</span>
                                <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $previewEvent->menus->sum('total_amount'), 2) }}</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                            <th class="py-2 px-3 w-10 text-center">#</th>
                                            <th class="py-2 px-3">Recipe / Menu Item</th>
                                            <th class="py-2 px-3">Category</th>
                                            <th class="py-2 px-3 text-right">Rate</th>
                                            <th class="py-2 px-3 text-right">Qty</th>
                                            <th class="py-2 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($previewEvent->menus as $mIndex => $menuItem)
                                            <tr>
                                                <td class="py-1.5 px-3 text-center text-slate-400">{{ $mIndex + 1 }}</td>
                                                <td class="py-1.5 px-3 font-semibold text-slate-900">
                                                    {{ $menuItem->recipe?->menu_name ?? 'N/A' }}
                                                    @if($menuItem->note)
                                                        <span class="block text-[10px] text-slate-500 font-normal italic">Note: {{ $menuItem->note }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-1.5 px-3 text-slate-600">{{ $menuItem->recipe?->category?->category_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($menuItem->price?->amount ?? $menuItem->rate?->amount ?? 0), 2) }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $menuItem->qty }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) $menuItem->total_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- 2. Venues Breakdown --}}
                    @if($previewEvent->venues->isNotEmpty())
                        <div class="mb-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                                <span>2. Function Halls & Venues</span>
                                <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $previewEvent->venues->sum('total_amount'), 2) }}</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                            <th class="py-2 px-3 w-10 text-center">#</th>
                                            <th class="py-2 px-3">Venue / Facility</th>
                                            <th class="py-2 px-3 text-right">Rate</th>
                                            <th class="py-2 px-3 text-right">Qty</th>
                                            <th class="py-2 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($previewEvent->venues as $vIndex => $venueItem)
                                            <tr>
                                                <td class="py-1.5 px-3 text-center text-slate-400">{{ $vIndex + 1 }}</td>
                                                <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $venueItem->venue?->venue_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($venueItem->rate?->amount ?? 0), 2) }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $venueItem->qty }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) $venueItem->total_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- 3. Services & Miscellaneous Breakdown --}}
                    @if($previewEvent->services->isNotEmpty())
                        <div class="mb-5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800 mb-2 flex items-center justify-between border-b border-slate-200 pb-1">
                                <span>3. Additional Services & Miscellaneous</span>
                                <span class="text-slate-500 font-mono font-normal">Subtotal: ₱{{ number_format((float) $previewEvent->services->sum('total_amount'), 2) }}</span>
                            </h3>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-xs text-left border-collapse">
                                    <thead>
                                        <tr class="bg-slate-100 text-slate-800 font-bold uppercase tracking-wider border-b border-slate-200">
                                            <th class="py-2 px-3 w-10 text-center">#</th>
                                            <th class="py-2 px-3">Service Name</th>
                                            <th class="py-2 px-3 text-right">Rate</th>
                                            <th class="py-2 px-3 text-right">Qty</th>
                                            <th class="py-2 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        @foreach($previewEvent->services as $sIndex => $serviceItem)
                                            <tr>
                                                <td class="py-1.5 px-3 text-center text-slate-400">{{ $sIndex + 1 }}</td>
                                                <td class="py-1.5 px-3 font-semibold text-slate-900">{{ $serviceItem->service?->service_name ?? 'N/A' }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono">₱{{ number_format((float) ($serviceItem->price?->amount ?? 0), 2) }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-medium">{{ $serviceItem->qty }}</td>
                                                <td class="py-1.5 px-3 text-right font-mono font-bold text-slate-900">₱{{ number_format((float) $serviceItem->total_amount, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- Financial Summary Box --}}
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            @if($previewEvent->notes)
                                <div class="p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs h-full">
                                    <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Special Instructions / Notes:</span>
                                    <p class="mt-1 text-slate-800 whitespace-pre-line">{{ $previewEvent->notes }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                            <h4 class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] border-b border-slate-200 pb-1">
                                Financial Summary
                            </h4>
                            <div class="space-y-1.5">
                                <div class="flex justify-between items-center text-slate-600">
                                    <span>Catering & Menus:</span>
                                    <span class="font-mono font-semibold text-slate-800">₱{{ number_format((float) $previewEvent->menus->sum('total_amount'), 2) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-slate-600">
                                    <span>Venues & Facilities:</span>
                                    <span class="font-mono font-semibold text-slate-800">₱{{ number_format((float) $previewEvent->venues->sum('total_amount'), 2) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-slate-600">
                                    <span>Services & Misc:</span>
                                    <span class="font-mono font-semibold text-slate-800">₱{{ number_format((float) $previewEvent->services->sum('total_amount'), 2) }}</span>
                                </div>
                                <div class="pt-2 border-t border-slate-300 flex justify-between items-center">
                                    <span class="font-bold uppercase tracking-wider text-slate-900">Total Contract Amount:</span>
                                    <span class="text-base font-black font-mono text-emerald-700">₱{{ number_format((float) $previewEvent->total_amount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Signatures Section (3 Columns - Prepared, Reviewed, Approved, NO client conforme) --}}
                    <div class="grid grid-cols-3 gap-6 pt-6 mt-6 border-t border-slate-200 text-xs">
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $previewEvent->preparedBy?->full_name ?? 'Event Coordinator' }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Prepared By</div>
                            <div class="text-[10px] text-slate-400">{{ $previewEvent->preparedBy?->position_name ?? 'Event Coordinator' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $previewEvent->reviewedBy?->full_name ?? '_________________________' }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Reviewed By</div>
                            <div class="text-[10px] text-slate-400">{{ $previewEvent->reviewedBy?->position_name ?? 'Banquet Supervisor' }}</div>
                        </div>
                        <div class="text-center">
                            <div class="h-10"></div>
                            <div class="border-b border-slate-400 font-bold text-slate-900 pb-1">
                                {{ $previewEvent->approvedBy?->full_name ?? '_________________________' }}
                            </div>
                            <div class="text-[11px] font-semibold text-slate-600 mt-1 uppercase">Approved By</div>
                            <div class="text-[10px] text-slate-400">{{ $previewEvent->approvedBy?->position_name ?? 'Operations Manager' }}</div>
                        </div>
                    </div>

                    {{-- Document Tracking Footer --}}
                    <div class="mt-8 pt-4 border-t border-dashed border-slate-200 flex justify-between items-center text-[10px] text-slate-400">
                        <div>{{ auth()->user()->branch?->company?->company_name ?? 'Hacienda Hills Resort & Leisure' }} • Banquet Management System</div>
                        <div>Generated on {{ now()->format('M d, Y h:i:s A') }}</div>
                    </div>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-ts-button flat text="Close" wire:click="$set('printPreviewModal', false)" />
                <x-ts-button icon="document-arrow-down" text="Export to PDF" wire:click="exportPdf({{ $previewEventId }})" color="primary" />
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
            #printable-booking-preview, #printable-booking-preview * {
                visibility: visible !important;
            }
            #printable-booking-preview {
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

    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Booking" href="{{ route('event-booking-create')}}" navigate />
    </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
