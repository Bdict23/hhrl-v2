<?php

use TallStackUi\Traits\Interactions;
use Livewire\Component;
use App\Models\Suite\HousekeepingLog;
use App\Models\Suite\Room;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Services\Suite\HousekeepingService;

new class extends Component
{
    use Interactions;

    public string $filter = 'all'; // all, mine, dirty, in_progress
    public ?int $activeLogIdForNote = null;
    public string $housekeeperNote = '';
    public ?string $flashMessage = null;

    public function startCleaning(int $logId): void
    {
        $log = HousekeepingLog::find($logId);
        if ($log) {
            $user = auth()->user();
            $log->update([
                'status' => 'in_progress',
                'user_id' => $log->user_id ?: $user?->id,
                'staff_name' => $log->user_id ? $log->staff_name : ($user?->name ?? 'Housekeeper'),
            ]);
            $this->flashMessage = "Started cleaning Room {$log->room?->room_number}. Status updated to In Progress.";
        }
        $this->startCleaning($logId);
    }

    public function openConfirmModal(int $logId): void
    {
        $this->activeLogIdForNote = $logId;
        $this->housekeeperNote = '';
        $this->cleaningConfirmLog($logId);
    }

    public function closeConfirmModal(): void
    {
        $this->activeLogIdForNote = null;
        $this->housekeeperNote = '';
    }

    public function confirmClean(int $logId): void
    {
        $log = HousekeepingLog::find($logId);
        if ($log) {
            $user = auth()->user();
            $notes = $log->notes ?? '';
            if (filled($this->housekeeperNote)) {
                $notes = trim($notes.' | Housekeeper note: '.trim($this->housekeeperNote));
            }
            $notes = trim($notes.' | Confirmed clean by '.($user?->name ?? 'Housekeeper').' at '.now()->format('M d, Y h:i A'));

            $log->update([
                'status' => 'clean',
                'staff_name' => $user?->name ?? $log->staff_name,
                'user_id' => $user?->id ?? $log->user_id,
                'completed_at' => now(),
                'notes' => $notes,
            ]);

            if ($log->room && $log->room->status === 'housekeeping') {
                $log->room->update(['status' => 'available']);
            }

            $this->flashMessage = "Room {$log->room?->room_number} confirmed clean and marked as Available to occupy.";
            $this->closeConfirmModal();
        }
    }

    public function confirmRoomClean(int $roomId): void
    {
        $room = Room::find($roomId);
        if ($room) {
            $user = auth()->user();
            $notes = 'Room inspected and cleared. | Confirmed clean by '.($user?->name ?? 'Housekeeper').' at '.now()->format('M d, Y h:i A');

            HousekeepingLog::create([
                'room_id' => $room->id,
                'user_id' => $user?->id,
                'staff_name' => $user?->name ?? 'Housekeeper',
                'status' => 'clean',
                'notes' => $notes,
                'cleaning_timestamp' => now(),
                'completed_at' => now(),
            ]);

            $room->update(['status' => 'available']);
            $this->flashMessage = "Room {$room->room_number} confirmed clean and marked as Available.";
        }
    }

    public function getRoomsPendingCleaningProperty(): EloquentCollection
    {
        return Room::with(['housekeepingLogs' => fn ($q) => $q->latest()->take(1)])
            ->where('status', 'housekeeping')
            ->orderBy('room_number')
            ->get();
    }

    public function getPendingLogsProperty(): EloquentCollection
    {
        $userId = auth()->id();
        return HousekeepingLog::with(['room', 'assignedTo'])
            ->whereIn('status', ['dirty', 'in_progress'])
            ->when($this->filter === 'mine', fn ($q) => $q->where('user_id', $userId))
            ->when($this->filter === 'dirty', fn ($q) => $q->where('status', 'dirty'))
            ->when($this->filter === 'in_progress', fn ($q) => $q->where('status', 'in_progress'))
            ->latest('cleaning_timestamp')
            ->get();
    }

    public function getCompletedTodayProperty(): EloquentCollection
    {
        return HousekeepingLog::with(['room', 'assignedTo'])
            ->where('status', 'clean')
            ->whereDate('completed_at', today())
            ->latest('completed_at')
            ->take(10)
            ->get();
    }

    public function startCleaningLog(HousekeepingService $service, $id)
    {
        try {
            $user = auth()->user();

            $data = [
                'housekeeping_log' => $id,
                'user_id'   => $user->id,
                'user_name' => $user->name,
            ];
            $result = $service->startCleaning($data);
            $this->toast()->success($result)->send();

        } catch (\Exception $e) {
           \Log::error("Action Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
        
    }
    public function cleaningConfirmLog($id)
    {
        try {
            $service = app(HousekeepingService::class);
            $user = auth()->user();

            $data = [
                'housekeeping_log' => $id,
                'user_id'   => $user->id,
                'user_name' => $user->name,
            ];
            $result = $service->startCleaning($data);
            $this->toast()->success($result)->send();

        } catch (\Exception $e) {
           \Log::error("Action Failed: " . $e->getMessage());
            $this->toast()->error('Error', 'Something went wrong while saving: ' . $e->getMessage())->send();
        }
        
    }
};
?>

<div>
    {{-- Header --}}
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-stone-900 flex items-center gap-2">
                <span>🧹</span> Housekeeper Dashboard
            </h1>
            <p class="text-stone-500 mt-1 text-sm">Real-time room sanitization and readiness tracking.</p>
        </div>

        {{-- Filter tabs --}}
        <div class="flex gap-1.5 bg-stone-100 p-1.5 rounded-xl text-xs font-semibold self-start md:self-auto">
            <button type="button" wire:click="$set('filter', 'all')"
                    class="px-3 py-1.5 rounded-lg transition-colors {{ $filter === 'all' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600 hover:text-stone-900' }}">
                All Tasks
            </button>
            <button type="button" wire:click="$set('filter', 'mine')"
                    class="px-3 py-1.5 rounded-lg transition-colors {{ $filter === 'mine' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600 hover:text-stone-900' }}">
                Assigned to Me
            </button>
            <button type="button" wire:click="$set('filter', 'dirty')"
                    class="px-3 py-1.5 rounded-lg transition-colors {{ $filter === 'dirty' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600 hover:text-stone-900' }}">
                Dirty / Queued
            </button>
            <button type="button" wire:click="$set('filter', 'in_progress')"
                    class="px-3 py-1.5 rounded-lg transition-colors {{ $filter === 'in_progress' ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-600 hover:text-stone-900' }}">
                In Progress
            </button>
        </div>
    </div>

    {{-- Live Flash Notification --}}
    @if($flashMessage)
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-sm font-medium flex items-center justify-between">
            <span>✅ {{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="text-emerald-600 hover:text-emerald-900 text-xs font-bold">Dismiss</button>
        </div>
    @endif

    {{-- Session alerts from standard redirects --}}
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-sm font-medium">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- Rooms Needing Cleaning --}}
    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-stone-900">Rooms Queued for Housekeeping</h2>
                <p class="text-xs text-stone-400 mt-0.5">Rooms that checked out and must be inspected and cleared before new guests can occupy.</p>
            </div>
            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 text-xs font-bold rounded-lg">{{ $this->roomsPendingCleaning->count() }} room(s)</span>
        </div>

        @if($this->roomsPendingCleaning->isEmpty())
            <div class="px-6 py-12 text-center text-stone-400 text-sm">
                <div class="text-3xl mb-2">🎉</div>
                <p class="font-semibold text-stone-700">All rooms are clean and available!</p>
                <p class="text-xs text-stone-400 mt-1">No rooms are currently awaiting housekeeping.</p>
            </div>
        @else
            <div class="divide-y divide-stone-50">
                @foreach($this->roomsPendingCleaning as $room)
                    @php
                        $latestLog = $room->housekeepingLogs->first();
                        $isAssignedToMe = $latestLog && $latestLog->user_id === auth()->id();
                    @endphp
                    <div class="px-6 py-5 flex flex-col md:flex-row md:items-center justify-between gap-4 {{ $isAssignedToMe ? 'bg-amber-50/40' : '' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-amber-100 flex flex-col items-center justify-center text-amber-800 font-black text-sm shrink-0 border border-amber-200">
                                <span class="text-[10px] uppercase font-bold text-amber-600 leading-none">Room</span>
                                <span class="text-base leading-tight">{{ $room->room_number }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-bold text-stone-900 text-base">{{ $room->name }}</p>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                        🧹 Housekeeping
                                    </span>
                                    @if($isAssignedToMe)
                                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                            ⭐ Assigned to You
                                        </span>
                                    @elseif($latestLog && $latestLog->staff_name)
                                        <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-stone-100 text-stone-600">
                                            👤 {{ $latestLog->staff_name }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-stone-500 mt-1">
                                    @if($latestLog)
                                        <span class="text-stone-700 font-medium">Status:</span>
                                        <span class="font-semibold uppercase {{ $latestLog->status === 'in_progress' ? 'text-amber-600' : 'text-rose-600' }}">
                                            {{ str_replace('_', ' ', $latestLog->status) }}
                                        </span>
                                        · {{ $latestLog->notes ?? 'Pending cleaning' }}
                                        <span class="text-stone-400">({{ $latestLog->cleaning_timestamp?->diffForHumans() ?? 'recently' }})</span>
                                    @else
                                        <span class="text-stone-400 italic">Flagged for housekeeping</span>
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-end md:self-center">
                            @if($latestLog && $latestLog->status === 'dirty')
                                <button type="button"
                                        wire:click="startCleaning({{ $latestLog->id }})"
                                        class="px-3.5 py-2 bg-stone-100 hover:bg-stone-200 text-stone-800 font-semibold rounded-xl text-xs transition-colors border border-stone-200">
                                    ▶ Start Cleaning
                                </button>
                            @endif

                            @if($latestLog && in_array($latestLog->status, ['dirty', 'in_progress']))
                                <button type="button"
                                        wire:click="openConfirmModal({{ $latestLog->id }})"
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors shadow-sm flex items-center gap-1.5">
                                    <span>✅</span>
                                    <span>Clear & Mark Available</span>
                                </button>
                            @else
                                <button type="button"
                                        wire:click="confirmRoomClean({{ $room->id }})"
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-colors shadow-sm">
                                    ✅ Mark Clean & Available
                                </button>
                                <form method="POST" action="{{ route('housekeeper.rooms.confirm-clean', $room) }}" class="hidden">
                                    @csrf
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Active Tasks & Logs --}}
    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-stone-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-stone-900">All Active Tasks & Logs</h2>
                <p class="text-xs text-stone-400 mt-0.5">Tasks currently queued or being cleaned.</p>
            </div>
            <span class="text-xs font-semibold text-stone-400">{{ $this->pendingLogs->count() }} active</span>
        </div>

        @if($this->pendingLogs->isEmpty())
            <div class="px-6 py-8 text-center text-stone-400 text-sm">
                No active housekeeping tasks logged for this filter. ✨
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase">Room</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase">Assigned Staff</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase">Instructions / Notes</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase">Queued At</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-stone-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-50">
                        @foreach($this->pendingLogs as $log)
                            <tr class="hover:bg-stone-50/50 transition-colors {{ $log->user_id === auth()->id() ? 'bg-amber-50/20' : '' }}">
                                <td class="px-4 py-3 font-bold font-mono text-stone-900">
                                    Room {{ $log->room?->room_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if($log->user_id === auth()->id())
                                        <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded">You</span>
                                    @else
                                        <span class="text-stone-600 font-medium">{{ $log->staff_name ?? $log->assignedTo?->name ?? 'Unassigned' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $log->status === 'in_progress' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst(str_replace('_', ' ', $log->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-stone-600 max-w-xs truncate" title="{{ $log->notes }}">
                                    {{ $log->notes ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-stone-500">
                                    {{ $log->cleaning_timestamp?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        @if($log->status === 'dirty')
                                            <button type="button"
                                                    wire:click="startCleaning({{ $log->id }})"
                                                    class="px-2.5 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-700 font-medium rounded-lg text-xs transition-colors">
                                                ▶ Start
                                            </button>
                                        @endif
                                        <button type="button"
                                                wire:click="openConfirmModal({{ $log->id }})"
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg text-xs transition-colors">
                                            ✅ Confirm Clean
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Modal for Notes on Confirm --}}
    @if($activeLogIdForNote)
        <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-stone-100 space-y-4">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-stone-900 text-base">Confirm Room is Clean</h3>
                    <button type="button" wire:click="closeConfirmModal" class="text-stone-400 hover:text-stone-600 text-lg">&times;</button>
                </div>
                <p class="text-xs text-stone-500">
                    Confirming will mark this room as <strong>Available</strong>, allowing new guests to book and occupy it.
                </p>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-stone-700 mb-1">Housekeeper Notes (Optional)</label>
                        <textarea wire:model="housekeeperNote" rows="2" placeholder="e.g. Linens replaced, bathroom disinfected, room inspected."
                                  class="w-full px-3 py-2 border border-stone-200 rounded-xl text-xs focus:ring-2 focus:ring-emerald-500"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="closeConfirmModal"
                                class="px-3 py-2 bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold rounded-xl text-xs">
                            Cancel
                        </button>
                        <button type="button" wire:click="confirmClean({{ $activeLogIdForNote }})"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs">
                            ✓ Confirm Ready for Occupancy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Recently Cleared Today --}}
    @if($this->completedToday->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-stone-100">
                <h2 class="font-bold text-stone-900">Cleared Rooms Today ({{ $this->completedToday->count() }})</h2>
            </div>
            <div class="divide-y divide-stone-50">
                @foreach($this->completedToday as $cleanLog)
                    <div class="px-6 py-3 flex items-center justify-between text-xs">
                        <div>
                            <span class="font-bold text-stone-900">Room {{ $cleanLog->room?->room_number }}</span>
                            <span class="text-stone-400 mx-1">·</span>
                            <span class="text-stone-500">{{ $cleanLog->notes }}</span>
                        </div>
                        <div class="text-stone-400">
                            Cleared {{ $cleanLog->completed_at?->diffForHumans() }} by {{ $cleanLog->staff_name }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>