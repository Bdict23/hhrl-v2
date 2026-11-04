<?php

use Livewire\Component;
use App\Models\User;
use Livewire\WithPagination; 
use Livewire\Attributes\Computed;

new class extends Component
{
    use WithPagination;
    public $search = '';

    // Reset pagination when searching
    public function updatingSearch()
    {
        $this->resetPage();
    }
    #[Computed]
    public function customers()
    {
        return User::where('role', 'customer')
            ->when($this->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        // Small tip: you can use $search here instead of $this->search again
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->withCount(['roomBookings' => fn ($q) => $q->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])])
            ->withSum(['roomBookings' => fn ($q) => $q->where('payment_status', 'paid')], 'total_price')
            ->paginate(20);
    }
};
?>

<div>
    <x-slot:heading>Customer History</x-slot:heading>
    <x-slot:subheading>Guest profiles, stay records, and lifetime value</x-slot:subheading>

    {{-- Search & Stats Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Search by name, email, or phone..."
                   class="w-full px-4 py-2.5 bg-white border border-stone-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500">
            <button class="px-5 py-2.5 bg-stone-900 text-white rounded-xl text-sm font-semibold hover:bg-stone-800 transition-colors">
                Search
            </button>
            @if($search)
                <a href="" class="text-xs text-stone-500 hover:text-stone-800">Clear</a>
            @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 border-b border-stone-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Contact</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-stone-500 uppercase tracking-wide">Stays / Bookings</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-stone-500 uppercase tracking-wide">Total Paid</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">Last Visit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-stone-500 uppercase tracking-wide">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50">
                    @forelse($this->customers as $customer)
                        <tr class="hover:bg-stone-50/50 transition-colors">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-800 font-bold text-sm flex items-center justify-center">
                                        {{ substr($customer->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-stone-900">{{ $customer->name }}</div>
                                        <div class="text-xs text-stone-400">Joined {{ $customer->created_at->format('M Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-xs">
                                <div class="text-stone-900">{{ $customer->email }}</div>
                                <div class="text-stone-500">{{ $customer->phone_number ?? 'No phone' }}</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-semibold rounded-lg text-xs">
                                    {{ $customer->bookings_count }} bookings
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right font-semibold text-stone-900">
                                ₱{{ number_format((float) ($customer->bookings_sum_total_price ?? 0), 2) }}
                            </td>
                            <td class="px-4 py-4 text-xs text-stone-600">
                                {{ $customer->last_visited_at ? $customer->last_visited_at->format('M d, Y') : ($customer->visit_count > 0 ? "{$customer->visit_count} visits" : '—') }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a 
                                href="{{ route('suite.customer.history-show', $customer) }}"
                                   class="px-3 py-1.5 bg-stone-100 hover:bg-stone-200 text-stone-800 text-xs font-semibold rounded-lg transition-colors">
                                    View History →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-stone-400 text-sm">
                                No customer records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->customers->hasPages())
            <div class="px-4 py-3 border-t border-stone-100">{{ $this->customers->links() }}</div>
        @endif
    </div>

</div>