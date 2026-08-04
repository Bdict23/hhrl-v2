<?php

use Livewire\Component;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use App\Models\Business\Venue;
use App\Models\Business\Service;
use App\Models\Business\BranchRecipe;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Event\BanquetEventService;
use Carbon\Carbon;




new class extends Component
{
    use WithPagination;
    use Interactions;

    
    public ?int $quantity = 5;
    public ?string $foodType = 'Banquet';
    public $foodCategoryID = null;

    public ?string $search = null;
    public array $sort = [
            'column' => 'bal_qty',
            'direction' => 'desc',
        ];
    
    public $grantTotal, 
            $eventName, 
            $guestCount,
            $customer,
            $address,
            $checkInDate,
            $checkOutDate,
            $arrivalTime,
            $departureTime,
            $reviewedBy,
            $approvedBy,
            $note,
            $status;


    // venue variables
        public  $selectedVenue = [],
            $selectedVenueRows = [],
            $grand_totalVenue = 0.00;
    // service variables
        public  $selectedService = [],
            $selectedServiceRows = [],
            $grand_totalService = 0.00;
    // food variables
        public  $selectedFood = [],
            $selectedFoodRows = [],
            $grand_totalFood = 0.00;

    // VALIDATION
        protected $rules = [
            'eventName' => 'required',
            'address' => 'required',
            'guestCount' => 'required',
            'customer' => 'required|exists:customers,id',
            'note' => 'nullable|string',
            'checkInDate' => 'required|date',
            'checkOutDate' => 'required|date|after_or_equal:checkInDate',
            'approvedBy' => 'required|exists:employees,id',
            'reviewedBy' => 'required|exists:employees,id',
            'selectedVenueRows.*.quantity' => 'nullable|numeric|min:1',
            'selectedServiceRows.*.quantity' => 'nullable|numeric|min:1',

        ];
    protected $messages=[
            'selectedVenueRows.*.quantity.min' => 'Quantity must be greater than 0.',
            'selectedServiceRows.*.quantity.min' => 'Quantity must be greater than 0.',
            'selectedVenueRows.*.quantity.required' => 'Qty is required.',
            'selectedServiceRows.*.quantity.required' => 'Qty is required.',
            'eventName.required' => 'Event name is required.',
            'address.required' => 'Address is required.',
            'guestCount.required' => 'Guest count is required.',
            'customer.required' => 'Customer is required.',
            'approvedBy.required' => 'Approver is required.',
            'reviewedBy.required' => 'Reviewer is required.',
            'reviewedBy.exists' => 'Select a valid reviewer on the list.',
            'approvedBy.exists' => 'Select a valid reviewer on the list.',
            'checkOutDate.after_or_equal' => 'Invalid end date.',
        ];
    
    // VENUE HOOKS
        public function updatedSelectedVenue($ids)
        {

            // 1. Get IDs already present in the table
            $existingIds = array_column($this->selectedVenueRows, 'id');

            // 2. Identify the IDs that are not in the table yet
            $newIds = array_diff($ids, $existingIds);

            // 3. Identify IDs that were unchecked (to remove them from table)
            $removedIds = array_diff($existingIds, $ids);

            // Handle Removals: if an ID is unchecked in the modal, remove it from the table
            if (!empty($removedIds)) {
                $this->selectedVenueRows = array_values(array_filter($this->selectedVenueRows, function($row) use ($removedIds) {
                    return !in_array($row['id'], $removedIds);
                }));
            }

            // Handle Additions: Only query the database for the NEW IDs
            if (!empty($newIds)) {
                $items = Venue::whereIn('id', $newIds)
                    ->get();

                foreach ($items as $item) {
                    $this->selectedVenueRows[] = [
                        'id'                => $item->id,
                        'venue_code'        => $item->venue_code,
                        'venue_name'        => $item->venue_name,
                        'description'       => $item->description ?? 'N/A',
                        'capacity'          => (float) ($item->capacity ?? 0),
                        'price_id'          => $item->rate?->id ?? null,
                        'rate'              => $item->rate?->amount ?? 0,
                        'sub_total'         => $item->rate?->amount ?? 0 * 1,
                        'quantity'               => 1,
                    ];
                }
            }
            $this->calculateGrandTotal();

        }
        public function calculateGrandTotal()
        {
            $this->grand_totalVenue = collect($this->selectedVenueRows)->sum('sub_total');

        }
        // Remove from selected venue
        public function removeVenue($index)
        {
            unset($this->selectedVenueRows[$index]);
            // Reset array keys to prevent index gaps
            $this->selectedVenueRows = array_values($this->selectedVenueRows);

            // Sync back to your original selection ID array if necessary
                $this->selectedVenue = collect($this->selectedVenueRows)->pluck('id')->toArray();
                $this->toast()->success('Success', 'Removed Successfully')->send();

                $this->calculateGrandTotal();

        }
        // This runs automatically whenever any value in $selectedVenueRows changes
        public function updatedSelectedVenueRows($value, $key)
        {
            // The $key looks like "0.quantity" = (index.property)
            // We extract the index to update the correct row
            $parts = explode('.', $key);
            $index = $parts[0];

            if (isset($parts[1]) && $parts[1] === 'quantity') {
                $qty = (float) ($this->selectedVenueRows[$index]['quantity'] ?? 0);
                $cost = (float) ($this->selectedVenueRows[$index]['rate'] ?? 0);

                // Update the Sub-total for this row
                $this->selectedVenueRows[$index]['sub_total'] = $qty * $cost;
            }
            $this->calculateGrandTotal();
        }

    // SERVICE HOOKS
        public function updatedSelectedService($ids)
        {

            // 1. Get IDs already present in the table
            $existingIds = array_column($this->selectedServiceRows, 'id');

            // 2. Identify the IDs that are not in the table yet
            $newIds = array_diff($ids, $existingIds);

            // 3. Identify IDs that were unchecked (to remove them from table)
            $removedIds = array_diff($existingIds, $ids);

            // Handle Removals: if an ID is unchecked in the modal, remove it from the table
            if (!empty($removedIds)) {
                $this->selectedServiceRows = array_values(array_filter($this->selectedServiceRows, function($row) use ($removedIds) {
                    return !in_array($row['id'], $removedIds);
                }));
            }

            // Handle Additions: Only query the database for the NEW IDs
            if (!empty($newIds)) {
                $items = Service::whereIn('id', $newIds)
                    ->get();

                foreach ($items as $item) {
                    $this->selectedServiceRows[] = [
                        'id'                    => $item->id,
                        'service_code'          => $item->service_code,
                        'service_name'          => $item->service_name,
                        'service_description'   => $item->service_description ?? '',
                        'category'              => $item->category->category_name ?? '',
                        'price_id'              => $item->rate?->id ?? null,
                        'rate'                  => $item->rate?->amount ?? 0,
                        'sub_total'             => $item->rate?->amount ?? 0 * 1,
                        'quantity'               => 1,
                    ];
                }
            }
            $this->calculateServiceGrandTotal();

        }
        public function calculateServiceGrandTotal()
        {
            $this->grand_totalService = collect($this->selectedServiceRows)->sum('sub_total');
        }
        // Remove from selected service
        public function removeService($index)
        {
            unset($this->selectedServiceRows[$index]);
            // Reset array keys to prevent index gaps
            $this->selectedServiceRows = array_values($this->selectedServiceRows);

            // Sync back to your original selection ID array if necessary
                $this->selectedService = collect($this->selectedServiceRows)->pluck('id')->toArray();
                $this->toast()->success('Success', 'Removed Successfully')->send();

                $this->calculateGrandTotal();

        }
        // This runs automatically whenever any value in $selectedServiceRows changes
        public function updatedSelectedServiceRows($value, $key)
        {
            // The $key looks like "0.quantity" = (index.property)
            // We extract the index to update the correct row
            $parts = explode('.', $key);
            $index = $parts[0];

            if (isset($parts[1]) && $parts[1] === 'quantity') {
                $qty = (float) ($this->selectedServiceRows[$index]['quantity'] ?? 0);
                $cost = (float) ($this->selectedServiceRows[$index]['rate'] ?? 0);

                // Update the Sub-total for this row
                $this->selectedServiceRows[$index]['sub_total'] = $qty * $cost;
            }
            $this->calculateServiceGrandTotal();
        }

    // MENU HOOKS
        public function updatedSelectedFood($ids)
        {

            // 1. Get IDs already present in the table
            $existingIds = array_column($this->selectedFoodRows, 'id');

            // 2. Identify the IDs that are not in the table yet
            $newIds = array_diff($ids, $existingIds);

            // 3. Identify IDs that were unchecked (to remove them from table)
            $removedIds = array_diff($existingIds, $ids);

            // Handle Removals: if an ID is unchecked in the modal, remove it from the table
            if (!empty($removedIds)) {
                $this->selectedFoodRows = array_values(array_filter($this->selectedFoodRows, function($row) use ($removedIds) {
                    return !in_array($row['id'], $removedIds);
                }));
            }

            // Handle Additions: Only query the database for the NEW IDs
            if (!empty($newIds)) {
                $items = BranchRecipe::whereIn('id', $newIds)->get();

                foreach ($items as $item) {
                    $this->selectedFoodRows[] = [
                        'id'                    => $item->menu_id,
                        'menu_image'            => $item->recipe?->menu_image,
                        'menu_name'             => $item->recipe->menu_name ?? '',
                        'category'              => $item->recipe->category->category_name ?? '',
                        'price_id'              => $item->recipe->rate?->id ?? null,
                        'rate'                  => $item->recipe->rate?->amount ?? 0,
                        'quantity'              => 1,
                        'sub_total'             => $item->recipe->rate?->amount ?? 0 * 1,
                        'note'                  => '',
                    ];
                }
            }
            $this->calculateFoodGrandTotal();

        }
        public function calculateFoodGrandTotal()
        {
            $this->grand_totalFood = collect($this->selectedFoodRows)->sum('sub_total');
        }
        // Remove from selected service
        public function removeFood($index)
        {
            unset($this->selectedFoodRows[$index]);
            // Reset array keys to prevent index gaps
            $this->selectedFoodRows = array_values($this->selectedFoodRows);

            // Sync back to your original selection ID array if necessary
                $this->selectedFood = collect($this->selectedFoodRows)->pluck('id')->toArray();
                $this->toast()->success('Success', 'Removed Successfully')->send();

                $this->calculateGrandTotal();

        }
        // This runs automatically whenever any value in $selectedFoodRows changes
        public function updatedSelectedFoodRows($value, $key)
        {
            // The $key looks like "0.quantity" = (index.property)
            // We extract the index to update the correct row
            $parts = explode('.', $key);
            $index = $parts[0];

            if (isset($parts[1]) && $parts[1] === 'quantity') {
                $qty = (float) ($this->selectedFoodRows[$index]['quantity'] ?? 0);
                $cost = (float) ($this->selectedFoodRows[$index]['rate'] ?? 0);

                // Update the Sub-total for this row
                $this->selectedFoodRows[$index]['sub_total'] = $qty * $cost;
            }
            $this->calculateFoodGrandTotal();
        }
    public function saveEventAsDraftAction()
    {
        $validated = $this->validate();
        $this->status = 'DRAFT';
        $this->dialog()
        ->question('Save Event?', 'Are you sure to save this event as draft?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function saveEventAsFinalAction(){
        $validated = $this->validate();
        $this->status = 'FINAL';
        $this->dialog()
        ->question('Save Event?', 'Are you sure to save this event as final ?')
        ->confirm(
            'Confirm',
            'store', //pass a functio to call
            )
        ->cancel('Cancel')
        ->send();
    }

    public function store(BanquetEventService $service)
    {
        try {

            // We structure it to match the $data array expected by the Service
            $data = [
                'branch_id' => Auth::user()->branch_id,
                'event_name' => $this->eventName,
                'guest_count' => $this->guestCount,
                'customer_id' => $this->customer,
                'event_address' => $this->address,
                'start_date' => $this->checkInDate,
                'end_date' => $this->checkOutDate,
                'arrival_time' => Carbon::createFromFormat('h:i A', $this->arrivalTime )->format('H:i:s'),
                'departure_time' => Carbon::createFromFormat('h:i A', $this->departureTime )->format('H:i:s'),
                'reviewer_id' => $this->reviewedBy,
                'approver_id' => $this->approvedBy,
                'status' => $this->status,
                'note' => $this->note,
                'prepared_by'    => Auth::user()->emp_id,
                'venues' => $this->selectedVenueRows, 
                'services' => $this->selectedServiceRows, 
                'menu' => $this->selectedFoodRows, 
                'venue_total_amount' => $this->grand_totalVenue,
                'service_total_amount' => $this->grand_totalService,
                'menu_total_amount' => $this->grand_totalFood,
            ];

            

            // 4. Call the Service
            $event = $service->createEvent($data);

            // 5. Success Feedback
            $this->toast()->success('Success', "Event created successfully!")->send();
            $this->reset();
            return redirect()->route('petty-cash-voucher.summary');

        } catch (\Exception $e) {
            // Log the error if needed
            \Log::error("PCV Creation Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }

    }

    

   public function with(): array
    {
        return [
            'selectedVenueHeader' => [
                ['index' => 'venue_code', 'label' => 'Code'],
                ['index' => 'venue_name', 'label' => 'venue name'],
                ['index' => 'capacity', 'label' => 'capacity' , 'sortable' => false],
                ['index' => 'rate', 'label' => 'rate' , 'sortable' => false],
                ['index' => 'quantity', 'label' => 'qty',  'sortable' => false],
                ['index' => 'sub_total', 'label' => 'sub total',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'venueListHeader' => [
                ['index' => 'venue_code', 'label' => 'Code'],
                ['index' => 'venue_name', 'label' => 'venue name'],
                ['index' => 'capacity', 'label' => 'capacity' , 'sortable' => false],
                ['index' => 'rate', 'label' => 'rate' , 'sortable' => false],
            ],
            'venueRow' => Venue::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->when($this->search, function (Builder $query) {
                    return $query->where('venue_name', 'like', "%{$this->search}%");
                })
                ->where('status', 'active')
                ->paginate($this->quantity)
                ->withQueryString(),

            'selectedServiceHeader' => [
                ['index' => 'service_code', 'label' => 'Code'],
                ['index' => 'service_name', 'label' => 'service name'],
                ['index' => 'category', 'label' => 'category' , 'sortable' => false],
                ['index' => 'rate', 'label' => 'rate' , 'sortable' => false],
                ['index' => 'quantity', 'label' => 'Qty' , 'sortable' => false],
                ['index' => 'sub_total', 'label' => 'Sub-total',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'serviceListHeader' => [
                ['index' => 'service_code', 'label' => 'code'],
                ['index' => 'service_name', 'label' => 'service name'],
                ['index' => 'category', 'label' => 'category'],
                ['index' => 'rate', 'label' => 'rate'],
            ],
            'serviceRow' => Service::query()
                ->where('branch_id', auth()->user()->branch_id)
                ->when($this->search, function (Builder $query) {
                    return $query->where('service_name', 'like', "%{$this->search}%");
                })
                ->where('status', 'ACTIVE')
                ->paginate($this->quantity)
                ->withQueryString(),

                // FOOD
                'selectedFoodHeader' => [
                ['index' => 'menu_image', 'label' => 'image'],
                ['index' => 'menu_name', 'label' => 'name'],
                ['index' => 'category', 'label' => 'category' , 'sortable' => false],
                ['index' => 'rate', 'label' => 'rate' , 'sortable' => false],
                ['index' => 'quantity', 'label' => 'Qty' , 'sortable' => false],
                ['index' => 'sub_total', 'label' => 'Sub-total',  'sortable' => false],
                ['index' => 'action', 'label' => 'Action',  'sortable' => false],
            ],
            'foodListHeader' => [
                ['index' => 'menu_image', 'label' => 'image' , 'sortable' => false],
                ['index' => 'menu_name', 'label' => 'name' , 'sortable' => false],
                ['index' => 'category', 'label' => 'category'],
                ['index' => 'rate', 'label' => 'rate'],
                ['index' => 'type', 'label' => 'type'],
            ],
            'foodRow' => BranchRecipe::query()
                ->whereHas('activeBranchMenu')
                ->whereHas('recipe', function ($query) {
                    // Filter by type
                    $query->where('recipe_type', $this->foodType);

                    // Filter by category (FIXED)
                    $query->when($this->foodCategoryID, function ($q) {
                        $q->where('category_id', $this->foodCategoryID);
                    });

                    // Add search query if user entered search text (FIXED)
                    $query->when($this->search, function ($q) {
                        $q->where(function ($subQuery) {
                            $subQuery->where('menu_name', 'like', '%' . $this->search . '%')
                                    ->orWhere('menu_code', 'like', '%' . $this->search . '%');
                        });
                    });
                })
                ->orderBy(...array_values($this->sort))
                ->paginate($this->quantity)
                ->withQueryString()
        ];
    }
};
?>

<div>
    <x-ts-card class="w-full">
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold tracking-tight uppercase">BOOK EVENT</h2>
        </div>

        <!-- Centering Wrapper -->
        <div class="flex justify-center w-full">
            <!-- Constrain width so it spreads out nicely instead of shrinking -->
            <div class="w-full max-w-7xl">
                <x-ts-step selected="1" circles helpers navigate-previous>
                    <x-ts-step.items step="1" title="Details">
                        <div class="mb-8 mt-5">
                            <x-ts-card header="EVENT" light color="primary" class="mb-4">
                                <div class="grid grid-cols-2 gap-8">
                                    <x-ts-input label="Event Name *" class="col-span-2" wire:model="eventName"/>
                                    <x-ts-input label="Address *" wire:model="address" />

                                    <div class="col-span-2 grid grid-cols-2 gap-6">
                                        <div class="grid col-span-1 gap-2">
                                            <x-ts-number step="5" label="Guest Count / Pax. *" wire:model="guestCount"/>
                                            <x-ts-select.styled 
                                                searchable 
                                                :request="route('api.active.event-booking-customers',['branch_id' => auth()->user()->branch_id])" 
                                                label="Customer *" 
                                                select="label:name|value:id"
                                                :placeholders="[
                                                    'default' => 'Select Customer',
                                                    ]"
                                                wire:model="customer">
                                                <x-slot:after>
                                                    <div class="px-2 mb-2 flex justify-center items-center">
                                                        <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search }); $tsui.open.modal('create-customer-modal')">
                                                            <span x-html="`Register new customer <b>${search}</b>`"></span>
                                                        </x-ts-button>
                                                    </div>
                                                </x-slot:after>
                                            </x-ts-select.styled>
                                            <x-ts-textarea maxlength="100" count label="Note" resize palceholder="(optional)" wire:model="note" maxlength="100" count />
                                        </div>
                                        <div class="grid col-span-1 grid-cols-2 gap-6">
                                            <div class="col-span-1 gap-2 grid">
                                                <x-ts-date format="DD [of] MMMM [of] YYYY" label="Check-in Date *" wire:model="checkInDate"/>
                                                <x-ts-time label="Arrival Time *" wire:model="arrivalTime"/>
                                                <x-ts-select.styled
                                                    :request="route('api.active.event-booking-reviewers', ['branch_id' => auth()->user()->branch_id ])"
                                                    select="label:full_name|value:id|description:position"
                                                    wire:model="reviewedBy"
                                                    label="Reviewed By *"
                                                    :placeholders="[
                                                    'default' => 'Select',
                                                    'empty'   => 'No reviewers found',
                                                    ]" />
                                            </div>
                                            <div class="col-span-1 gap-2 grid">
                                                <x-ts-date format="DD [of] MMMM [of] YYYY" label="Check-out Date *" wire:model="checkOutDate"/>
                                                <x-ts-time label="Departure Time *" wire:model="departureTime"/>
                                                <x-ts-select.styled
                                                    :request="route('api.active.event-booking-approvers', ['branch_id' => auth()->user()->branch_id])"
                                                    wire:model="approvedBy"
                                                    select="label:full_name|value:id|description:position"
                                                    label="Approved By *"
                                                    :placeholders="[
                                                        'default' => 'Select    ',
                                                        'empty'   => 'No aapprovers found',
                                                    ]"  />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </x-ts-card>
                        </div>
                    </x-ts-step.items>
                    <x-ts-step.items step="2" title="Venue" >
                        <div class="mb-8 mt-5">
                            <x-ts-card header="VENUE" light color="primary" class="mb-4">
                                <x-ts-table :headers="$selectedVenueHeader" :rows="$selectedVenueRows" striped expandable>
                                    <x-slot:footer>

                                        <div class="flex justify-between mt-4">
                                            <x-ts-button icon="plus" position="left" x-on:click="$tsui.open.modal('modal-add-venue')" flat>Add Venue </x-ts-button>
                                            <span class="text-xl font-bold">
                                                ₱ {{ number_format($grand_totalVenue,2) }}
                                            </span>
                                        </div>
                                    </x-slot:footer>
                                    @interact('column_action', $row)
                                        <x-ts-button
                                                color="rose"
                                                outline
                                                wire:click="removeVenue({{ $loop->index }})"
                                                loading="removeVenue({{ $loop->index }})">
                            
                                                <x-ts-icon name="trash"
                                                    wire:loading.remove
                                                    wire:target="removeVenue({{ $loop->index }})"
                                                    class="w-5 h-5" />
                                            </x-ts-button>
                                    @endinteract
                                    @interact('column_rate', $row)
                                        ₱ {{ number_format($row['rate'], 2) }}
                                    @endinteract
                                    @interact('column_quantity', $row)
                                    <x-ts-number sm
                                        wire:model.live.debounce.500ms="selectedVenueRows.{{ $loop->index }}.quantity" />
                                    @endinteract
                                    @interact('column_sub_total', $row)
                                    ₱ {{ number_format($row['sub_total'], 2) }}
                                    @endinteract
                            
                                    @interact('sub_table', $row)
                                        <x-ts-table :headers="[
                                            ['index' => 'description', 'label' => 'description'],
                                        ]"
                                        :rows="[[
                                            'description'       => $row['description'],
                                        ]]" />
                                    @endinteract
                            
                                </x-ts-table>
                                @error('selectedVenueRows.*')
                                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                                @enderror
                            </x-ts-card>
                        </div>
                    </x-ts-step.items>
                    <x-ts-step.items step="3" title="Services" >
                        <div class="mb-8 mt-5">
                            <x-ts-card header="SERVCES" light color="primary" class="mb-4">
                                <x-ts-table :headers="$selectedServiceHeader" :rows="$selectedServiceRows" striped expandable>
                                    <x-slot:footer>
                                        
                                    <div class="flex justify-between mt-4">
                                        <x-ts-button icon="plus" position="left" x-on:click="$tsui.open.modal('modal-add-service')" flat>Add Service </x-ts-button>
                                        <span class="text-xl font-bold">
                                            ₱ {{ number_format($grand_totalService,2) }}
                                        </span>
                                        </div>
                                    </x-slot:footer>
                                    @interact('column_action', $row)
                                        <x-ts-button
                                                color="rose"
                                                outline
                                                wire:click="removeService({{ $loop->index }})"
                                                loading="removeService({{ $loop->index }})">
                            
                                                <x-ts-icon name="trash"
                                                    wire:loading.remove
                                                    wire:target="removeService({{ $loop->index }})"
                                                    class="w-5 h-5" />
                                            </x-ts-button>
                                    @endinteract
                                    @interact('column_rate', $row)
                                        ₱ {{ number_format($row['rate'], 2) }}
                                    @endinteract
                                     @interact('column_category', $row)
                                         {{ $row['category']}}
                                    @endinteract
                                    @interact('column_quantity', $row)
                                    <x-ts-number sm
                                        wire:model.live.debounce.500ms="selectedServiceRows.{{ $loop->index }}.quantity" />
                                    @endinteract
                                    @interact('column_sub_total', $row)
                                    ₱ {{ number_format($row['sub_total'], 2) }}
                                    @endinteract
                            
                                    @interact('sub_table', $row)
                                        <x-ts-table :headers="[
                                            ['index' => 'description', 'label' => 'description'],
                                        ]"
                                        :rows="[[
                                            'description'       => $row['service_description'],
                                        ]]" />
                                    @endinteract
                            
                                </x-ts-table>
                                @error('selectedServiceRows')
                                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                                @enderror
                            </x-ts-card>
                        </div>
                    </x-ts-step.items>
                    <x-ts-step.items step="4" title="Food" >
                        <div class="mb-8 mt-5">
                            <x-ts-card header="FOOD" light color="primary" class="mb-4">
                                <x-ts-table :headers="$selectedFoodHeader" :rows="$selectedFoodRows" striped expandable>
                                    <x-slot:footer>
                                        <div class="flex justify-between mt-4">
                                        <x-ts-button icon="plus" position="left" x-on:click="$tsui.open.modal('modal-add-food')" flat>Add Food</x-ts-button>
                                            <span class="text-xl font-bold">
                                                ₱ {{ number_format($grand_totalFood,2) }}
                                            </span>
                                        </div>
                                    </x-slot:footer>
                                    @interact('column_menu_image', $row)
                                        <x-ts-avatar image="{{ asset('storage/'.$row['menu_image']) }}" md text="AIR" square />
                                    @endinteract
                                    @interact('column_menu_name',$row)
                                        {{ $row['menu_name'] }}
                                    @endinteract 
                                    @interact('column_category',$row)
                                        {{ $row['category']}}
                                    @endinteract
                                    @interact('column_rate',$row)
                                        ₱ {{ number_format($row['rate'], 2) }}
                                    @endinteract
                                    @interact('column_quantity', $row)
                                    <x-ts-number sm wire:model.live.debounce.500ms="selectedFoodRows.{{ $loop->index }}.quantity" />
                                    @endinteract
                                    @interact('column_sub_total', $row)
                                    ₱ {{ number_format($row['sub_total'], 2) }}
                                    @endinteract
                                    @interact('column_action', $row)
                                        <x-ts-button
                                                color="rose"
                                                outline
                                                wire:click="removeFood({{ $loop->index }})"
                                                loading="removeFood({{ $loop->index }})">
                            
                                                <x-ts-icon name="trash"
                                                    wire:loading.remove
                                                    wire:target="removeFood({{ $loop->index }})"
                                                    class="w-5 h-5" />
                                            </x-ts-button>
                                    @endinteract
                                    @interact('sub_table', $row)
                                        <x-ts-table :headers="[
                                            ['index'       => 'description', 'label' => 'add note'],
                                        ]"
                                        :rows="[[
                                            'description' => $row['note'],
                                        ]]">
                                            @interact('column_description', $row)
                                                    <x-ts-textarea maxlength="100" count wire:model="selectedFoodRows.{{ $loop->index }}.note" resize placeholder="Add note here.."/>
                                            @endinteract
                                        </x-ts-table>
                                    @endinteract
                            
                                </x-ts-table>
                                @error('selectedFoodRows')
                                    <x-ts-alert title="Error" text="{{ $message }}" color="red" light bordered="left" rounded="xl"/>
                                @enderror
                            </x-ts-card>
                        </div>
                    </x-ts-step.items>
                    <x-ts-step.items step="5" title="Validate" >
                       <div class="mb-8 mt-5">
                            <x-ts-card light color="primary" class="mb-4">
                                <x-slot:header>
                                    <span class="font-bold">SUMMARY</span>
                                </x-slot:header>
                                <div class="grid grid-cols-2 gap-6">
                                    <div class="col-span-2">
                                        <x-ts-input label="Event Name *" class="col-span-2" wire:model="eventName" readonly/>
                                    </div>
                                    <div class="grid gap-4 grid-cols-2">
                                        <div class="col-span-2">
                                            <x-ts-input label="Guest count" wire:model="guestCount" readonly/>
                                        </div>
                                        <div class="col-span-2">
                                            <x-ts-select.styled 
                                                searchable 
                                                :request="route('api.active.event-booking-customers',['branch_id' => auth()->user()->branch_id])" 
                                                label="Customer *" 
                                                select="label:name|value:id"
                                                wire:model="customer" readonly>
                                                <x-slot:after>
                                                    <div class="px-2 mb-2 flex justify-center items-center">
                                                        <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search }); $tsui.open.modal('create-customer-modal')">
                                                            <span x-html="`Register new customer <b>${search}</b>`"></span>
                                                        </x-ts-button>
                                                    </div>
                                                </x-slot:after>
                                            </x-ts-select.styled>
                                        </div>
                                        <div class="col-span-2">
                                            <x-ts-input label="Address" wire:model="address" readonly/>
                                        </div>
                                        <div class="w-full grid gap-2 p-3 col-span-2">                        
                                            <div class="w-full  rounded-xl bg-slate-100/70 p-5 shadow-sm border border-slate-200/60 font-sans ">
                                                <!-- Card Header -->
                                                <h3 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200/80">
                                                    Summary
                                                </h3>

                                                <!-- Breakdown Rows -->
                                                <div class="space-y-2 text-sm text-slate-600">
                                                    <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                                    <span>Menu Total:</span>
                                                    <span class="font-medium text-slate-800">₱ {{ number_format($grand_totalFood,2)}}</span>
                                                    </div>

                                                    <div class="flex justify-between items-center py-1 border-b border-slate-200/50">
                                                    <span>Services &amp; Misc Total:</span>
                                                    <span class="font-medium text-slate-800">₱ {{number_format($grand_totalService,2)}}</span>
                                                    </div>

                                                    <div class="flex justify-between items-center py-1 border-b border-slate-200/80">
                                                    <span>Venue Total:</span>
                                                    <span class="font-medium text-slate-800">₱ {{number_format($grand_totalVenue,2)}}</span>
                                                    </div>
                                                </div>

                                                <!-- Grand Total -->
                                                <div class="flex justify-between items-center mt-4 pt-1">
                                                    <span class="text-base font-extrabold tracking-wider text-slate-900 uppercase">
                                                    Grand Total:
                                                    </span>
                                                    <span class="text-xl font-extrabold text-emerald-600">
                                                        ₱ {{ number_format($grand_totalFood + $grand_totalService + $grand_totalVenue,2) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid gap-2 grid-cols-2">
                                        <div class="grid gap-2">
                                            <x-ts-date readonly format="DD [of] MMMM [of] YYYY" label="Check-in Date *" wire:model="checkInDate"/>
                                            <x-ts-input label="Arrival Time *" wire:model="arrivalTime" readonly/>
                                            <x-ts-select.styled
                                                        :request="route('api.active.withdrawal-reviewers', ['branch_id' => auth()->user()->branch_id ])"
                                                        select="label:fullName|value:id|description:position"
                                                        wire:model="reviewedBy"
                                                        label="Reviewed By *"
                                                        :placeholders="[
                                                        'default' => 'No selected reviewer',
                                                        'empty'   => 'No reviewers found',
                                                        ]" readonly/>
                                        </div>
                                        <div class="grid gap-2">
                                            <x-ts-date readonly format="DD [of] MMMM [of] YYYY" label="Check-out Date *" wire:model="checkOutDate"/>
                                            <x-ts-input label="Departure Time *" wire:model="departureTime" readonly/>
                                            <x-ts-select.styled
                                                        :request="route('api.active.withdrawal-approvers', ['branch_id' => auth()->user()->branch_id])"
                                                        wire:model="approvedBy"
                                                        select="label:fullName|value:id|description:position"
                                                        label="Approved By"
                                                        :placeholders="[
                                                            'default' => 'No selected approver    ',
                                                            'empty'   => 'No aapprovers found',
                                                        ]"  readonly/>
                                        </div>
                                       <div class="col-span-2">
                                         <x-ts-textarea maxlength="100" count label="Note" resize palceholder="(optional)" readonly wire:model="note"/>
                                       </div>
                                       <div class="col-span-2 h-fit flex justify-end gap-2">
                                            <x-ts-button text="Draft" outline wire:click="saveEventAsDraftAction" loading="saveEventAsDraftAction"/>
                                            <x-ts-button text="Place Event" wire:click="saveEventAsFinalAction" loading="saveEventAsFinalAction"/>
                                       </div>
                                    </div>
                                </div>
                            </x-ts-card>
                       </div>
                    </x-ts-step.items>
                </x-ts-step>
            </div>
        </div>

        {{-- ADD VENUE MODAL --}}
        <x-ts-modal id="modal-add-venue" size="5xl">
            <x-ts-card class="p-4 max-h-200 overflow-y-auto">
                <x-ts-table expandable loading  :headers="$venueListHeader" :rows="$venueRow" striped  filter  paginate selectable wire:model.live='selectedVenue'>
                    @interact('column_rate', $row)
                        ₱ {{ number_format($row->rate->amount ?? 0, 2) }}
                    @endinteract
                    @interact('sub_table', $row)
                        <x-ts-table :headers="[
                            ['index'       => 'description', 'label' => 'description'],
                        ]"
                        :rows="[[
                            'description' => $row->description,
                        ]]">
                            @interact('column_description', $row)
                                     <x-ts-textarea value="{{$row['description']}}" resize readonly/>
                            @endinteract
                        </x-ts-table>
                    @endinteract
                </x-ts-table>
            </x-ts-card>
            <x-slot:footer>
                <x-ts-button icon="check" x-on:click="$tsui.close.modal('modal-add-venue')">Done</x-ts-button>
            </x-slot:footer>
        </x-ts-modal>

        {{-- ADD SERVICE MODAL --}}
        <x-ts-modal id="modal-add-service" size="5xl">
            <x-ts-card class="p-4 max-h-200 overflow-y-auto">
                <x-ts-table expandable loading  :headers="$serviceListHeader" :rows="$serviceRow" striped  filter  paginate selectable wire:model.live='selectedService'>
                    @interact('column_rate', $row)
                        ₱ {{ number_format($row->rate->amount ?? 0, 2) }}
                    @endinteract
                    @interact('column_category',$row)
                        {{ $row->category->category_name}}
                    @endinteract
                    @interact('sub_table', $row)
                        <x-ts-table :headers="[
                            ['index'       => 'description', 'label' => 'description'],
                        ]"
                        :rows="[[
                            'description' => $row->service_description,
                        ]]">
                            @interact('column_description', $row)
                                     <x-ts-textarea value="{{$row['description']}}" resize readonly/>
                            @endinteract
                        </x-ts-table>
                    @endinteract
                </x-ts-table>
            </x-ts-card>
            <x-slot:footer>
                <x-ts-button icon="check" x-on:click="$tsui.close.modal('modal-add-service')">Done</x-ts-button>
            </x-slot:footer>
        </x-ts-modal>


        {{-- ADD FOOD MODAL --}}
        <x-ts-modal id="modal-add-food" size="5xl">
            <x-ts-card class="p-4 max-h-200 overflow-y-auto">
                <x-ts-table :$sort 
                            expandable 
                            loading  
                            :headers="$foodListHeader" 
                            :rows="$foodRow" 
                            striped  
                            filter  
                            paginate 
                            selectable 
                            wire:model.live='selectedFood' 
                            >
                    <x-slot:header>
                        <div class="lg:flex lg:justify-between mb-3 grid">
                            <div class="w-auto mb-3">
                                <span class="text-2xl"> Food Selection </span>
                            </div>
                            <div class="lg:flex gap-2 grid grid-cols-2">
                                <x-ts-select.native
                                    wire:model.live="foodType"
                                    placeholder="All Type"
                                    :options="[
                                        ['name' => 'ALA CARTE', 'id' => 'Ala carte'],
                                        ['name' => 'BANQUET', 'id' => 'Banquet'],
                                    ]"
                                    select="label:name|value:id" />
                                <div class="w-80">
                                    <x-ts-select.styled
                                        :request="route('api.active.recipe-categories', ['company_id' => auth()->user()->branch->company_id ])"
                                        select="label:category_name|value:id|description:category_description"
                                        wire:model.live="foodCategoryID"
                                        :placeholders="[
                                        'default' => 'Filter by category',
                                        'empty'   => 'No categories',
                                        ]" />
                                </div>
                            </div>
                        </div>
                    </x-slot:header>
                    @interact('column_menu_image', $row)
                        <x-ts-avatar image="{{ asset('storage/'.$row->recipe?->menu_image) }}" md text="AIR" square />
                    @endinteract
                     @interact('column_menu_name',$row)
                        {{ $row->recipe->menu_name }}
                    @endinteract 
                    @interact('column_category',$row)
                        {{ $row->recipe->category->category_name}}
                    @endinteract
                    @interact('column_rate',$row)
                        ₱ {{ number_format($row->recipe->rate->amount ?? 0, 2) }}
                    @endinteract
                    @interact('column_type',$row)
                        {{ $row->recipe->recipe_type}}
                    @endinteract
                    @interact('sub_table', $row)
                        <x-ts-table :headers="[
                            ['index'       => 'description', 'label' => 'description'],
                        ]"
                        :rows="[[
                            'description' => $row->recipe->menu_description,
                        ]]">
                            @interact('column_description', $row)
                                     <x-ts-textarea value="{{$row['description']}}" resize readonly/>
                            @endinteract
                        </x-ts-table>
                    @endinteract 
                </x-ts-table>
            </x-ts-card>
            <x-slot:footer>
                <x-ts-button icon="check" x-on:click="$tsui.close.modal('modal-add-food')">Done</x-ts-button>
            </x-slot:footer>
        </x-ts-modal>



        {{-- customer modal --}}
        <x-ts-modal id="create-customer-modal" title="Start Your Journey: Create a New Profile" size="4xl" center>
            <!-- Modal Header Icon / Subtitle -->
            <div class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <p class="text-sm text-slate-500">Provide the required details to set up the customer account.</p>
                </div>
                <div class="flex items-center space-x-2 text-slate-400">
                    <x-ts-icon name="user-circle" class="h-8 w-8 text-slate-400" />
                </div>
            </div>

            <!-- Main Two-Column Layout -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                
                <!-- Left Column: Personal Identity & Demographics -->
                <div class="space-y-6">
                    <!-- Personal Identity Card -->
                    <x-ts-card class="rounded-xl  p-4">
                        <div class="mb-4 flex items-center space-x-2 text-slate-700 font-semibold">
                            <x-ts-icon name="user" class="h-5 w-5 text-blue-600" />
                            <h3>Tell us about yourself</h3>
                        </div>

                        <div class="space-y-4">
                            <!-- First & Last Name -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-ts-input 
                                    label="First Name *" 
                                    wire:model="firstName" 
                                    placeholder="e.g. Jane" 
                                />
                                <x-ts-input 
                                    label="Last Name *" 
                                    wire:model="lastName" 
                                    placeholder="e.g. Doe" 
                                />
                            </div>

                            <!-- Middle Initial & Suffix -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <x-ts-input 
                                    label="Middle Initial" 
                                    wire:model="middleName" 
                                    placeholder="Optional" 
                                />
                                <x-ts-input 
                                    label="Suffix" 
                                    wire:model="suffix" 
                                    placeholder="Jr., Sr., III" 
                                />
                            </div>
                        </div>
                    </x-ts-card>

                    <!-- Demographic Details Card -->
                    <x-ts-card class="rounded-xl  p-4  space-y-4">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Demographic Details</h4>
                        
                        <x-ts-select.styled 
                            label="Gender Identity" 
                            wire:model="gender" 
                            :options="['Female', 'Male', 'Non-Binary', 'Prefer not to say']" 
                            placeholder="Select gender"
                        />

                        <x-ts-date 
                            label="Date of Birth" 
                            wire:model="dob" 
                            placeholder="mm/dd/yyyy"
                        />
                    </x-ts-card>
                </div>

                <!-- Right Column: Contact & Address -->
                <x-ts-card class="flex flex-col justify-between rounded-xl p-4 bordered ">
                    <x-ts-card>
                        <div class="mb-4 flex items-center space-x-2 text-slate-700 font-semibold">
                            <x-ts-icon name="envelope" class="h-5 w-5 text-blue-600" />
                            <h3>How can we reach you?</h3>
                        </div>

                        <div class="space-y-4">
                            <!-- Email -->
                            <x-ts-input 
                                label="Primary Email Address *" 
                                wire:model="customerEmail" 
                                icon="envelope" 
                                placeholder="name@company.com" 
                                hint="We will send account setup updates here."
                            />

                            <!-- Phone Number -->
                            <x-ts-input 
                                label="Preferred Phone Number *" 
                                wire:model="phone" 
                                icon="phone" 
                                placeholder="(555) 000-0000" 
                                x-mask="(999) 999-9999"
                            />

                            <!-- Address -->
                            <x-ts-textarea 
                                label="Mailing Address" 
                                wire:model="address" 
                                placeholder="Enter full street address, city, state, zip" 
                                rows="3"
                            />
                        </div>
                    </x-ts-card>

                    <!-- Security Callout Banner -->
                    <div class="mt-6 flex items-center space-x-2 rounded-lg p-3 text-xs text-emerald-800 bg-emerald-50">
                        <x-ts-icon name="shield-check" class="h-5 w-5 text-emerald-600 flex-shrink-0" />
                        <span>This information is encrypted and confidential.</span>
                    </div>
                </x-ts-card>

            </div>

            <!-- Modal Footer Actions -->
            <x-slot:footer>
                <div class="flex w-full items-center justify-between">
                    <x-ts-button flat color="secondary" x-on:click="$slideOpen = false">
                        Cancel
                    </x-ts-button>
                    
                    <x-ts-button color="primary" class="w-full sm:w-auto" wire:click="save">
                        <x-ts-icon name="check-circle" class="mr-1 h-4 w-4" />
                        Create Customer Profile
                    </x-ts-button>
                </div>
            </x-slot:footer>
        </x-ts-modal>


    </x-ts-card>
</div>