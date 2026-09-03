<?php

use App\Models\Court;
use App\Models\OperatingHour;
use App\Models\SlotOverride;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component {
    // Facility Hours
    public array $hours = [];

    // Slot Override Form
    public bool $showOverrideModal = false;
    public ?int $editingOverrideId = null;
    public ?int $courtId = null; // null = all courts
    public string $overrideDate;
    public string $startTime = '08:00';
    public string $endTime = '12:00';
    public string $overrideType = 'maintenance';
    public string $overrideTitle = '';
    public string $overrideReason = '';
    public ?float $feePerPerson = 150.00;
    public ?int $maxParticipants = 24;

    public function mount(): void
    {
        $this->overrideDate = Carbon::today()->format('Y-m-d');
        $this->loadHours();
    }

    public function loadHours(): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $existing = OperatingHour::all()->keyBy('day_of_week');

        $this->hours = [];
        foreach ($days as $day) {
            $record = $existing->get($day);

            $open = $record ? substr($record->opening_time, 0, 5) : '06:00';
            $close = $record ? substr($record->closing_time, 0, 5) : '00:00';
            if ($close === '24:00') {
                $close = '00:00';
            }

            $this->hours[$day] = [
                'id'           => $record?->id,
                'day_of_week'  => $day,
                'opening_time' => $open,
                'closing_time' => $close,
                'is_closed'    => $record ? (bool) $record->is_closed : false,
            ];
        }
    }

    public function saveOperatingHours(): void
    {
        foreach ($this->hours as $day => $data) {
             $open = ($data['opening_time'] ?? '06:00');
            $open = strlen($open) === 5 ? $open . ':00' : $open;

            $close = ($data['closing_time'] ?? '00:00');
            $close = strlen($close) === 5 ? $close . ':00' : $close;
            if ($close === '24:00:00') {
                $close = '00:00:00';
            }

            OperatingHour::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'opening_time' => $open,
                    'closing_time' => $close,
                    'is_closed'    => (bool) ($data['is_closed'] ?? false),
                ]
            );
        }

        $this->loadHours();
        $this->dispatch('hours-saved');
    }

    public function openCreateOverrideModal(): void
    {
        $this->editingOverrideId = null;
        $this->courtId = null;
        $this->overrideDate = Carbon::today()->format('Y-m-d');
        $this->startTime = '08:00';
        $this->endTime = '12:00';
        $this->overrideType = 'maintenance';
        $this->overrideTitle = '';
        $this->overrideReason = '';
        $this->feePerPerson = 150.00;
        $this->maxParticipants = 24;
        $this->showOverrideModal = true;
        $this->resetErrorBag();
    }

    public function openEditOverrideModal(int $id): void
    {
        $override = SlotOverride::findOrFail($id);
        $this->editingOverrideId = $override->id;
        $this->courtId = $override->court_id;
        $this->overrideDate = $override->date instanceof Carbon ? $override->date->format('Y-m-d') : (string) $override->date;
        $this->startTime = substr($override->start_time, 0, 5);
        $this->endTime = substr($override->end_time, 0, 5);
        $this->overrideType = $override->type;
        $this->overrideTitle = $override->title ?? '';
        $this->overrideReason = $override->reason ?? '';
        $this->feePerPerson = $override->fee_per_person ? (float) $override->fee_per_person : 150.00;
        $this->maxParticipants = $override->max_participants ?? 24;
        $this->showOverrideModal = true;
        $this->resetErrorBag();
    }

    public function closeOverrideModal(): void
    {
        $this->showOverrideModal = false;
        $this->editingOverrideId = null;
        $this->resetErrorBag();
    }

    public function saveSlotOverride(): void
    {
        $this->validate([
            'overrideDate'    => ['required', 'date'],
            'startTime'       => ['required'],
            'endTime'         => ['required'],
            'overrideType'    => ['required', 'in:open_play,maintenance,blocked,private_event,unavailable'],
            'overrideTitle'   => ['nullable', 'string', 'max:100'],
            'overrideReason'  => ['nullable', 'string', 'max:500'],
            'feePerPerson'    => ['nullable', 'numeric', 'min:0'],
            'maxParticipants' => ['nullable', 'integer', 'min:1'],
        ]);

        $start = str_contains($this->startTime, ':') && strlen($this->startTime) === 5 ? $this->startTime . ':00' : $this->startTime;
        $end = str_contains($this->endTime, ':') && strlen($this->endTime) === 5 ? $this->endTime . ':00' : $this->endTime;

        $overrideData = [
            'court_id'         => $this->courtId ? (int) $this->courtId : null,
            'date'             => $this->overrideDate,
            'start_time'       => $start,
            'end_time'         => $end,
            'type'             => $this->overrideType,
            'title'            => $this->overrideTitle ?: match ($this->overrideType) {
                'open_play'   => 'Open Play Rally',
                'maintenance' => 'Court Maintenance',
                default       => 'Slot Unavailable',
            },
            'reason'           => $this->overrideReason,
            'fee_per_person'   => $this->overrideType === 'open_play' ? $this->feePerPerson : null,
            'max_participants' => $this->overrideType === 'open_play' ? $this->maxParticipants : null,
 ];

        if ($this->editingOverrideId) {
            SlotOverride::findOrFail($this->editingOverrideId)->update($overrideData);
        } else {
            SlotOverride::create($overrideData);
        }


        $this->closeOverrideModal();
    }

    public function deleteOverride(int $id): void
    {
        SlotOverride::findOrFail($id)->delete();
    }

    public function with(): array
    {
        $courts = Court::active()->ordered()->get();
        $overrides = SlotOverride::with('court')
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return [
            'courts'    => $courts,
            'overrides' => $overrides,
            'overrideHeader' => [
                ['index' => 'date', 'label' => 'Date & Schedule'], 
                ['index' => 'court', 'label' => 'Court'], 
                ['index' => 'type', 'label' => 'Override Type'], 
                ['index' => 'title', 'label' => 'Title & Details'], 
                ['index' => 'fee_per_person', 'label' => 'Fee / Capacity'], 
                ['index' => 'action', 'label' => 'Action']],
            'overrideRows' => SlotOverride::with('court')
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
        ];
    }
}; ?>

