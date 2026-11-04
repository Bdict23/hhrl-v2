<?php

use App\Models\Suite\Room;
use App\Models\Suite\RoomCategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public string $startDate = '';
    public int $days = 7;
    public ?int $categoryId = null;
    public string $statusFilter = 'active';

    public function mount(?string $initialStart = null, ?int $initialDays = null): void
    {
        $this->startDate = $initialStart ?: request('start', today()->format('Y-m-d'));
        $this->days = $initialDays ?: (int) request('days', 7);
        $this->statusFilter = request('status', 'active');
    }

    public function previousPeriod(): void
    {
        $start = Carbon::parse($this->startDate);
        $this->startDate = $start->subDays($this->days)->format('Y-m-d');
    }

    public function nextPeriod(): void
    {
        $start = Carbon::parse($this->startDate);
        $this->startDate = $start->addDays($this->days)->format('Y-m-d');
    }

    public function today(): void
    {
        $this->startDate = today()->format('Y-m-d');
    }

    public function getDateRangeProperty(): Collection
    {
        $start = Carbon::hasFormat($this->startDate, 'Y-m-d')
            ? Carbon::parse($this->startDate)
            : today();

        return collect(range(0, $this->days - 1))
            ->map(fn ($i) => $start->copy()->addDays($i));
    }

    public function getCategoriesProperty(): EloquentCollection
    {
        return RoomCategory::where('is_active', true)->orderBy('name')->get();
    }

    public function getRoomsProperty(): EloquentCollection
    {
        $start = $this->dateRange->first()->copy()->startOfDay();
        $end = $this->dateRange->last()->copy()->endOfDay();

        return Room::with([
            'category',
            'bookings' => function ($q) use ($start, $end) {
                if ($this->statusFilter === 'active') {
                    $q->whereIn('status', ['pending', 'confirmed', 'checked_in']);
                } elseif ($this->statusFilter !== 'all') {
                    $q->where('status', $this->statusFilter);
                }

                $q->where(function ($sub) use ($start, $end) {
                    $sub->whereBetween('check_in', [$start, $end])
                        ->orWhereBetween('check_out', [$start, $end])
                        ->orWhere(fn ($nested) => $nested->where('check_in', '<=', $start)->where('check_out', '>=', $end));
                });
            },
        ])
        ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
        ->orderBy('category_id')
        ->orderBy('room_number')
        ->get();
    }
};
?>

