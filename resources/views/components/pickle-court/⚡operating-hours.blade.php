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
    public ?int $courtId = null; // null = all courts
    public string $overrideDate;
    public string $startTime = '08:00:00';
    public string $endTime = '12:00:00';
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
            $this->hours[$day] = [
                'id'           => $record?->id,
                'day_of_week'  => $day,
                'opening_time' => $record ? substr($record->opening_time, 0, 5) : '06:00',
                'closing_time' => $record ? substr($record->closing_time, 0, 5) : '24:00',
                'is_closed'    => $record ? (bool) $record->is_closed : false,
            ];
        }
    }

    public function saveOperatingHours(): void
    {
        foreach ($this->hours as $day => $data) {
            $open = $data['opening_time'] . ':00';
            $close = ($data['closing_time'] === '24:00' || $data['closing_time'] === '00:00') ? '24:00:00' : $data['closing_time'] . ':00';

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
        $this->courtId = null;
        $this->overrideDate = Carbon::today()->format('Y-m-d');
        $this->startTime = '08:00:00';
        $this->endTime = '12:00:00';
        $this->overrideType = 'maintenance';
        $this->overrideTitle = '';
        $this->overrideReason = '';
        $this->feePerPerson = 150.00;
        $this->maxParticipants = 24;
        $this->showOverrideModal = true;
    }

    public function closeOverrideModal(): void
    {
        $this->showOverrideModal = false;
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

        SlotOverride::create([
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
        ]);

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
        <div>
            <button
                wire:click="openCreateOverrideModal"
                class="px-5 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg shadow-lime-500/20 flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Create Slot Override / Block</span>
            </button>
        </div>
    </div>

    <!-- 1. FACILITY BASE OPERATING HOURS -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Facility Base Operating Hours (Monday – Sunday)
                </h2>
                <p class="text-xs text-slate-500">
                    Defines the public schedule matrix opening and closing time range.
                </p>
            </div>
            <button
                wire:click="saveOperatingHours"
                class="px-5 py-2 rounded-xl bg-slate-900 dark:bg-white text-white dark:text-slate-950 hover:bg-slate-800 dark:hover:bg-slate-100 text-xs font-black uppercase tracking-wider transition"
            >
                Save Base Hours
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            @foreach ($hours as $day => $data)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-black uppercase tracking-wider text-slate-900 dark:text-white text-xs">
                            {{ ucfirst($day) }}
                        </span>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px]">
                            <input type="checkbox" wire:model="hours.{{ $day }}.is_closed" class="rounded border-slate-300 text-red-500 focus:ring-red-500">
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
                            </div>
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-slate-400 mb-0.5">Closes</label>
                                <input
                                    type="time"
                                    wire:model="hours.{{ $day }}.closing_time"
                                    class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-semibold focus:outline-none focus:border-lime-500 text-center"
                                />
                            </div>
                        </div>
                    @else
                        <div class="py-2 text-center text-red-400 font-semibold text-xs">
                            Facility Closed All Day
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- 2. UPCOMING SLOT OVERRIDES, OPEN PLAY & MAINTENANCE BLOCKS -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
        <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white uppercase tracking-tight">
                    Active & Upcoming Slot Overrides
                </h2>
                <p class="text-xs text-slate-500">
                    Open Play rally sessions, maintenance blocks, and private events that override standard court availability.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 text-slate-500 uppercase font-black tracking-wider text-[10px]">
                    <tr>
                        <th class="p-4">Date & Schedule</th>
                        <th class="p-4">Court</th>
                        <th class="p-4">Override Type</th>
                        <th class="p-4">Title & Details</th>
                        <th class="p-4">Fee / Capacity</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($overrides as $o)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition">
                            <td class="p-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ $o->date->format('M d, Y (D)') }}
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono">
                                    {{ \Carbon\Carbon::createFromTimeString($o->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::createFromTimeString($o->end_time)->format('g:i A') }}
                                </div>
                            </td>

                            <td class="p-4">
                                <span class="font-bold text-slate-700 dark:text-slate-300">
                                    {{ $o->court ? $o->court->name : 'All Active Courts' }}
                                </span>
                            </td>

                            <td class="p-4">
                                @if ($o->isOpenPlay())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-500/10 text-amber-500 border border-amber-500/30">
                                        ⚡ Open Play
                                    </span>
                                @elseif ($o->isMaintenance())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-red-500/10 text-red-400 border border-red-500/30">
                                        🛠️ {{ ucfirst($o->type) }}
                                    </span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-purple-500/10 text-purple-400 border border-purple-500/30">
                                        {{ ucfirst($o->type) }}
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $o->title }}</div>
                                @if ($o->reason)
                                    <div class="text-[11px] text-slate-500 italic max-w-sm">{{ $o->reason }}</div>
                                @endif
                            </td>

                            <td class="p-4">
                                @if ($o->isOpenPlay())
                                    <div class="font-bold text-lime-500">₱{{ number_format((float)$o->fee_per_person, 2) }} / player</div>
                                    <div class="text-[10px] text-slate-400">Max: {{ $o->max_participants ?? 'Unlimited' }} players</div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <button
                                    wire:click="deleteOverride({{ $o->id }})"
                                    wire:confirm="Are you sure you want to remove this slot override?"
                                    class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-500 font-semibold transition"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400">
                                No active slot overrides or maintenance blocks found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- CREATE OVERRIDE MODAL -->
    @if ($showOverrideModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl text-slate-900 dark:text-white relative animate-in zoom-in-95">
                <button wire:click="closeOverrideModal" class="absolute top-6 right-6 text-slate-400 hover:text-slate-600 dark:hover:text-white p-2">✕</button>

                <h3 class="text-xl font-black uppercase tracking-tight mb-4">
                    Create Slot Override / Block
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
                            class="w-full px-4 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-sm focus:outline-none focus:border-lime-500"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeOverrideModal"
                            class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-xl bg-lime-500 hover:bg-lime-400 text-slate-950 font-black uppercase tracking-wider"
                        >
                            Save Override
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