<div class="space-y-8">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
                Operating Hours & Slot Overrides
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Manage global opening/closing hours per weekday and block custom date ranges for maintenance or Open Play events.
            </p>
        </div>
        {{-- <div>
            <button
                wire:click="openCreateOverrideModal"
                class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create Slot Override / Block</span>
            </button>
        </div> --}}
    </div>

    <!-- 1. FACILITY BASE OPERATING HOURS -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Facility Base Operating Hours (Monday – Sunday)
                </h2>
                <p class="text-xs text-slate-500">
                    Defines the public schedule matrix opening and closing time range. Note: 12:00 AM is 00:00 midnight. Overnight hours (e.g. 4 PM to 2 AM) are fully supported.
                </p>
            </div>
            <x-ts-button
                wire:click="saveOperatingHours"
                loading="saveOperatingHours"
                class="px-5 py-2 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-950 hover:bg-slate-800 dark:hover:bg-slate-100 text-xs font-black uppercase tracking-wider transition"
            >
                Save Base Hours
            </x-ts-button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            @foreach ($hours as $day => $data)
                @php
                    $openStr = $hours[$day]['opening_time'] ?? '06:00';
                    $closeStr = $hours[$day]['closing_time'] ?? '00:00';
                    
                    try {
                        $openLabel = \Carbon\Carbon::createFromFormat('H:i', $openStr)->format('g:i A');
                    } catch (\Exception $e) {
                        $openLabel = $openStr;
                    }
                    
                    try {
                        $closeLabel = \Carbon\Carbon::createFromFormat('H:i', $closeStr)->format('g:i A');
                        if ($closeStr === '00:00') {
                            $closeLabel = '12:00 AM (Midnight)';
                        }
                    } catch (\Exception $e) {
                        $closeLabel = $closeStr;
                    }
                @endphp
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-black uppercase tracking-wider text-slate-900 dark:text-white text-xs">
                            {{ ucfirst($day) }}
                        </span>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px]">
                            <input type="checkbox" wire:model.live="hours.{{ $day }}.is_closed" class="rounded border-slate-300 text-red-500 focus:ring-red-500">
                            <span class="text-red-500 font-bold">Closed</span>
                        </label>
                    </div>

                    @if (! ($hours[$day]['is_closed'] ?? false))
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-0.5">Opens</label>
                                <input
                                    type="time"
                                    wire:model="hours.{{ $day }}.opening_time"
                                    class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-semibold focus:outline-none focus:border-lime-500 text-center"
                                />
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 text-center font-medium">
                                    {{ $openLabel }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-0.5">Closes</label>
                                <input
                                    type="time"
                                    wire:model="hours.{{ $day }}.closing_time"
                                    class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-semibold focus:outline-none focus:border-lime-500 text-center"
                                />
                                <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 text-center font-medium">
                                    {{ $closeLabel }}
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="py-4 text-center text-red-400 font-semibold text-xs">
                            Facility Closed All Day
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- 2. UPCOMING SLOT OVERRIDES, OPEN PLAY & MAINTENANCE BLOCKS -->
    <x-ts-card>
        <x-slot:header>
                    <div class="p-6 border-b mb-4 border-slate-200 dark:border-slate-800 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">
                                Active & Upcoming Slot Overrides
                            </h2>
                            <p class="text-xs text-slate-500">
                                Open Play rally sessions, maintenance blocks, and private events that override standard court availability.
                            </p>
                        </div>
                    </div>
            </x-slot:header>
        <x-ts-table :headers="$overrideHeader" :rows="$overrideRows" striped persistent loading>
            @interact('column_date', $row)
                <div class="font-semibold text-slate-900 dark:text-white">
                    {{ $row->date->format('M d, Y (D)') }}
                </div>
                <div class="text-[11px] text-slate-500 font-mono">
                    {{ Carbon::createFromTimeString($row->start_time)->format('g:i A') }} – {{ Carbon::createFromTimeString($row->end_time)->format('g:i A') }}
                </div>
            @endinteract
            @interact('column_court', $row)
                <span class="font-semibold text-slate-700 dark:text-slate-300">
                    {{ $row->court ? $row->court->name : 'All Active Courts' }}
                </span>
            @endinteract
            @interact('column_type', $row)
                 @if ($row->isOpenPlay())
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-500/10 text-amber-500 border border-amber-500/30">
                        ⚡ Open Play
                    </span>
                @elseif ($row->isMaintenance())
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-red-500/10 text-red-400 border border-red-500/30">
                        🛠️ {{ ucfirst($row->type) }}
                    </span>
                @else
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-purple-500/10 text-purple-400 border border-purple-500/30">
                        {{ ucfirst($row->type) }}
                    </span>
                @endif
            @endinteract
            @interact('column_fee_per_person', $row)
                 @if ($row->isOpenPlay())
                    <div class="font-bold text-lime-500">₱{{ number_format((float)$row->fee_per_person, 2) }} / player</div>
                    <div class="text-[10px] text-slate-400">Max: {{ $row->max_participants ?? 'Unlimited' }} players</div>
                @else
                    <span class="text-slate-400">—</span>
                @endif
            @endinteract
            @interact('column_action', $row)
                <x-ts-dropdown icon="ellipsis-vertical" static lg>    
                    <x-ts-dropdown.items text="Edit" separator icon="pencil" wire:click="openEditOverrideModal({{ $row->id }})"/>
                    <x-ts-dropdown.items text="Delete" separator icon="x-mark" wire:click="deleteOverride({{ $row->id }})" wire:confirm="Are you sure you want to remove this slot override?"/>
                </x-ts-dropdown>
            @endinteract
        </x-ts-table>
    </x-ts-card>

    <!-- CREATE OVERRIDE MODAL -->
    @if ($showOverrideModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95">
                <button wire:click="closeOverrideModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    {{ $editingOverrideId ? 'Edit Slot Override / Block' : 'Create Slot Override / Block' }}
                </h3>

                <form wire:submit="saveSlotOverride" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Date *</label>
                            <input
                                type="date"
                                wire:model="overrideDate"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            />
                            @error('overrideDate') <span class="text-red-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Affected Court</label>
                            <select
                                wire:model="courtId"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                            >
                                <option value="">All Active Courts</option>
                                @foreach ($courts as $court)
                                    <option value="{{ $court->id }}">{{ $court->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Start Time *</label>
                            <input
                                type="time"
                                wire:model="startTime"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500 text-center"
                            />
                        </div>

                        <div>
                            <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">End Time *</label>
                            <input
                                type="time"
                                wire:model="endTime"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500 text-center"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Override Type *</label>
                        <select
                            wire:model.live="overrideType"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        >
                            <option value="maintenance">Maintenance (Surface, Nets, Lighting)</option>
                            <option value="open_play">⚡ Open Play Rally Event</option>
                            <option value="blocked">Blocked / Unavailable</option>
                            <option value="private_event">Private Tournament / Event</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Title / Event Name</label>
                        <input
                            type="text"
                            wire:model="overrideTitle"
                            placeholder="e.g. Morning Open Play Rally, Resurfacing"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        />
                    </div>

                    @if ($overrideType === 'open_play')
                        <div class="grid grid-cols-2 gap-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
                            <div>
                                <label class="block font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">Fee / Player (₱)</label>
                                <input
                                    type="number"
                                    step="1"
                                    wire:model="feePerPerson"
                                    class="w-full px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-1">Max Players</label>
                                <input
                                    type="number"
                                    step="1"
                                    wire:model="maxParticipants"
                                    class="w-full px-3 py-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm"
                                />
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="block font-bold uppercase tracking-wider text-slate-500 mb-1">Reason / Notes</label>
                        <textarea
                            wire:model="overrideReason"
                            rows="2"
                            placeholder="Details shown to customers on the public grid..."
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        
                            ></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeOverrideModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider cursor-pointer"
                        >
                            {{ $editingOverrideId ? 'Update Override' : 'Save Override' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <x-ts-dial lg>
        <x-ts-dial.items icon="plus" label="Create Slot Override / Block"  wire:click="openCreateModal" wire:click="openCreateOverrideModal" />
    </x-ts-dial>
</div>
