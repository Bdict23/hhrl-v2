<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="tallstackui_darkTheme()"
      x-bind:class="darkTheme ? 'dark' : ''">
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

        <style>
            [x-cloak] { display: none !important; }

            /* ── Floating leaf animations ── */
            @keyframes floatLeaf {
                0%,100% { transform: translateY(0) rotate(0deg) scale(1); opacity:.18; }
                33%     { transform: translateY(-18px) rotate(8deg) scale(1.04); opacity:.22; }
                66%     { transform: translateY(-8px) rotate(-5deg) scale(.97); opacity:.15; }
            }
            @keyframes floatLeaf2 {
                0%,100% { transform: translateY(0) rotate(0deg); opacity:.13; }
                50%     { transform: translateY(-24px) rotate(-10deg); opacity:.20; }
            }
            @keyframes floatLeaf3 {
                0%,100% { transform: translateY(0) rotate(0deg) scale(1); opacity:.10; }
                40%     { transform: translateY(-14px) rotate(6deg) scale(1.06); opacity:.17; }
            }
            @keyframes sway {
                0%,100% { transform: rotate(-3deg); }
                50%     { transform: rotate(3deg); }
            }
            @keyframes shimmer {
                0%,100% { opacity:.3; }
                50%     { opacity:.6; }
            }
            @keyframes particleDrift {
                0%   { transform: translateY(100vh) rotate(0deg); opacity:0; }
                10%  { opacity:.7; }
                90%  { opacity:.5; }
                100% { transform: translateY(-10vh) rotate(360deg); opacity:0; }
            }

            .leaf-f1 { animation: floatLeaf  6s ease-in-out infinite; }
            .leaf-f2 { animation: floatLeaf2 8s ease-in-out infinite 1s; }
            .leaf-f3 { animation: floatLeaf3 7s ease-in-out infinite 2.5s; }
            .leaf-f4 { animation: floatLeaf  9s ease-in-out infinite .5s; }
            .leaf-f5 { animation: floatLeaf2 5s ease-in-out infinite 3s; }
            .tree-sway    { animation: sway 4s ease-in-out infinite; transform-origin: bottom center; }
            .shimmer-anim { animation: shimmer 3s ease-in-out infinite; }

            /* Drifting particle leaves */
            .particle {
                position: absolute; width: 8px; height: 10px;
                border-radius: 0 60% 0 60%; pointer-events: none; z-index: 1;
            }
            .particle:nth-child(1) { left:10%; animation: particleDrift 12s linear infinite 0s; }
            .particle:nth-child(2) { left:25%; animation: particleDrift 15s linear infinite 2s; }
            .particle:nth-child(3) { left:40%; animation: particleDrift 10s linear infinite 5s; }
            .particle:nth-child(4) { left:60%; animation: particleDrift 14s linear infinite 1s; }
            .particle:nth-child(5) { left:75%; animation: particleDrift 11s linear infinite 7s; }
            .particle:nth-child(6) { left:88%; animation: particleDrift 13s linear infinite 3s; }
            .particle:nth-child(7) { left:5%;  animation: particleDrift 16s linear infinite 9s; }
            .particle:nth-child(8) { left:50%; animation: particleDrift 9s  linear infinite 4s; }

            /* Nature backgrounds */
            .nature-light {
                background:
                    radial-gradient(ellipse 100% 80% at 50% 110%, rgba(5,150,105,.5) 0%, transparent 60%),
                    radial-gradient(ellipse 60% 40% at 10% 0%,   rgba(167,243,208,.4) 0%, transparent 50%),
                    linear-gradient(175deg, #064e3b 0%, #065f46 25%, #047857 50%, #059669 75%, #10b981 100%);
            }
            .nature-dark {
                background:
                    radial-gradient(ellipse 90% 70% at 50% 110%, rgba(2,44,34,.8) 0%, transparent 60%),
                    radial-gradient(ellipse 50% 35% at 10% 0%,   rgba(6,78,59,.5) 0%, transparent 50%),
                    linear-gradient(175deg, #011710 0%, #022c22 30%, #064e3b 65%, #065f46 100%);
            }
        </style>
    </head>
    <body class="font-sans antialiased" x-cloak>

        {{-- Full-screen nature background --}}
        <div class="relative min-h-screen overflow-hidden"
             x-bind:class="darkTheme ? 'nature-dark' : 'nature-light'">

            {{-- Particle leaves --}}
            <div class="absolute inset-0 pointer-events-none">
                <div class="particle bg-emerald-300/60"></div>
                <div class="particle bg-green-300/50"></div>
                <div class="particle bg-teal-300/60"></div>
                <div class="particle bg-emerald-200/70"></div>
                <div class="particle bg-green-200/60"></div>
                <div class="particle bg-emerald-400/50"></div>
                <div class="particle bg-teal-200/60"></div>
                <div class="particle bg-green-300/70"></div>
            </div>

            {{-- SVG nature scene anchored to bottom --}}
            <div class="absolute inset-x-0 bottom-0 pointer-events-none select-none">
                <svg viewBox="0 0 1200 420" xmlns="http://www.w3.org/2000/svg"
                     class="w-full h-auto opacity-25"
                     preserveAspectRatio="xMidYMax meet">
                    <!-- Background hills -->
                    <ellipse cx="200"  cy="420" rx="300" ry="140" fill="rgba(5,150,105,.5)"/>
                    <ellipse cx="1000" cy="420" rx="320" ry="120" fill="rgba(4,120,87,.5)"/>
                    <ellipse cx="600"  cy="420" rx="420" ry="100" fill="rgba(6,95,70,.6)"/>
                    <!-- Far-left tall tree -->
                    <rect x="30"  y="200" width="22" height="160" rx="6" fill="rgba(2,44,34,.8)" class="tree-sway-r"/>
                    <ellipse cx="41"  cy="193" rx="55" ry="72" fill="rgba(5,150,105,.7)" class="tree-sway-r"/>
                    <ellipse cx="41"  cy="140" rx="42" ry="58" fill="rgba(16,185,129,.6)" class="tree-sway-r"/>
                    <!-- Left tree -->
                    <rect x="160" y="220" width="26" height="150" rx="6" fill="rgba(2,44,34,.8)"/>
                    <ellipse cx="173" cy="213" rx="70" ry="88" fill="rgba(4,120,87,.7)"/>
                    <ellipse cx="173" cy="155" rx="54" ry="70" fill="rgba(16,185,129,.6)"/>
                    <!-- Center-left medium tree -->
                    <rect x="390" y="270" width="18" height="110" rx="5" fill="rgba(2,44,34,.8)"/>
                    <ellipse cx="399" cy="263" rx="50" ry="66" fill="rgba(5,150,105,.65)"/>
                    <ellipse cx="399" cy="212" rx="38" ry="52" fill="rgba(16,185,129,.55)"/>
                    <!-- Center-right medium tree -->
                    <rect x="790" y="260" width="18" height="120" rx="5" fill="rgba(2,44,34,.8)"/>
                    <ellipse cx="799" cy="253" rx="52" ry="68" fill="rgba(4,120,87,.65)"/>
                    <ellipse cx="799" cy="200" rx="40" ry="54" fill="rgba(16,185,129,.55)"/>
                    <!-- Right tall tree -->
                    <rect x="980" y="210" width="24" height="155" rx="6" fill="rgba(2,44,34,.8)"/>
                    <ellipse cx="992" cy="204" rx="62" ry="80" fill="rgba(5,150,105,.7)"/>
                    <ellipse cx="992" cy="145" rx="48" ry="64" fill="rgba(16,185,129,.6)"/>
                    <!-- Far-right tree -->
                    <rect x="1140" y="230" width="20" height="140" rx="5" fill="rgba(2,44,34,.8)" class="tree-sway-r"/>
                    <ellipse cx="1150" cy="224" rx="50" ry="66" fill="rgba(5,150,105,.7)" class="tree-sway-r"/>
                    <ellipse cx="1150" cy="172" rx="38" ry="52" fill="rgba(16,185,129,.6)" class="tree-sway-r"/>
                    <!-- Bushes / undergrowth -->
                    <ellipse cx="100"  cy="380" rx="90" ry="42" fill="rgba(5,150,105,.4)"/>
                    <ellipse cx="500"  cy="400" rx="120" ry="40" fill="rgba(4,120,87,.4)"/>
                    <ellipse cx="900"  cy="390" rx="110" ry="38" fill="rgba(6,95,70,.4)"/>
                    <ellipse cx="1100" cy="385" rx="80"  ry="35" fill="rgba(5,150,105,.35)"/>
                    <!-- Ground -->
                    <rect x="0" y="398" width="1200" height="22" fill="rgba(2,44,34,.55)"/>
                    <!-- Sun/Moon glow -->
                    <circle cx="1100" cy="80" r="60" fill="rgba(167,243,208,.15)" class="shimmer-anim"/>
                    <circle cx="1100" cy="80" r="42" fill="rgba(167,243,208,.20)" class="shimmer-anim"/>
                    <!-- Floating leaf puffs -->
                    <ellipse cx="580" cy="130" rx="30" ry="18" fill="rgba(167,243,208,.28)" class="leaf-f1"/>
                    <ellipse cx="220" cy="160" rx="22" ry="14" fill="rgba(167,243,208,.22)" class="leaf-f2"/>
                    <ellipse cx="850" cy="150" rx="18" ry="12" fill="rgba(209,250,229,.22)" class="leaf-f3"/>
                    <!-- Birds -->
                    <path d="M460 90 Q470 84 480 90" stroke="rgba(167,243,208,.55)" stroke-width="2" fill="none"/>
                    <path d="M500 74 Q510 68 520 74" stroke="rgba(167,243,208,.45)" stroke-width="2" fill="none"/>
                    <path d="M430 110 Q438 105 446 110" stroke="rgba(167,243,208,.35)" stroke-width="1.5" fill="none"/>
                </svg>
            </div>

            {{-- Decorative corner leaves --}}
            <svg class="absolute -bottom-6 -left-6 w-64 h-64 opacity-20 leaf-f4 pointer-events-none"
                 viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path d="M10,190 Q10,10 190,10 Q10,10 10,190" fill="rgba(167,243,208,.9)"/>
                <path d="M10,190 L100,100" stroke="rgba(167,243,208,.5)" stroke-width="2" fill="none"/>
            </svg>
            <svg class="absolute -top-8 -right-4 w-56 h-56 opacity-15 leaf-f5 pointer-events-none rotate-180"
                 viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <path d="M10,190 Q10,10 190,10 Q10,10 10,190" fill="rgba(52,211,153,.9)"/>
            </svg>

            {{-- Theme toggle: fixed top-right --}}
            <div class="fixed top-4 right-4 z-50">
                <button
                    x-on:click="mode = (mode === 'dark' ? 'light' : 'dark')"
                    x-bind:title="darkTheme ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
                    class="group flex items-center gap-2 px-3 py-2 rounded-full border-2 shadow-lg text-xs font-semibold transition-all duration-300 cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-500"
                    x-bind:class="darkTheme
                        ? 'bg-gray-800/90 border-emerald-700/60 text-emerald-300 hover:bg-gray-700'
                        : 'bg-white/90 border-white/60 text-green-800 hover:bg-white'">
                    <svg x-show="!darkTheme" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                    <svg x-show="darkTheme" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span x-text="darkTheme ? 'Light' : 'Dark'"></span>
                </button>
            </div>

            {{-- Centered layout --}}
            <div class="relative z-10 min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 px-4">

                {{-- Logo & branding — OUTSIDE the card --}}
                <div class="w-full sm:max-w-md mb-5 flex flex-col items-center gap-3">
                    {{-- Logo with gradient ring + glow --}}
                    <div class="relative">
                        <div class="absolute inset-0 rounded-full blur-2xl opacity-50 bg-emerald-300"></div>
                        <div class="relative p-1 rounded-full bg-gradient-to-br from-white/60 to-white/20 ring-4 ring-white/50 shadow-2xl backdrop-blur-sm">
                            <img src="{{ asset('/assets/images/1772440510.png') }}"
                                 class="h-24 w-24 rounded-full object-cover shadow-lg" />
                        </div>
                    </div>

                    {{-- Resort name & tagline --}}
                    <div class="text-center">
                        <h1 class="text-2xl font-bold tracking-tight text-white drop-shadow-lg">
                            LYR Agro Inland Resort
                        </h1>
                        
                    </div>
                </div>

                {{-- Card — form only --}}
                <div class="w-full sm:max-w-md px-6 py-6 shadow-2xl overflow-hidden sm:rounded-2xl transition-colors duration-300"
                     x-bind:class="darkTheme
                        ? 'bg-gray-900/90 text-gray-100'
                        : 'bg-white/90 text-gray-900'">
                    {{ $slot }}
                </div>

                {{-- Footer below card --}}
                <p class="mt-6 text-xs text-emerald-200/50 drop-shadow">
                    &copy; {{ date('Y') }} LYR Agro Inland Resort. All rights reserved.
                </p>
            </div>

        </div>

        @livewireScripts
    </body>
</html>
