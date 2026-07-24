<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;
use App\Models\BanquetEvent\Event;


new class extends Component
{
    use WithPagination;

    public ?int $quantity = 10;
    public ?string $search = null;
    public ?string $status = null;
    public ?array $dates = null;
    public $activeEvents = [];
    public bool $viewSlide = false;

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
            $menuTotal,
            $serviceTotal,
            $venueTotal;

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
                    return $query->where('reference', 'like', "%{$this->search}%");
                })
                ->when($this->dates, function (Builder $query) {
                    if (is_array($this->dates) && count($this->dates) === 2 && !empty($this->dates[0]) && !empty($this->dates[1])) {
                        return $query->whereBetween('start_date', $this->dates);
                    }
                })
                ->when($this->status, function (Builder $query) {
                    return $query->where('status', $this->status);
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
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
                        @if ($row->withdrawal_status == 'PREPARING')
                            <a href="{{ route('withdrawal.edit', ['id' => $row->id]) }}">
                                <x-ts-dropdown.items text="Edit" icon="pencil-square" />
                            </a>
                        @endif
                        <a href="{{ route('withdrawal.view', ['id' => $row->id]) }}">
                            <x-ts-dropdown.items text="View" separator icon="eye" />
                        </a>
                        <a>
                            <x-ts-dropdown.items text="Cancel" color="rose" separator icon="x-mark" />
                        </a>
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
                <span class="text-4xl">{{ $eventName }}</span>
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
                                <x-ts-avatar image="{{ asset($row->recipe?->menu_image) }}" md text="AIR" square />
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
                                ₱ {{number_format($row->price->amount, 2 )}}
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
    <x-ts-dial lg>
            <x-ts-dial.items icon="plus" label="New Booking" href="{{ route('withdrawal.create')}}" navigate />
            <x-ts-dial.items icon="printer" label="Print Preview" href="/posts/1" navigate-hover />
    </x-ts-dial>
    <x-ts-back-to-top lg/>
</div>
