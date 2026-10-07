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

    <x-ts-layout>
        
        <x-slot:top>
            <x-ts-dialog />
            <x-ts-toast />
        </x-slot:top>
        <x-slot:header>
            <x-ts-layout.header class="print:hidden">
                <x-slot:left>
                    <livewire:switch-branch />
                </x-slot:left>
                <x-slot:right>
                    <div class="mr-1 sm:mr-4 lg:mr-8 flex items-center gap-1.5 sm:gap-2">
                        {{-- Real-time Bell Notifications Dropdown --}}
                        <livewire:notifications.bell-dropdown />

                        {{-- Chat Icon with Dynamic Color --}}
                        <x-ts-button.circle icon="chat-bubble-left-right"
                                        flat
                                        lg
                                        color="primary"
                                        class="dark:!text-white dark:hover:!bg-white/10 dark:focus:!bg-white/10 [&>svg]:dark:!text-white" >
                        </x-ts-button.circle>
                    </div>
                    <x-ts-dropdown>
                        <x-slot:action>
                            <button class="flex items-center gap-2 cursor-pointer hover:opacity-80 transition" x-on:click="show = !show">
                                <img :src="avatar"  class="w-10 h-10 rounded-full object-cover"  gravatar="nobody@tallstackui.com" gravatar-default="monsterid"/>
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
                <x-ts-side-bar smart collapsible thin-scroll navigate>
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
                    @if(auth()->user()->hasPermission('Dashboard'))
                        <x-ts-side-bar.item text="Dashboard" icon="home" :route="route('dashboard')" />
                    @endif

                    <!-- AI Assistant Page -->
                    @if(auth()->user()->hasPermission('AI Assistant'))
                        <x-ts-side-bar.item text="AI Assistant" :route="route('ai.assistant')" icon="sparkles" />
                    @endif

                    <!-- Front Desk Section -->
                    @if(auth()->user()->hasPermission('Front Desk'))
                        <x-ts-side-bar.item text="Front Desk">
                            <x-slot:icon>
                                <x-icon-desktop-computer class="w-5 h-5" />
                            </x-slot:icon>
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Rooms & suites -->
                    @if(auth()->user()->hasAccess('Rooms & Suites'))
                    <x-ts-side-bar.item text="Rooms & Suites">
                        <x-slot:icon>
                            <x-icon-door class="w-5 h-5" />
                        </x-slot:icon>
                        @if(auth()->user()->hasPermission('Room Bookings'))
                            <x-ts-side-bar.item text="Room Bookings">
                                <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>
                        @endif
                    </x-ts-side-bar.item>
                    @endif

                    <!-- Restaurant -->
                    @if(auth()->user()->hasAccess('Restaurant'))
                        <x-ts-side-bar.item text="Restaurant" icon="building-storefront">
                            <x-slot:icon>
                                    <x-icon-chef-hat class="w-5 h-5" />
                                </x-slot:icon>
                            @if(auth()->user()->hasPermission('F&B Order'))
                                <x-ts-side-bar.item text="F&B Order">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Recipe'))
                                <x-ts-side-bar.item text="Recipe" :route="route('restaurant.recipe-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Menu'))
                                <x-ts-side-bar.item text="Menu">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Kitchen Order Monitoring'))
                                <x-ts-side-bar.item text="Kitchen Monitor">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Events -->
                    @if(auth()->user()->hasAccess('Events'))
                        <x-ts-side-bar.item text="Events" icon="calendar">
                            @if(auth()->user()->hasPermission('Event Booking'))
                                <x-ts-side-bar.item text="Event Booking" :route="route('event-booking-create')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Event Orders (BEO)'))
                                <x-ts-side-bar.item text="Event Orders (BEO)" :route="route('event-booking-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Event Budget (BEB)'))
                                <x-ts-side-bar.item text="Event Budget (BEB)" :route="route('event-budget-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Event Liquidation'))
                                <x-ts-side-bar.item text="Event Liquidation" :route="route('event-liquidation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif
    
                    <!-- Pickle court -->
                    @if(auth()->user()->hasAccess('Pickle Court'))
                        <x-ts-side-bar.item text="Pickle Court" icon="squares-2x2">
                            @if(auth()->user()->hasPermission('Court Dashboard'))
                                <livewire:badges.payment-approval-badge />
                            @endif
                            @if(auth()->user()->hasPermission('Courts'))
                                <x-ts-side-bar.item text="Courts" :route="route('admin.courts')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Hours & Overrides'))
                                <x-ts-side-bar.item text="Hours & Overrides" :route="route('admin.operating-hours')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Pricing Engine'))
                                <x-ts-side-bar.item text="Pricing Engine" :route="route('admin.pricing-rules')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Court Bookings'))
                                <x-ts-side-bar.item text="Court Bookings" :route="route('admin.bookings')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Hero Carousel'))
                                <x-ts-side-bar.item text="Hero Carousel" :route="route('admin.carousel')" >
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Inventory -->
                    @if(auth()->user()->hasAccess('Inventory'))
                        <x-ts-side-bar.item text="Inventory">
                            <x-slot:icon>
                                    <x-icon-box class="w-5 h-5" />
                            </x-slot:icon>
                            @if(auth()->user()->hasPermission('Cardex'))
                                <x-ts-side-bar.item text="Cardex" x-on:click="$tsui.open.modal('modal-cardex')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Purchase Order'))
                                <x-ts-side-bar.item text="Purchase Order" :route="route('purchase-order-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Receiving PO'))
                                <x-ts-side-bar.item text="Receiving PO" :route="route('receiving-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('PO Backorders'))
                                <x-ts-side-bar.item text="PO Backorders" :route="route('backorder-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Item Withdrawal'))
                                <x-ts-side-bar.item text="Item Withdrawal" :route="route('withdrawal-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Merchandise Inventory'))
                                <x-ts-side-bar.item text="Merchandise Inventory">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Item Location'))
                                <x-ts-side-bar.item text="Item Location">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Fixed Asset'))
                                <x-ts-side-bar.item text="Fixed Asset" :route="route('fixed-asset.menu')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Transaction -->
                    @if(auth()->user()->hasAccess('Transaction'))
                        <x-ts-side-bar.item text="Transaction" icon="arrow-path-rounded-square" >
                            @if(auth()->user()->hasPermission('Revolving Fund'))
                                <x-ts-side-bar.item text="Revolving Fund" :route="route('revolving-fund.overview')" >
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Advances for Liquidation'))
                                <x-ts-side-bar.item text="Advances for Liquidation" :route="route('afl.summary')" match="afl.*">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Employee Cash Advance'))
                                <x-ts-side-bar.item text="Employee Cash Advance" :route="route('employees-advances.summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Petty Cash Voucher'))
                                <x-ts-side-bar.item text="Petty Cash Voucher" :route="route('petty-cash-voucher.summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Cash Return'))
                                <x-ts-side-bar.item text="Cash Return" :route="route('cash-return.summary-tab')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Reimbursement'))
                                <x-ts-side-bar.item text="Reimbursement" :route="route('reimbursement.summary')" >
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Acknowledgement Receipt'))
                                <x-ts-side-bar.item text="Acknowledgement" :route="route('acknowledgement-receipt.summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Provisional Receipt'))
                                <x-ts-side-bar.item text="Provisional Receipt">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Cash Flow'))
                                <x-ts-side-bar.item text="Cash Flow">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Cashier Shift'))
                                <x-ts-side-bar.item text="Cashier Shift">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Validations Section -->
                    @if(auth()->user()->hasAccess('Validations'))
                        <x-ts-side-bar.item text="Validations" icon="check-badge">
                            @if(auth()->user()->hasPermission('Validate Reimbursement'))
                                <x-ts-side-bar.item text="Reimbursement" :route="route('reimbursement.validation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Validate Employee Cash Advance'))
                                <x-ts-side-bar.item text="Employee Cash advance" :route="route('cash-advances.validation.approval-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Validate Purchase Order'))
                                <x-ts-side-bar.item text="Purchase Order" :route="route('purchase-order.validation-tabs')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Validate Item Withdrawal'))
                                <x-ts-side-bar.item text="Item Withdrawal" :route="route('withdrawal.validation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Validate Event Budget'))
                                <x-ts-side-bar.item text="Event Budget (BEB)" :route="route('event-budget.validation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                            @if(auth()->user()->hasPermission('Validate Event Liquidation'))
                                <x-ts-side-bar.item text="Event Liquidation" :route="route('event-liquidation.validation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Validate Equipment Request'))
                                <x-ts-side-bar.item text="Equipment Request">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Validate Fixed Asset'))
                                <x-ts-side-bar.item text="Fixed Asset" :route="route('fixed-asset.validation-summary')">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif

                            @if(auth()->user()->hasPermission('Validate COA Template'))
                                <x-ts-side-bar.item text="COA Template">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Accounting  -->
                    @if(auth()->user()->hasAccess('Accounting'))
                        <x-ts-side-bar.item text="Accounting" icon="calculator">
                            @if(auth()->user()->hasPermission('Chart of Accounts Management'))
                                <x-ts-side-bar.item text="COA - Management">
                                    <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                    </x-slot:icon>
                                </x-ts-side-bar.item>
                            @endif
                        </x-ts-side-bar.item>
                    @endif

                    <!-- Business -->
                    @if(auth()->user()->hasAccess('Business'))
                    <x-ts-side-bar.item text="Business">
                        <x-slot:icon>
                            <x-icon-store class="w-5 h-5" />
                        </x-slot:icon>
                        @if(auth()->user()->hasPermission('Supplier'))
                            <x-ts-side-bar.item text="Supplier">
                                <x-slot:icon>
                                        <x-icon-dot class="w-5 h-5" />
                                </x-slot:icon>
                            </x-ts-side-bar.item>
                        @endif
                    </x-ts-side-bar.item>
                    @endif

                    <!-- Data Management -->
                    @if(auth()->user()->hasPermission('Data Management'))
                        <x-ts-side-bar.item text="Data Management" icon="server-stack"  :route="route('data-management.tab')"/>
                    @endif

                    <!-- Role Management -->
                     @if(auth()->user()->hasPermission('Access Management'))
                        <x-ts-side-bar.item text="Access Management" icon="shield-check" :route="route('access-management')" />
                    @endif
                    

                    <!-- Settings -->
                     @if(auth()->user()->hasPermission('Settings'))
                        <x-ts-side-bar.item text="Settings" icon="cog-6-tooth" />
                    @endif


                    <!-- Logout -->
                    {{-- <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <x-ts-side-bar.item text="Logout" icon="arrow-left-start-on-rectangle" onclick="event.preventDefault(); this.closest('form').submit();" />
                    </form> --}}
                </x-ts-side-bar>
        </x-slot:menu>
        {{ $slot }}

        {{-- <livewire:inventory.cardex /> --}}
    </x-ts-layout>
    @livewireScripts
    </body>
</html>
