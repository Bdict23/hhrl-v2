<?php

use Livewire\Component;
use App\Models\DataManagement\Item;
use Livewire\WithPagination;
use TallStackUi\Traits\Interactions;
use App\Models\Business\Venue;
use App\Models\Business\Service;



new class extends Component
{
    use WithPagination;
    use Interactions;

    
    public ?int $quantity = 5;
    public ?string $search = null;


    // venue variables
    public  $selectedVenue = [],
            $selectedVenueRows = [],
            $grand_totalVenue = 0.00;

    // service variables
    public  $selectedService = [],
            $selectedServiceRows = [],
            $grand_totalService = 0.00;

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
                    'qty'               => 1,
                ];
            }
        }
        $this->calculateGrandTotal();

    }
    public function calculateGrandTotal()
    {
        $this->grand_totalVenue = number_format(collect($this->selectedVenueRows)->sum('sub_total'), 2 );
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

        if (isset($parts[1]) && $parts[1] === 'qty') {
            $qty = (float) ($this->selectedVenueRows[$index]['qty'] ?? 0);
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
        $this->calculateGrandTotal();

    }
    public function calculateServiceGrandTotal()
    {
        $this->grand_totalService = number_format(collect($this->selectedServiceRows)->sum('sub_total'), 2 );
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

   public function with(): array
    {
        return [
            'selectedVenueHeader' => [
                ['index' => 'venue_code', 'label' => 'Code'],
                ['index' => 'venue_name', 'label' => 'venue name'],
                ['index' => 'capacity', 'label' => 'capacity' , 'sortable' => false],
                ['index' => 'rate', 'label' => 'rate' , 'sortable' => false],
                ['index' => 'qty', 'label' => 'qty',  'sortable' => false],
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
                                    <x-ts-input label="Event Name *" class="col-span-2" />
                                    <div class="col-span-2 grid grid-cols-2 gap-6">
                                        <div class="grid col-span-1 gap-2">
                                            <x-ts-number step="5" label="Guest Count / Pax. *"/>
                                            <x-ts-select.styled searchable :options="[1,2,3]" label="Customer *">
                                                <x-slot:after>
                                                    <div class="px-2 mb-2 flex justify-center items-center">
                                                        <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                                            <span x-html="`Create user <b>${search}</b>`"></span>
                                                        </x-ts-button>
                                                    </div>
                                                </x-slot:after>
                                            </x-ts-select.styled>
                                            <x-ts-textarea maxlength="100" count label="Note" resize palceholder="(optional)"/>
                                        </div>
                                        <div class="grid col-span-1 grid-cols-2 gap-6">
                                            <div class="col-span-1 gap-2 grid">
                                                <x-ts-date format="DD [of] MMMM [of] YYYY" label="Check-in Date *"/>
                                                <x-ts-time label="Arrival Time *"/>
                                                <x-ts-select.styled
                                                    :request="route('api.active.withdrawal-reviewers', ['branch_id' => auth()->user()->branch_id ])"
                                                    select="label:fullName|value:id|description:position"
                                                    wire:model="reviewedBy"
                                                    label="Reviewed By *"
                                                    :placeholders="[
                                                    'default' => 'Select',
                                                    'empty'   => 'No reviewers found',
                                                    ]" />
                                            </div>
                                            <div class="col-span-1 gap-2 grid">
                                                <x-ts-date format="DD [of] MMMM [of] YYYY" label="Check-out Date *"/>
                                                <x-ts-time label="Departure Time *"/>
                                                <x-ts-select.styled
                                                    :request="route('api.active.withdrawal-approvers', ['branch_id' => auth()->user()->branch_id])"
                                                    wire:model="approvedBy"
                                                    select="label:fullName|value:id|description:position"
                                                    label="Approved By"
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
                                        <x-ts-button icon="plus" position="left" class="mt-2" x-on:click="$tsui.open.modal('modal-add-venue')" flat>Add Venue </x-ts-button>
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
                                    @interact('column_qty', $row)
                                    <x-ts-number sm
                                        wire:model.live.debounce.500ms="selectedVenueRows.{{ $loop->index }}.qty" />
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
                                @error('selectedVenueRows')
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
                                        <x-ts-button icon="plus" position="left" class="mt-2" x-on:click="$tsui.open.modal('modal-add-service')" flat>Add Service </x-ts-button>
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
                        Step 4 content...
                    </x-ts-step.items>
                    <x-ts-step.items step="5" title="Summary" >
                        Step 5 content... <b>finished!</b>
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
    </x-ts-card>
</div>