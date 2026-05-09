<div>
    <!-- Greeting Header -->
     <div class="w-full md:max-w-md mb-2 flex justify-between ">
            <div>
                <x-ts-breadcrumbs :items="[
                    ['label' => 'Home', 'link' => '/', 'icon' => 'home'],
                    ['label' => 'Dashboard', 'link' => '/dashboard', 'icon' => 'chart-bar-square'],
                ]" />
            </div>
           {{-- <div>
             <p class="lg:text-2xl font-semibold text-gray-900 dark:text-white">Good morning,
                 {{ auth()->user()->name }}!</p>
             <p class="mt-1 text-gray-600 dark:text-gray-400">Manage orders, services, and events</p>
           </div> --}}
    </div>
    <div class="lg:grid lg:grid-cols-3 gap-2">
         <!-- Stats Cards -->
        <div class="w-full mr-2 lg:col-span-2">
            <div class="grid lg:grid-cols-3 mt-2 gap-4">
                <!-- Total Orders Card -->
                <div class="flex justify-between items-center  rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900 ">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 sm:whitespace-nowrap md:whitespace-normal">Total
                            Orders Today</p>
                        <p class="mt-2 lg:text-3xl  text-2xl font-bold text-gray-900 dark:text-white">120</p>
                    </div>
                    <div class="lg:rounded-lg rounded bg-green-100 p-3 dark:bg-green-900">
                         <x-icon-cart class="w-5 h-5 text-emerald-700 dark:text-white"/>
                    </div>
                </div>
                <!-- Total Customers Card -->
                <div class="flex items-center justify-between rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 sm:whitespace-nowrap md:whitespace-normal">Total
                            Customers Today</p>
                        <p class="mt-2 lg:text-3xl text-2xl font-bold text-gray-900 dark:text-white">50</p>
                    </div>
                    <div class="rounded-lg bg-green-100 p-3 dark:bg-green-900">
                         <x-icon-customers class="w-5 h-5 text-emerald-700 dark:text-white"/>
                    </div>
                </div>
                <!-- Total Revenue Card -->
                <div class="flex items-center justify-between rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 sm:whitespace-nowrap md:whitespace-normal">Total
                            Revenue Today</p>
                        <p class="mt-2 lg:text-3xl  text-2xl font-bold text-gray-900 dark:text-white">₱12,256
                        </p>
                    </div>
                    <div class="rounded-lg bg-green-100 p-3  dark:bg-green-900 ">
                        <x-icon-peso class="w-5 h-5 text-emerald-700 dark:text-white"/>
                    </div>
                </div>
            </div>

             <!-- Sales  by channel -->
            <div class="mt-3">
                <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sales by channel</h2>
                        <div class="flex gap-2">
                            <x-ts-dropdown text="This Week" position="bottom-start">
                                <x-ts-dropdown.items text="This Month" />
                                <x-ts-dropdown.items text="This Year" separator />
                            </x-ts-dropdown>
                             <x-ts-button sm icon="funnel" position="right">Filter</x-ts-button>
                        </div>
                    </div>

                    <!-- Chart Placeholder for beartropy/charts -->
                    <div
                        class="flex h-64 items-center justify-center rounded-lg bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700">
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Chart Area (Ready for
                                beartropy/charts)</p>
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Restaurant, Events, Leisure
                                data visualization</p>
                        </div>
                    </div>

                    <!-- Chart Legend -->
                    <div class="mt-6 flex gap-8 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-orange-400"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Restaurant</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-green-600"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Events</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="h-3 w-3 rounded-full bg-yellow-400"></div>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Leisure</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals Table -->
            <div class="rounded-lg bg-white shadow-sm dark:bg-gray-900 mt-3">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Approvals</h2>
                          <x-ts-button sm icon="funnel" position="right">Filter</x-ts-button>
                    </div>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">All</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    TYPE</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    DESCRIPTION</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    PREPARED DATE</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    PREPARED BY</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900 dark:text-white">
                                    ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">Menu
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Sinigag na Bangus
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">01-Jan-2025</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Juan Dela Cruz
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="#"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                                </td>
                            </tr>
                            <tr
                                class="border-b border-gray-100 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">PO</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Sinigag na Bangus
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">01-Jan-2025</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Mira Rivera</td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="#"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">BEB</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Sinigag na
                                    Bangus</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">01-Jan-2025</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-400">Jean Luna</td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="#"
                                        class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <!-- Current Events Sidebar -->
            <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900 w-full mb-3 mt-3">
                <div class="mb-6 flex items-center justify-between ">
                    <h3 class="lg:text-lg font-semibold text-gray-900 dark:text-white">Current Events</h3>
                    <a href="#"
                        class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 ">See all</a>
                </div>
                <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">This month</p>
                <hr class="border-t-2 border-dashed border-gray-400 my-4" />

                <!-- Event Item 1 -->
                <div class="mb-6 border-l-4 border-red-500 pl-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-gray-900 dark:text-white">LYR Digital Literacy
                            Seminar</h4>
                        <x-ts-badge xs  color="amber" class="whitespace-nowrap" round outline >
                            <x-slot:left>
                                <p class="mr-2">9</p>
                            </x-slot:left>
                            DAYS LEFT
                        </x-ts-badge>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">MON, FEB 21 - WED, FEB 23 2025
                    </p>
                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM15 20H9m6 0h.01" />
                            </svg>
                            <span>Guests: <strong>50 people</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Venue: <strong>Hangin Function Hall</strong></span>
                        </div>
                    </div>
                    <a href="#"
                        class="mt-3 inline-block text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                </div>
                <hr class="border-t-2 border-dashed border-gray-400 my-4" />
                <!-- Event Item 2 -->
                <div class="border-l-4 border-red-500 pl-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-semibold text-gray-900 dark:text-white">LYR Digital Literacy
                            Seminar</h4>
                        <x-ts-badge xs  color="rose" class="whitespace-nowrap" round outline >
                            <x-slot:left>
                                <p class="mr-2">2</p>
                            </x-slot:left>
                            DAYS LEFT
                        </x-ts-badge>
                    </div>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">MON, FEB 21 - WED, FEB 23 2025
                    </p>
                    <div class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM15 20H9m6 0h.01" />
                            </svg>
                            <span>Guests: <strong>50 people</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Venue: <strong>Hangin Function Hall</strong></span>
                        </div>
                    </div>
                    <a href="#"
                        class="mt-3 inline-block text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                </div>
                <hr class="border-t-2 border-dashed border-gray-400 my-4" />

            </div>
            <!-- Inventory Updates Sidebar -->
            <div class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                <div class="mb-6 flex justify-between space-x-4">
                    <h3 class="lg:text-lg font-semibold text-gray-900 dark:text-white  whitespace-nowrap">Inventory
                        Updates</h3>
                    <div class="grid lg:flex lg:justify-end gap-2 text-center content-center">
                        <a href="#"
                            class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 whitespace-nowrap">See
                            all</a>
                        <x-ts-button sm icon="funnel" position="right">Filter</x-ts-button>
                    </div>
                </div>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">Lowest QTY</p>

                <div class="space-y-4">
                    <!-- Inventory Item 1 -->
                    <div
                        class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">White Rice</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">2kg remaining</p>
                        </div>
                        <a href="#" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                    </div>

                    <!-- Inventory Item 2 -->
                    <div
                        class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">White Rice</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">2kg remaining</p>
                        </div>
                        <a href="#" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                    </div>

                    <!-- Inventory Item 3 -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold text-gray-900 dark:text-white">White Rice</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">2kg remaining</p>
                        </div>
                        <a href="#" class="text-blue-600 hover:text-blue-700 dark:text-blue-400">View</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <x-ts-back-to-top />

</div>