<div>
    {{-- Controls --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="previousPeriod"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-semibold hover:bg-stone-50 transition-colors shadow-sm">
                ← Prev {{ $days }} Days
            </button>

            <button type="button" wire:click="today"
                    class="px-3 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-bold hover:bg-emerald-100 transition-colors shadow-sm">
                Today
            </button>

            <button type="button" wire:click="nextPeriod"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs font-semibold hover:bg-stone-50 transition-colors shadow-sm">
                Next {{ $days }} Days →
            </button>

            <input type="date" wire:model.live="startDate"
                   class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">

            <select wire:model.live="days"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
                <option value="7">7 Days</option>
                <option value="14">14 Days</option>
                <option value="30">30 Days</option>
            </select>

            <select wire:model.live="categoryId"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm">
                <option value="">All Categories</option>
                @foreach($this->categories as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter"
                    class="px-3 py-2 bg-white border border-stone-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm font-medium">
                <option value="active">Active (Pending, Confirmed, In-House)</option>
                <option value="all">All (incl. Checked Out & Cancelled)</option>
                <option value="pending">⏳ Pending Only</option>
                <option value="confirmed">✓ Confirmed Only</option>
                <option value="checked_in">👤 Checked In (In-House) Only</option>
                <option value="checked_out">🚪 Checked Out Only</option>
                <option value="cancelled">✕ Cancelled Only</option>
            </select>
        </div>

        <a href="{{ route('room-booking.create') }}"
           class="px-4 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-xl hover:bg-emerald-700 transition-colors shadow-sm">
            + New Walk-In
        </a>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-4 mb-4 p-4 bg-white rounded-2xl border border-stone-100 shadow-sm">
        <span class="text-xs font-semibold text-stone-500 uppercase tracking-wide">Status Legend:</span>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-emerald-50 border border-emerald-300 text-emerald-600 flex items-center justify-center text-[10px] font-bold">+</div><span class="text-xs text-stone-600">Available</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-indigo-100 border border-indigo-300"></div><span class="text-xs text-stone-600">👤 In-House (Checked In)</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-emerald-100 border border-emerald-300"></div><span class="text-xs text-stone-600">✓ Confirmed</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-amber-100 border border-amber-300"></div><span class="text-xs text-stone-600">⏳ Pending</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-stone-200 border border-stone-300"></div><span class="text-xs text-stone-600">🚪 Checked Out</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-rose-50 border border-rose-200 text-rose-500 text-[10px] flex items-center justify-center">✕</div><span class="text-xs text-stone-600">Cancelled</span></div>
        <div class="flex items-center gap-1.5"><span class="text-xs">☀️</span><span class="text-xs text-stone-600">Day Pass</span></div>
        <div class="flex items-center gap-1.5"><div class="w-3.5 h-3.5 rounded bg-stone-300 border border-stone-400"></div><span class="text-xs text-stone-600">🔧 Maintenance</span></div>
    </div>

    {{-- Matrix Grid --}}
    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse min-w-max">
                <thead>
                    <tr class="bg-stone-50 border-b border-stone-200">
                        <th class="sticky left-0 z-10 bg-stone-50 p-3 text-left font-semibold text-stone-600 min-w-40 border-r border-stone-200">
                            Room / Unit
                        </th>
                        @foreach($this->dateRange as $date)
                            <th class="p-2 text-center font-medium min-w-[72px] border-r border-stone-100 {{ $date->isToday() ? 'bg-emerald-50 text-emerald-800 font-bold' : 'text-stone-500' }}">
                                <div>{{ $date->format('D') }}</div>
                                <div class="{{ $date->isToday() ? 'text-emerald-600' : '' }}">{{ $date->format('M d') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php $currentCategory = null; @endphp
                    @foreach($this->rooms as $room)
                        @if($room->category_id !== $currentCategory)
                            @php $currentCategory = $room->category_id; @endphp
                            <tr class="bg-stone-800">
                                <td colspan="{{ $this->dateRange->count() + 1 }}" class="px-4 py-2 text-white font-bold text-xs uppercase tracking-widest">
                                    {{ $room->category?->name }}
                                </td>
                            </tr>
                        @endif

                        <tr class="border-b border-stone-100 hover:bg-stone-50/50 transition-colors">
                            <td class="sticky left-0 z-10 bg-white p-3 border-r border-stone-200">
                                <div class="font-semibold text-stone-900">{{ $room->room_number }}</div>
                                <div class="text-stone-400 text-xs flex items-center gap-1">
                                    <span>{{ $room->name }}</span>
                                    @if($room->booking_type === 'day_pass')
                                        <span class="text-[9px] bg-amber-50 text-amber-700 px-1 py-0.2 rounded font-medium">Day Pass</span>
                                    @endif
                                </div>
                            </td>

                            @foreach($this->dateRange as $date)
                                @php
                                    $bookingForDate = $room->bookings
                                        ->sortBy(fn ($b) => match ($b->status) {
                                            'checked_in' => 1,
                                            'confirmed' => 2,
                                            'pending' => 3,
                                            'checked_out' => 4,
                                            default => 5,
                                        })
                                        ->first(function ($b) use ($date) {
                                            $bIn = Carbon::parse($b->check_in)->startOfDay();
                                            $bOut = Carbon::parse($b->check_out)->startOfDay();

                                            // Day Pass or same-day booking occupies this single date
                                            if ($bIn->equalTo($bOut) || $b->booking_type === 'day_pass') {
                                                return $date->equalTo($bIn);
                                            }

                                            // Overnight stay: check_in <= date < check_out
                                            return $date->gte($bIn) && $date->lt($bOut);
                                        });

                                    $isMaintenance = in_array($room->status, ['maintenance', 'out_of_service', 'blocked']);
                                    $isHousekeeping = $room->status === 'housekeeping';
                                @endphp

                                <td class="p-1 border-r border-stone-100 text-center">
                                    @if($isMaintenance)
                                        <div class="h-9 rounded bg-stone-200 text-stone-600 flex items-center justify-center font-semibold text-[10px]" title="{{ ucfirst($room->status) }}">
                                            🔧 Mnt
                                        </div>
                                    @elseif($isHousekeeping && !$bookingForDate)
                                        <div class="h-9 rounded bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center font-semibold text-[10px]" title="Housekeeping">
                                            🧹 Clean
                                        </div>
                                    @elseif($bookingForDate)
                                        @php
                                            $status = $bookingForDate->status;
                                            $isDayPass = $bookingForDate->booking_type === 'day_pass'
                                                || Carbon::parse($bookingForDate->check_in)->isSameDay(Carbon::parse($bookingForDate->check_out));

                                            $cellBg = match($status) {
                                                'checked_in' => 'bg-indigo-100 text-indigo-900 border border-indigo-300 hover:bg-indigo-200',
                                                'confirmed' => 'bg-emerald-100 text-emerald-900 border border-emerald-300 hover:bg-emerald-200',
                                                'pending' => 'bg-amber-100 text-amber-900 border border-amber-300 hover:bg-amber-200',
                                                'checked_out' => 'bg-stone-200 text-stone-700 border border-stone-300 hover:bg-stone-300',
                                                'cancelled' => 'bg-rose-50 text-rose-500 border border-rose-200 line-through opacity-70 hover:bg-rose-100',
                                                default => 'bg-rose-100 text-rose-800 border border-rose-200 hover:bg-rose-200',
                                            };

                                            $statusIcon = match($status) {
                                                'checked_in' => '👤',
                                                'confirmed' => '✓',
                                                'pending' => '⏳',
                                                'checked_out' => '🚪',
                                                'cancelled' => '✕',
                                                default => '📅',
                                            };
                                        @endphp
                                        <a href="{{ route('room-booking.show', $bookingForDate) }}"
                                           class="h-9 rounded {{ $cellBg }} flex flex-col items-center justify-center font-medium text-[10px] p-0.5 transition-colors group block relative"
                                           title="Guest: {{ $bookingForDate->guest_name }} | Status: {{ ucfirst(str_replace('_', ' ', $status)) }} | Type: {{ $isDayPass ? 'Day Pass' : 'Overnight' }} | Code: {{ $bookingForDate->booking_code }} | Payment: {{ ucfirst($bookingForDate->payment_status) }}">
                                            <div class="flex items-center gap-0.5 truncate max-w-[65px] font-bold leading-tight">
                                                <span>{{ $statusIcon }}</span>
                                                <span class="truncate">{{ $bookingForDate->guest_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-1 text-[8px] opacity-80 leading-none mt-0.5">
                                                @if($isDayPass)
                                                    <span title="Day Pass">☀️</span>
                                                @endif
                                                @if($bookingForDate->is_walk_in)
                                                    <span class="font-semibold text-blue-700" title="Walk-In">🚶</span>
                                                @endif
                                                <span class="font-mono">{{ substr($bookingForDate->booking_code, -4) }}</span>
                                            </div>
                                        </a>
                                    @else
                                        <a href="{{ route('room-booking.create', ['room_id' => $room->id, 'check_in' => $date->format('Y-m-d')]) }}"
                                           class="h-9 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-medium transition-colors"
                                           title="Book Room {{ $room->room_number }} on {{ $date->format('M d') }}">
                                            +
                                        </a>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
