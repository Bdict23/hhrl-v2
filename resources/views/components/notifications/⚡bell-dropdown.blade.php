<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;

new class extends Component
{
    #[On('echo-private:hhrl-channel,.new-notification')]
    #[On('echo-private:hhrl-channel,.update-booking')]
    #[On('echo-private:hhrl-channel,PickleBooking')]
    public function handleRealtimeUpdate($event = null): void
    {
        // Re-renders and re-evaluates pendingCount & notifications in real time
    }

    public function markAllAsRead(): void
    {
        $user = Auth::user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
        }
    }

    public function openNotification(string $notificationId)
    {
        $user = Auth::user();
        if ($user) {
            $notification = $user->notifications()->find($notificationId);
            if ($notification) {
                $notification->markAsRead();
                $url = $notification->data['url'] ?? route('admin.dashboard');
                return $this->redirect($url, navigate: true);
            }
        }

        return $this->redirect(route('admin.dashboard'), navigate: true);
    }

    public function with(): array
    {
        $user = Auth::user();

        // Badge counter strictly reflects pending approvals: only decrements when approved or declined
        $pendingCount = Booking::query()
            ->where('status', 'pending')
            ->count();

        $notifications = $user ? $user->notifications()->latest()->take(15)->get() : collect();

        return [
            'pendingCount'  => $pendingCount,
            'notifications' => $notifications,
        ];
    }
};
?>

<div class="relative z-50"
     x-data="{ open: false }"
     x-on:click.outside="open = false"
     wire:key="navbar-bell-notifications">

    {{-- Trigger Button: Clicking only toggles dropdown, DOES NOT remove or decrement badge --}}
    <button type="button"
            x-on:click="open = !open"
            aria-label="Notifications"
            class="relative p-1.5 sm:p-2 rounded-full cursor-pointer duration-200 transition-colors
                   text-primary-600 hover:text-primary-700 hover:bg-primary-50
                   dark:text-primary-400 dark:hover:text-white dark:hover:bg-white/10
                   focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">

        <x-icon-pickleball class="w-7 h-7 sm:w-8 sm:h-8" />

        {{-- Dynamic Badge Counter: Only decrements when a booking is approved/declined --}}
        @if ($pendingCount > 0)
            <span class="absolute top-0.5 right-0.5 sm:top-1 sm:right-1 flex items-center justify-center min-w-4.5 h-4.5 sm:min-w-5 sm:h-5 px-1
                         text-[9px] sm:text-[10px] font-bold text-white bg-red-500 rounded-full border-2 border-white
                         dark:border-gray-800 animate-pulse shadow-sm">
                {{ $pendingCount > 99 ? '99+' : $pendingCount }}
            </span>
        @endif
    </button>

    {{-- Mobile Backdrop to close on tap outside and prevent overflow confusion --}}
    <div x-show="open"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-on:click="open = false"
         x-cloak
         class="fixed inset-0 bg-black/25 backdrop-blur-[2px] sm:hidden z-40">
    </div>

    {{-- Notifications Dropdown Menu: Fully responsive for mobile & desktop --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
         x-cloak
         class="fixed inset-x-2 top-16 sm:inset-auto sm:absolute sm:right-0 sm:top-full sm:mt-2
                w-auto sm:w-96 sm:max-w-md max-h-[82vh] sm:max-h-[520px]
                rounded-2xl shadow-2xl border
                bg-white dark:bg-gray-900 border-gray-100 dark:border-gray-800
                flex flex-col overflow-hidden z-50">

        {{-- Dropdown Header --}}
        <div class="flex-shrink-0 px-4 py-3 border-b border-gray-100 dark:border-gray-800/80 bg-gray-50/70 dark:bg-gray-800/50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-bold text-sm text-gray-800 dark:text-gray-100">Notifications</span>
                @if ($pendingCount > 0)
                    <span class="px-2 py-0.5 text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 rounded-full">
                        {{ $pendingCount }} pending approval
                    </span>
                @endif
            </div>
            @if ($notifications->isNotEmpty())
                <button type="button"
                        wire:click="markAllAsRead"
                        class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline cursor-pointer">
                    Mark all read
                </button>
            @endif
        </div>

        {{-- Notification Items List --}}
        <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/60 thin-scroll">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $type = $data['type'] ?? 'system_alert';
                    $title = $data['title'] ?? 'Notification';
                    $message = $data['message'] ?? '';
                    $referenceCode = $data['reference_code'] ?? null;
                    $isUnread = is_null($notification->read_at);

                    $badgeStyles = match($type) {
                        'pending_payment' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                        'payment_approved' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                        'cancellation_requested' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
                        'court_updated' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400 border-sky-200 dark:border-sky-800',
                        default => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
                    };

                    $icon = match($type) {
                        'pending_payment' => 'clock',
                        'payment_approved' => 'check-circle',
                        'cancellation_requested' => 'x-circle',
                        'court_updated' => 'calendar',
                        default => 'bell',
                    };
                @endphp

                <div wire:key="notif-{{ $notification->id }}"
                     wire:click="openNotification('{{ $notification->id }}')"
                     class="p-3 sm:p-3.5 hover:bg-gray-50 dark:hover:bg-gray-800/60 cursor-pointer transition flex items-start gap-3 relative group {{ $isUnread ? 'bg-primary-50/30 dark:bg-primary-950/20' : '' }}">

                    {{-- Type Icon Badge --}}
                    <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full border flex items-center justify-center {{ $badgeStyles }}">
                        <x-ts-icon :name="$icon" class="w-4 h-4" />
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-1">
                            <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 break-words">
                                    {{ $title }}
                                </p>
                                @if ($referenceCode)
                                    <span class="inline-block px-1.5 py-0.5 rounded font-mono font-bold text-[10px] bg-slate-100 dark:bg-slate-800 text-primary-600 dark:text-primary-400 border border-slate-200 dark:border-slate-700 flex-shrink-0">
                                        {{ $referenceCode }}
                                    </span>
                                @endif
                            </div>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap flex-shrink-0 ml-1">
                                {{ $notification->created_at->diffForHumans(null, true, true) }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 leading-relaxed break-words">
                            {{ $message }}
                        </p>
                    </div>

                    {{-- Unread Dot --}}
                    @if ($isUnread)
                        <span class="w-2 h-2 rounded-full bg-primary-500 self-center flex-shrink-0"></span>
                    @endif
                </div>
            @empty
                <div class="py-12 px-4 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 mb-3">
                        <x-icon-pickleball class="w-6 h-6" />
                    </div>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No notifications yet</p>
                    <p class="text-xs text-gray-400 mt-1">We'll alert you when approval events occur.</p>
                </div>
            @endforelse
        </div>

        {{-- Dropdown Footer --}}
        @if ($notifications->isNotEmpty())
            <div class="flex-shrink-0 px-4 py-2.5 bg-gray-50/70 dark:bg-gray-800/40 border-t border-gray-100 dark:border-gray-800 text-center">
                <a href="{{ route('admin.dashboard') }}"
                   wire:navigate
                   x-on:click="open = false"
                   class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:text-primary-700">
                    Go to Court Dashboard &rarr;
                </a>
            </div>
        @endif
    </div>
</div>
