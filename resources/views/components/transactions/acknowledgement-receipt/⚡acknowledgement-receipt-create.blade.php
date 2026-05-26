<?php

use Livewire\Component;

new class extends Component
{
    //
    public $address;
};
?>

<div class="p-6 font-sans">
    <x-ts-breadcrumbs separator="icon:chevron-right" :items="[
                              ['label' => 'Transaction', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'archive-box' ],
                              ['label' => 'Acknowledgement Receipt Summary', 'link' => route('acknowledgement-receipt.summary'), 'icon' => 'list-bullet'],
                              ['label' => 'Create acknowledgement receipt', 'icon' => 'pencil-square'],
                  ]"  class="mb-3"/>
    <x-ts-card>
        <div class="mb-6 pb-4 border-b border-gray-100">
            <h2 class="text-xl font-bold text-gray-800 tracking-tight uppercase">Acknowledgement Receipt</h2>
        </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                <div class="grid grid-cols-9 md:col-span-12 gap-10">
                    <div class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Reference</label>
                        <div class="w-full bg-gray-50 border border-gray-200 text-gray-400 text-sm rounded-lg px-3 py-2.5 font-mono flex items-center select-none cursor-not-allowed">
                            <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            &lt;AUTO&gt;
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <x-ts-select.styled
                            :request="route('api.active.event', ['branch_id' => auth()->user()->branch_id])"
                            label="Associated Event"
                            select="label:event_name|value:id|description:reference"
                            placeholder="Select event"

                        />
                    </div>
                </div>

                <div class="md:col-span-12">
                        <x-ts-select.styled searchable
                                            :request="route('api.get.branch-customers', ['branch_id' => auth()->user()->branch_id])"
                                            label="RECEIVED FROM (SOURCE)"
                                            select="label:full_name|value:id|description:customer_address"
                                            placeholder="Select source (customer)"
                                            x-on:select="
                                                let selected = $event.detail.select;
                                                if (selected) {
                                                    $wire.address = selected.description;
                                                }
                                            "
                                            x-on:remove="$wire.address = '';"
                                            required>
                            <x-slot:after>
                                <div class="px-2 mb-2 flex justify-center items-center">
                                    <x-ts-button x-on:click="show = false; $dispatch('confirmed', { term: search })">
                                        <span x-html="`Create user <b>${search}</b>`"></span>
                                    </x-ts-button>
                                </div>
                            </x-slot:after>
                        </x-ts-select.styled>
                </div>
                <div class="md:col-span-12">
                    <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Address</label>
                    <x-ts-input  x-model="$wire.address"></x-ts-input>
                </div>

            </div>

            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center space-x-2 mb-4">
                    <span class="w-1.5 h-4 bg-emerald-700 rounded-full"></span>
                    <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">Check Details</h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Account Name</label>
                        <input type="text" placeholder="Enter account name" class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-800 transition">
                    </div>

                    <div class="md:col-span-6">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Bank</label>
                        <div class="relative">
                            <select class="w-full pl-3 pr-10 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-700 transition appearance-none cursor-pointer" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 2.5rem top 50%; background-size: 0.65rem auto;">
                                <option value="" disabled selected>Select Bank -></option>
                                <option value="bdo">BDO Unibank</option>
                                <option value="bpi">BPI</option>
                                <option value="metrobank">Metrobank</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 flex items-center px-2.5 rounded-r-lg bg-emerald-50 text-emerald-700 border-y border-r border-gray-300 pointer-events-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Check Number</label>
                        <input type="text" placeholder="Enter check number" class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-800 transition">
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Check Date</label>
                        <input type="date" class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-800 transition cursor-pointer">
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Check Status</label>
                        <select class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-700 transition appearance-none cursor-pointer" style="background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236B7280%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.75rem top 50%; background-size: 0.65rem auto;">
                            <option value="current" selected>Current</option>
                            <option value="post_dated">Post-dated</option>
                            <option value="cleared">Cleared</option>
                        </select>
                    </div>

                    <div class="md:col-span-4 flex flex-col justify-between space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Check Amount</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-500 font-medium text-sm">₱</span>
                                <input type="number" step="0.01" placeholder="0.00" class="w-full pl-7 pr-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-800 transition font-medium">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Amount in Words</label>
                            <div class="w-full bg-gray-50 border border-gray-200 text-gray-400 text-xs rounded-lg px-3 py-3 min-h-[42px] select-none italic">
                                &lt;AUTO GENERATED FROM AMOUNT&gt;
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-8">
                        <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1.5">Notes</label>
                        <textarea rows="4" placeholder="Enter optional transaction notes or internal remarks here..." class="w-full px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg focus:outline-none focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600 text-gray-800 transition resize-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-5 border-t border-gray-100 flex justify-end items-center space-x-3">
                <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-800 rounded-lg hover:bg-gray-100 transition duration-150">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-emerald-800 hover:bg-emerald-900 shadow-sm rounded-lg transition duration-150">
                    Save Receipt
                </button>
            </div>
    </x-ts-card>
</div>
