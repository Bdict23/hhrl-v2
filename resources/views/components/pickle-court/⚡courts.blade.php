<?php

use App\Models\Court;
use Livewire\Attributes\Layout;
use Livewire\Component;

new  class extends Component {
    public bool $showModal = false;
    public ?int $editingCourtId = null;

    public string $name = '';
    public float $hourlyRate = 350.00;
    public bool $isActive = true;
    public int $displayOrder = 1;
    public string $surfaceType = 'Pro Cushion Acrylic';
    public bool $isIndoor = false;
    public string $description = '';

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->displayOrder = (int) Court::max('display_order') + 1;
        $this->showModal = true;
    }

    public function openEditModal(int $courtId): void
    {
        $court = Court::findOrFail($courtId);
        $this->editingCourtId = $court->id;
        $this->name = $court->name;
        $this->hourlyRate = (float) $court->hourly_rate;
        $this->isActive = (bool) $court->is_active;
        $this->displayOrder = (int) $court->display_order;
        $this->surfaceType = (string) $court->surface_type;
        $this->isIndoor = (bool) $court->is_indoor;
        $this->description = (string) ($court->description ?? '');
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingCourtId = null;
        $this->name = '';
        $this->hourlyRate = 350.00;
        $this->isActive = true;
        $this->displayOrder = 1;
        $this->surfaceType = 'Pro Cushion Acrylic';
        $this->isIndoor = false;
        $this->description = '';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->validate([
            'name'         => ['required', 'string', 'max:100'],
            'hourlyRate'   => ['required', 'numeric', 'min:0'],
            'displayOrder' => ['required', 'integer', 'min:0'],
            'surfaceType'  => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
        ]);

        $data = [
            'name'          => mb_trim($this->name),
            'hourly_rate'   => $this->hourlyRate,
            'is_active'     => $this->isActive,
            'display_order' => $this->displayOrder,
            'surface_type'  => $this->surfaceType,
            'is_indoor'     => $this->isIndoor,
            'description'   => $this->description,
        ];

        if ($this->editingCourtId) {
            Court::findOrFail($this->editingCourtId)->update($data);
        } else {
            Court::create($data);
        }

        $this->closeModal();
    }

    public function toggleActive(int $courtId): void
    {
        $court = Court::findOrFail($courtId);
        $court->update(['is_active' => ! $court->is_active]);
    }

    public function moveUp(int $courtId): void
    {
        $court = Court::findOrFail($courtId);
        if ($court->display_order > 1) {
            $court->decrement('display_order');
        }
    }

    public function moveDown(int $courtId): void
    {
        $court = Court::findOrFail($courtId);
        $court->increment('display_order');
    }

    public function deleteCourt(int $courtId): void
    {
        $court = Court::findOrFail($courtId);
        $court->delete();
    }

    public function with(): array
    {
        return[
         'courtHeader' => [
                ['index' => 'display_order', 'label' => 'order'], 
                ['index' => 'name', 'label' => 'Court Name & Details'], 
                ['index' => 'surface_type', 'label' => 'Surface / Type'], 
                ['index' => 'hourly_rate', 'label' => 'Base Hourly Rate'], 
                ['index' => 'is_active', 'label' => 'Grid Status'], 
                ['index' => 'action', 'label' => 'Action']],
        'courtRows' => Court::query()
                ->orderBy('display_order')
                ->get(),
        $courts = Court::ordered()->get(),

        // return [
            'courts' => $courts,
        // ];
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Court Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Configure court details, base hourly rates, display ordering, and toggle visibility on the public booking grid.
            </p>
        </div>
    </div>

    <!-- COURTS LIST TABLE -->
    {{-- <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase font-black tracking-wider text-[10px]">
                    <tr>
                        <th class="p-4 w-16 text-center">Order</th>
                        <th class="p-4">Court Name & Details</th>
                        <th class="p-4">Surface / Type</th>
                        <th class="p-4">Base Hourly Rate</th>
                        <th class="p-4">Grid Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($courts as $court)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <!-- Display Order Controls -->
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ $court->display_order }}</span>
                                    <div class="flex flex-col">
                                        <button wire:click="moveUp({{ $court->id }})" title="Move Up" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-0.5">
                                            ▲
                                        </button>
                                        <button wire:click="moveDown({{ $court->id }})" title="Move Down" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-0.5">
                                            ▼
                                        </button>
                                    </div>
                                </div>
                            </td>

                            <!-- Court Name & Description -->
                            <td class="p-4">
                                <div class="font-black text-sm text-slate-900 dark:text-white">{{ $court->name }}</div>
                                @if ($court->description)
                                    <div class="text-[11px] text-slate-500 mt-0.5 max-w-sm">{{ $court->description }}</div>
                                @endif
                            </td>

                            <!-- Surface Type & Indoor Badge -->
                            <td class="p-4">
                                <div class="font-medium text-slate-700 dark:text-slate-300">{{ $court->surface_type }}</div>
                                @if ($court->is_indoor)
                                    <span class="mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-500">
                                        Indoor
                                    </span>
                                @else
                                    <span class="mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-400">
                                        Outdoor
                                    </span>
                                @endif
                            </td>

                            <!-- Hourly Rate -->
                            <td class="p-4">
                                <div class="font-black text-sm text-lime-500">
                                    ₱{{ number_format((float)$court->hourly_rate, 2) }} <span class="text-[10px] text-slate-400 font-normal">/ hour</span>
                                </div>
                            </td>

                            <!-- Active Status Toggle -->
                            <td class="p-4">
                                <button
                                    wire:click="toggleActive({{ $court->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition cursor-pointer {{ $court->is_active ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-400 border border-slate-300 dark:border-slate-700' }}"
                                >
                                    <span class="w-2 h-2 rounded-full {{ $court->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                    <span>{{ $court->is_active ? 'Active on Grid' : 'Hidden / Inactive' }}</span>
                                </button>
                            </td>

                            <!-- Actions -->
                            <td class="p-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        wire:click="openEditModal({{ $court->id }})"
                                        class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        wire:click="deleteCourt({{ $court->id }})"
                                        wire:confirm="Are you sure you want to delete this court?"
                                        class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 font-semibold transition"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No courts created yet. Click "Add New Court" above.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div> --}}
    <x-ts-table :headers="$courtHeader" :rows="$courtRows" striped persistent loading>
        @interact('column_display_order', $row)
            <div class="flex items-center justify-center gap-1">
                <span class="font-bold text-slate-700 dark:text-slate-300">{{ $row->display_order }}</span>
                <div class="flex flex-col">
                    <button wire:click="moveUp({{ $row->id }})" title="Move Up" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-0.5">
                        ▲
                    </button>
                    <button wire:click="moveDown({{ $row->id }})" title="Move Down" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-0.5">
                        ▼
                    </button>
                </div>
            </div>
        @endinteract
        @interact('column_name', $row)
            <div class="font-black text-sm text-slate-900 dark:text-white">{{ $row->name }}</div>
            @if ($row->description)
                <div class="text-[11px] text-slate-500 mt-0.5 max-w-sm">{{ $row->description }}</div>
            @endif
        @endinteract
        @interact('column_surface_type', $row)
            <div class="font-medium text-slate-700 dark:text-slate-300">{{ $row->surface_type }}</div>
            @if ($row->is_indoor)
                <span class="mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-500">
                    Indoor
                </span>
            @else
                <span class="mt-1 inline-block px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-400">
                    Outdoor
                </span>
            @endif
        @endinteract
        @interact('column_hourly_rate', $row)
                ₱{{ number_format((float)$row->hourly_rate, 2) }} <span class="text-[10px] text-slate-400 font-normal">/ hour</span>
        @endinteract
        @interact('column_is_active', $row)
            <button
                wire:click="toggleActive({{ $row->id }})"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold transition cursor-pointer {{ $row->is_active ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-400 border border-slate-300 dark:border-slate-700' }}"
            >
                <span class="w-2 h-2 rounded-full {{ $row->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                <span>{{ $row->is_active ? 'Active on Grid' : 'Hidden / Inactive' }}</span>
            </button>       
        @endinteract
        @interact('column_action', $row)
            <x-ts-dropdown icon="ellipsis-vertical" static lg>    
                <x-ts-dropdown.items text="Edit" icon="pencil-square" wire:click="openEditModal({{ $row->id }})"/>
                <x-ts-dropdown.items text="Delete" separator icon="x-mark" wire:click="deleteCourt({{ $row->id }})" wire:confirm="Are you sure you want to delete this court?"/>
            </x-ts-dropdown>
        @endinteract
    </x-ts-table>

    <!-- CREATE / EDIT MODAL -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95">
                <button wire:click="closeModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    {{ $editingCourtId ? 'Edit Court' : 'Create New Court' }}
                </h3>

                <form wire:submit="save" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Court Name *</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. Court 5 (VIP Indoor)"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        />
                        @error('name') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Hourly Base Rate (₱) *</label>
                            <input
                                type="number"
                                step="1"
                                wire:model="hourlyRate"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            />
                            @error('hourlyRate') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Display Order *</label>
                            <input
                                type="number"
                                step="1"
                                wire:model="displayOrder"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            />
                            @error('displayOrder') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Surface Type *</label>
                        <input
                            type="text"
                            wire:model="surfaceType"
                            placeholder="e.g. Pro Cushion Acrylic"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        />
                        @error('surfaceType') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center gap-6 py-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="isIndoor" class="rounded border-slate-300 text-lime-500 focus:ring-lime-500">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Indoor Air-Conditioned</span>
                        </label>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="isActive" class="rounded border-slate-300 text-lime-500 focus:ring-lime-500">
                            <span class="font-bold text-slate-700 dark:text-slate-300">Active on Public Grid</span>
                        </label>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Description / Amenities</label>
                        <textarea
                            wire:model="description"
                            rows="2"
                            placeholder="Court features, lighting, seating..."
                            class="w-full px-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider"
                        >
                            Save Court
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="Add New Court"  wire:click="openCreateModal" />
    </x-ts-dial>
</div>
