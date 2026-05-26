<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased"
          x-cloak
          x-data="{ name: @js(auth()->user()->name),
                   avatar: @js(asset(auth()->user()->photo_url) ?? asset('/images/1772440510.png')),
                }"
          x-bind:class="{ 'dark bg-gray-800': darkTheme, 'bg-gray-100': !darkTheme }">
    {{-- <div x-show="loading" class="fixed top-0 left-0 right-0 z-[999] h-1 pointer-events-none" style="display: none;"> --}}

    </div>
    <x-ts-layout>
        <x-slot:top>
            <x-ts-dialog />
            <x-ts-toast />
        </x-slot:top>
        <x-slot:header>
            <x-ts-layout.header>
                <x-slot:right>
                    <div class="mr-10 flex items-center gap-2">
                        {{-- Bell Icon --}}
                        <x-ts-button.circle icon="bell"
                                         flat
                                         lg
                                         color="primary"
                                         class="dark:!text-white dark:hover:!bg-white/10 dark:focus:!bg-white/10 [&>svg]:dark:!text-white" />

                        {{-- Chat Icon with Dynamic Color --}}
                        <x-ts-button.circle icon="chat-bubble-left-right"
                                        flat
                                        lg
                                        color="primary"
                                        class="dark:!text-white dark:hover:!bg-white/10 dark:focus:!bg-white/10 [&>svg]:dark:!text-white" />
                    </div>
                    <x-ts-dropdown>
                        <x-slot:action>
                            <button class="flex items-center gap-2 cursor-pointer hover:opacity-80 transition" x-on:click="show = !show">
                                <img :src="avatar" alt="Avatar" class="w-10 h-10 rounded-full object-cover" />
                                <span class="text-base font-semibold text-primary-500" x-text="name"></span>
                            </button>
                        </x-slot:action>
                        <x-slot:header>
                            <x-ts-theme-switch block />
                        </x-slot:header>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ts-dropdown.items :text="__('Profile')" :href="route('user.profile')" />
                            <x-ts-dropdown.items :text="__('Logout')" onclick="event.preventDefault(); this.closest('form').submit();" separator />
                        </form>
                    </x-ts-dropdown>
                </x-slot:right>
            </x-ts-layout.header>
        </x-slot:header>
        <x-slot:menu>
            @persist('side-bar')
                <x-ts-side-bar smart collapsible>
                    <x-slot:brand>
                        <div class="my-4 flex items-center justify-center">
                            <img src="{{ asset('assets/images/1772440510.png') }}" width="80" height="80" />
                        </div>
                    </x-slot:brand>
                    <x-slot:brand-collapsed>
                        <div class="my-4 flex items-center justify-center">
                            <img src="{{ asset('assets/images/1772440510.png') }}" width="40" height="40" />
                        </div>
                    </x-slot:brand-collapsed>

                    <!-- Dashboard -->
                    <x-ts-side-bar.item text="Dashboard" icon="home" :route="route('dashboard')" />

                    <!-- Front Desk Section -->
                    <x-ts-side-bar.item text="Front Desk">
                        <x-slot:icon>
                            <x-icon-desktop-computer class="w-5 h-5" />
                        </x-slot:icon>
                    </x-ts-side-bar.item>

                    <!-- Rooms & suites -->
                    <x-ts-side-bar.item text="Rooms & Suites">
                        <x-slot:icon>
                            <x-icon-door class="w-5 h-5" />
                        </x-slot:icon>
                        <x-ts-side-bar.item text="Booking Transaction">
                            <x-slot:icon>
                                    <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>

                    <!-- Restaurant -->
                    <x-ts-side-bar.item text="Restaurant" icon="building-storefront">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-chef-hat class="w-5 h-5" />
                            </x-slot:icon>
                        <x-ts-side-bar.item text="F&B Order">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Recipe">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Menu">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Monitor">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Daily Report">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG diri --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>

                    <!-- Banquet Events -->
                    <x-ts-side-bar.item text="Events" icon="calendar">
                        <x-ts-side-bar.item text="Event Booking">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Event Orders (BEO)">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Event Budget (BEB)">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>
                    <x-ts-side-bar.item text="Inventory">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-box class="w-5 h-5" />
                        </x-slot:icon>
                        <x-ts-side-bar.item text="Cardex" x-on:click="$tsui.open.modal('modal-cardex')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Purchase Order" :route="route('purchase-order-summary')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Receiving PO" :route="route('receiving-summary')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Item Withdrawal">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Merchandise Inventory">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Item Location">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Fixed Asset" :route="route('fixed-asset.menu')">
                            <x-slot:icon>
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>
                    <x-ts-side-bar.item text="Transaction" icon="arrow-path-rounded-square">
                        <x-ts-side-bar.item text="Advances for Liquidation" :route="route('afl.summary')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Acknowledgement" :route="route('acknowledgement-receipt.summary')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Provisional Receipt">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Petty Cash Voucher" :route="route('petty-cash-voucher.summary')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Cash Return" :route="route('cash-return.summary-tab')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                         <x-ts-side-bar.item text="Revolving Fund" :route="route('revolving-fund.overview')">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Cash Flow">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                        <x-ts-side-bar.item text="Cashier Shifts">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>

                    <!-- Validations Section -->
                    <x-ts-side-bar.item text="Validations" icon="check-badge">

                            <x-ts-side-bar.item text="Purchase Order">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>


                            <x-ts-side-bar.item text="Item Withdrawal">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>


                            <x-ts-side-bar.item text="Recipe">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>


                            <x-ts-side-bar.item text="Event Budget (BEB)">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>

                            <x-ts-side-bar.item text="Event Liquidation">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>


                            <x-ts-side-bar.item text="Equipment Request">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>

                            <x-ts-side-bar.item text="Fixed Asset" :route="route('fixed-asset.validation-summary')">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>

                            <x-ts-side-bar.item text="COA Template">
                                <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>

                    </x-ts-side-bar.item>

                    <!-- Accounting  -->
                    <x-ts-side-bar.item text="Accounting" icon="calculator">
                        <x-ts-side-bar.item text="COA - Management">
                            <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>

                    <!-- Business -->
                    <x-ts-side-bar.item text="Business">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-store class="w-5 h-5" />
                        </x-slot:icon>
                        <x-ts-side-bar.item text="Supplier">
                            <x-slot:icon>
                                    {{-- Tawga ang imong custom SVG --}}
                                    <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    </x-ts-side-bar.item>

                    <!-- Data Management -->
                    <x-ts-side-bar.item text="Data Management" icon="server-stack" />

                    <!-- Role Management -->
                    <x-ts-side-bar.item text="Role Management" icon="shield-check" />

                    <!-- Settings -->
                    <x-ts-side-bar.item text="Settings" icon="cog-6-tooth" />


                    <!-- Logout -->
                    {{-- <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <x-ts-side-bar.item text="Logout" icon="arrow-left-start-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                    </form> --}}
                    <x-slot:footer>
                        <p class="text-sm text-gray-500">v1.0.0</p>
                    </x-slot:footer>
                </x-ts-side-bar>
            @endpersist
        </x-slot:menu>
        {{ $slot }}

        <livewire:inventory.cardex />
    </x-ts-layout>
    @livewireScripts
    </body>
</html>
