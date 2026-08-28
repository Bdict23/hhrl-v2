<?php

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Component;

new  class extends Component {
    public Booking $booking;

    public function mount(string $referenceCode): void
    {
        $this->booking = Booking::with(['slots.court'])
            ->whereRaw('LOWER(reference_code) = ?', [strtolower($referenceCode)])
            ->firstOrFail();
    }
}; ?>

<div class="max-w-2xl mx-auto my-4 sm:my-10 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-5 sm:p-10 shadow-xl text-slate-900 dark:text-white print:bg-white print:text-slate-950 print:border-none print:shadow-none print:p-0 transition-colors duration-200">
    <!-- PRINT BUTTON & CONTROLS -->
    <div class="flex items-center justify-between pb-4 sm:pb-6 mb-4 sm:mb-6 border-b border-slate-200 dark:border-slate-800 print:hidden">
        <a href="{{ route('welcome') }}" class="text-xs font-bold text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white flex items-center gap-1.5 transition">
            ← Back to Schedule Grid
        </a>
        <button
            onclick="window.print()"
            class="px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 text-white text-xs font-black uppercase tracking-wider transition flex items-center gap-2 shadow-md cursor-pointer border border-slate-700"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Print Receipt</span>
        </button>
    </div>

    <!-- RECEIPT HEADER -->
    <div class="text-center pb-6 sm:pb-8 border-b-2 border-dashed border-slate-200 dark:border-slate-800">
        <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-lime-400 text-slate-950 text-2xl font-black mb-3 shadow-md">
            🎾
        </div>
        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white uppercase print:text-black">
            LYR Pickleball Club
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5 print:text-slate-600">
            Tagum City, Davao del Norte • Contact: 0917-888-9999
        </p>
        <div class="mt-4 inline-block px-4 py-1 rounded-full bg-slate-100 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 print:bg-slate-100 print:text-black">
            Official Booking Voucher
        </div>
    </div>

    <!-- VOUCHER CODE BAR -->
    <div class="my-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left print:bg-slate-50">
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500">Reference Number</span>
            <div class="text-2xl font-black font-mono tracking-wider text-slate-900 dark:text-white print:text-black">
                {{ $booking->reference_code }}
            </div>
        </div>
        <div>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 block sm:text-right">Status</span>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider {{ $booking->status === 'confirmed' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30' : ($booking->status === 'pending' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30' : 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/30') }}">
                {{ $booking->status }} ({{ $booking->payment_status }})
            </span>
        </div>
    </div>

    <!-- CUSTOMER & BOOKING INFO -->
    <div class="grid grid-cols-2 gap-4 text-xs py-4 border-b border-slate-100 dark:border-slate-800/80">
        <div>
            <span class="text-slate-400 dark:text-slate-500 block font-medium">Customer Name</span>
            <span class="font-bold text-slate-900 dark:text-white text-sm print:text-black">{{ $booking->customer_name }}</span>
        </div>
        <div>
            <span class="text-slate-400 dark:text-slate-500 block font-medium">Contact Phone</span>
            <span class="font-bold text-slate-900 dark:text-white text-sm font-mono print:text-black">{{ $booking->customer_phone }}</span>
        </div>
        <div>
            <span class="text-slate-400 dark:text-slate-500 block font-medium">Email Address</span>
            <span class="font-medium text-slate-700 dark:text-slate-300 truncate print:text-black">{{ $booking->customer_email }}</span>
        </div>
        <div>
            <span class="text-slate-400 dark:text-slate-500 block font-medium">Date Issued</span>
            <span class="font-medium text-slate-700 dark:text-slate-300 print:text-black">{{ $booking->created_at->format('M d, Y h:i A') }}</span>
        </div>
    </div>

    <!-- RESERVED SLOTS TABLE -->
    <div class="py-6 border-b border-slate-200 dark:border-slate-800">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">
            Reserved Schedule Details
        </h3>
        <table class="w-full text-xs">
            <thead>
                <tr class="text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold border-b border-slate-200 dark:border-slate-800">
                    <th class="text-left pb-2">Court</th>
                    <th class="text-left pb-2">Date & Time</th>
                    <th class="text-right pb-2">Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                @foreach ($booking->slots as $slot)
                    <tr class="py-2.5">
                        <td class="py-2.5 font-bold text-slate-900 dark:text-white print:text-black">{{ $slot->court->name }}</td>
                        <td class="py-2.5 text-slate-600 dark:text-slate-300 print:text-black">
                            {{ $slot->date->format('M d, Y') }} • {{ \Carbon\Carbon::createFromTimeString($slot->start_time)->format('g:i A') }} - {{ \Carbon\Carbon::createFromTimeString($slot->end_time)->format('g:i A') }}
                        </td>
                        <td class="py-2.5 text-right font-black text-slate-900 dark:text-white print:text-black">₱{{ number_format((float)$slot->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- TOTAL AMOUNT -->
    <div class="pt-6 space-y-2 text-xs">
        <div class="flex justify-between text-slate-500 dark:text-slate-400">
            <span>Subtotal:</span>
            <span>₱{{ number_format((float)$booking->subtotal_amount, 2) }}</span>
        </div>
        @if ((float)$booking->discount_or_surcharge != 0)
            <div class="flex justify-between text-amber-700 dark:text-amber-400">
                <span>Peak Hour / Surcharge:</span>
                <span>₱{{ number_format((float)$booking->discount_or_surcharge, 2) }}</span>
            </div>
        @endif
        <div class="flex justify-between text-slate-500 dark:text-slate-400">
            <span>Payment Method:</span>
            <span class="uppercase font-bold text-slate-900 dark:text-white print:text-black">{{ $booking->payment_method }}</span>
        </div>
        @if ($booking->proof_of_payment_url)
            <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs print:hidden">
                <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-400 font-semibold">
                    <span>📎 Proof of Payment Attached</span>
                </div>
                <a
                    href="{{ $booking->proof_of_payment_url }}"
                    target="_blank"
                    class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs uppercase tracking-wider transition flex items-center gap-1.5 shadow-xs"
                >
                    <span>View Receipt Photo</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        @endif
        <div class="pt-3 border-t border-slate-300 dark:border-slate-800 flex justify-between items-baseline text-base font-black text-slate-950 dark:text-white print:text-black">
            <span>Grand Total:</span>
            <span class="text-2xl text-lime-600 dark:text-lime-400 font-black">₱{{ number_format((float)$booking->total_amount, 2) }}</span>
        </div>
    </div>

    <!-- VENUE POLICY & NOTES -->
    <div class="mt-8 p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 space-y-1 print:bg-slate-50">
        <div class="font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider text-[10px]">Club Rules & Check-in:</div>
        <div>• Please arrive 10-15 minutes prior to your scheduled time slot.</div>
        <div>• Non-marking court shoes are strictly required on all playing surfaces.</div>
        <div>• Present this voucher reference code (<strong class="font-mono text-slate-900 dark:text-white">{{ $booking->reference_code }}</strong>) at the reception desk upon arrival.</div>
    </div>
</div>
