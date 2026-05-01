<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
         {{-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"> --}}

        <tallstackui:script />
        @livewireStyles
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased"
          x-cloak
          x-data="{ name: @js(auth()->user()->name),
                   avatar: @js(auth()->user()->photo_url ?? asset('assets/images/1772440510.png'))
                }"
          x-on:name-updated.window="name = $event.detail.name"
          x-bind:class="{ 'dark bg-gray-800': darkTheme, 'bg-gray-100': !darkTheme }">
    <x-layout>
        <x-slot:top>
            <x-dialog />
            <x-toast />
        </x-slot:top>
        <x-slot:header>
            <x-layout.header>
                <x-slot:right>
                    <div class="mr-10 flex items-center gap-2">
                        {{-- Bell Icon --}}
                        <x-button.circle icon="bell"
                                         flat
                                         lg
                                         color="primary"
                                         class="dark:!text-white dark:hover:!bg-white/10 dark:focus:!bg-white/10 [&>svg]:dark:!text-white" />

                        {{-- Chat Icon with Dynamic Color --}}
                        <x-button.circle icon="chat-bubble-left-right"
                                        flat
                                        lg
                                        color="primary"
                                        class="dark:!text-white dark:hover:!bg-white/10 dark:focus:!bg-white/10 [&>svg]:dark:!text-white" />
                    </div>
                    <x-dropdown>
                        <x-slot:action>
                            <button class="flex items-center gap-2 cursor-pointer hover:opacity-80 transition" x-on:click="show = !show">
                                <img :src="avatar" alt="Avatar" class="w-10 h-10 rounded-full object-cover" />
                                <span class="text-base font-semibold text-primary-500" x-text="name"></span>
                            </button>
                        </x-slot:action>
                        <x-slot:header>
                            <x-theme-switch block />
                        </x-slot:header>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown.items :text="__('Profile')" :href="route('user.profile')" />
                            <x-dropdown.items :text="__('Logout')" onclick="event.preventDefault(); this.closest('form').submit();" separator />
                        </form>
                    </x-dropdown>
                </x-slot:right>
            </x-layout.header>
        </x-slot:header>
        <x-slot:menu>
            <x-side-bar smart collapsible>
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
                <x-side-bar.item text="Dashboard" icon="home" :route="route('dashboard')" />

                <!-- Front Desk Section -->
                <x-side-bar.item text="Front Desk">
                    <x-slot:icon>
                        <x-icon-desktop-computer class="w-5 h-5" />
                    </x-slot:icon>
                </x-side-bar.item>

                <!-- Rooms & suites -->
                <x-side-bar.item text="Rooms & Suites">
                     <x-slot:icon>
                        <x-icon-door class="w-5 h-5" />
                    </x-slot:icon>
                    <x-side-bar.item text="Booking Transaction">
                         <x-slot:icon>
                                <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Restaurant -->
                <x-side-bar.item text="Restaurant" icon="building-storefront">
                     <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-chef-hat class="w-5 h-5" />
                        </x-slot:icon>
                    <x-side-bar.item text="F&B Order">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Recipe">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Menu">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Monitor">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Daily Report">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG diri --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Banquet Events -->
                <x-side-bar.item text="Events" icon="calendar">
                    <x-side-bar.item text="Event Booking">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Event Orders (BEO)">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Event Budget (BEB)">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>
                <x-side-bar.item text="Inventory">
                    <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-box class="w-5 h-5" />
                    </x-slot:icon>
                    <x-side-bar.item text="Cardex">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Purchase Order">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Receiving PO">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Item Withdrawal">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Merchandise Inventory">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Item Location">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>
                <x-side-bar.item text="Transaction" icon="arrow-path-rounded-square">
                    <x-side-bar.item text="Advances for Liquidation">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Acknowledgement">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Provisional Receipt">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Petty Cash Voucher">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Cash Return">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Cash Flow">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                    <x-side-bar.item text="Cashier Shifts">
                        <x-slot:icon>
                            {{-- Tawga ang imong custom SVG --}}
                            <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Validations Section -->
                <x-side-bar.item text="Validations" icon="check-badge">
                    <x-side-bar.item text="Purchase Order" icon="truck">
                        <x-side-bar.item text="P.O - Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="P.O Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                    <x-side-bar.item text="Item Withdrawal" icon="archive-box-x-mark">
                        <x-side-bar.item text="Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                    <x-side-bar.item text="Recipe">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-cooking-pot class="w-5 h-5" />
                        </x-slot:icon>
                        <x-side-bar.item text="Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                     <x-side-bar.item text="Event Budget (BEB)">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-chart-pie class="w-5 h-5" />
                        </x-slot:icon>
                        <x-side-bar.item text="Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                     <x-side-bar.item text="Event Liquidation">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-scale class="w-5 h-5" />
                        </x-slot:icon>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Validate">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                     <x-side-bar.item text="Equipment Request">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-item-remove class="w-5 h-5" />
                        </x-slot:icon>
                        <x-side-bar.item text="Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                    <x-side-bar.item text="Fixed Asset">
                        <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-hand-coins class="w-5 h-5" />
                        </x-slot:icon>
                        <x-side-bar.item text="Review">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                        <x-side-bar.item text="Approval">
                            <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                            </x-slot:icon>
                        </x-side-bar.item>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Accounting  -->
                <x-side-bar.item text="Accounting" icon="calculator">
                    <x-side-bar.item text="COA - Management">
                         <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Business -->
                <x-side-bar.item text="Business">
                     <x-slot:icon>
                        {{-- Tawga ang imong custom SVG --}}
                        <x-icon-store class="w-5 h-5" />
                    </x-slot:icon>
                    <x-side-bar.item text="Supplier">
                         <x-slot:icon>
                                {{-- Tawga ang imong custom SVG --}}
                                <x-icon-dot class="w-5 h-5" />
                        </x-slot:icon>
                    </x-side-bar.item>
                </x-side-bar.item>

                <!-- Data Management -->
                <x-side-bar.item text="Data Management" icon="server-stack" />

                <!-- Role Management -->
                <x-side-bar.item text="Role Management" icon="shield-check" />

                <!-- Settings -->
                <x-side-bar.item text="Settings" icon="cog-6-tooth" />

                <!-- Logout -->
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <x-side-bar.item text="Logout" icon="arrow-left-start-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                </form>
            </x-side-bar>
        </x-slot:menu>
        {{ $slot }}
    </x-layout>
    @livewireScripts
    </body>
</html>
