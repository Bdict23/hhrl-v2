<?php

use Livewire\Component;
use App\Models\Suite\RoomDiscount as Discount;

new class extends Component
{
    public $discounts,$createDiscountModal;
    public function mount()
    {
        $this->discounts = Discount::latest()->get();

    }
};
?>

<div>
    <x-slot:heading>Discounts & Promo Codes</x-slot:heading>
    <x-slot:subheading>Manage booking discounts and promotions</x-slot:subheading>

    <div class="flex justify-end mb-5">
        <x-ts-button wire:click="$toggle('createDiscountModal')">
            + New Discount
        </x-ts-button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-stone-100 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 border-b border-stone-200">
                <tr>
                    @foreach(['Code', 'Type', 'Value', 'Min Amount', 'Uses', 'Valid Until', 'Status', 'Actions'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-semibold text-stone-500 uppercase tracking-wide">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-50">
                @forelse($discounts as $discount)
                    <tr class="hover:bg-stone-50/50 transition-colors">
                        <td class="px-4 py-4 font-mono font-bold text-emerald-600">{{ $discount->code }}</td>
                        <td class="px-4 py-4 capitalize text-stone-600">{{ $discount->type }}</td>
                        <td class="px-4 py-4 font-semibold text-stone-900">
                            {{ $discount->type === 'percentage' ? $discount->value.'%' : '₱'.number_format($discount->value, 2) }}
                        </td>
                        <td class="px-4 py-4 text-stone-500">{{ $discount->minimum_amount ? '₱'.number_format($discount->minimum_amount, 2) : '—' }}</td>
                        <td class="px-4 py-4 text-stone-600">{{ $discount->used_count }} / {{ $discount->max_uses ?? '∞' }}</td>
                        <td class="px-4 py-4 text-stone-500">{{ $discount->valid_until?->format('M d, Y') ?? '—' }}</td>
                        <td class="px-4 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold {{ $discount->is_active && $discount->isValidNow() ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-200 text-stone-600' }}">
                                {{ $discount->is_active && $discount->isValidNow() ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-1">
                                <a href="{{ route('admin.discounts.edit', $discount) }}"
                                   class="px-2.5 py-1.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 text-xs font-medium rounded-lg transition-colors">Edit</a>
                                <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}"
                                      onsubmit="return confirm('Delete this discount?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-2.5 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium rounded-lg transition-colors">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center text-stone-400">
                            <div class="text-4xl mb-3">🏷️</div>
                            <p class="font-medium">No discounts yet</p>
                            <span
                            wire:click="$toggle('createDiscountModal')"
                            class="mt-2 inline-block text-sm text-emerald-600 hover:underline cursor-pointer">Create your first discount →</span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-ts-modal title="Create Discount" wire="createDiscountModal" size="xl" center>
        <x-slot:heading>New Discount Code</x-slot:heading>
        <x-slot:subheading>Create a promo code for bookings</x-slot:subheading>
        <div class="max-w-2xl">
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-stone-100 space-y-4 mb-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Promo Code * <span class="text-xs text-stone-400">(uppercase)</span></label>
                            <input type="text" name="code" value="{{ old('code') }}" required placeholder="e.g. SUMMER20"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase @error('code') border-red-400 @enderror"
                                oninput="this.value = this.value.toUpperCase()">
                            @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Type *</label>
                            <select name="type" x-model="type" class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₱)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Value *</label>
                            <input type="number" name="value" value="{{ old('value') }}" min="0" step="0.01" required
                                placeholder="e.g. 20 for 20% or 500 for ₱500"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Minimum Booking Amount (₱)</label>
                            <input type="number" name="minimum_amount" value="{{ old('minimum_amount') }}" min="0" step="0.01"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Max Uses</label>
                            <input type="number" name="max_uses" value="{{ old('max_uses') }}" min="1" placeholder="Leave blank for unlimited"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Valid From</label>
                            <input type="date" name="valid_from" value="{{ old('valid_from') }}"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-stone-700 mb-1.5">Valid Until</label>
                            <input type="date" name="valid_until" value="{{ old('valid_until') }}"
                                class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-stone-700 mb-1.5">Description</label>
                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Internal note about this code"
                            class="w-full px-3 py-2.5 border border-stone-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer mt-2 ">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-stone-700">Active (usable immediately)</span>
                    </label>
                </div>

                @if($errors->any())
                    <div class="p-4 bg-red-50 border border-red-200 rounded-2xl">
                        @foreach($errors->all() as $error)<p class="text-sm text-red-700">• {{ $error }}</p>@endforeach
                    </div>
                @endif

                <div class="flex justify-end">
                    <button type="submit" class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-sm transition-colors shadow-lg shadow-emerald-200">
                        Create Discount
                    </button>
                    
                </div>
        </div>
    </x-ts-modal>
</div>