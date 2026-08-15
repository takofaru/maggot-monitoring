<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        @livewireStyles
    </head>
    <body x-data="{ mobileSidebarOpen: false }" class="bg-(--bg-colour) flex flex-col md:flex-row h-screen overflow-hidden">

        <!-- Mobile Top Bar: Hamburger di Kiri, Logo di Kanan (Hanya Tampil di Layar Mobile) -->
        <header class="no-print h-14 bg-(--bg2-colour) border-b border-(--outline-colour) px-4 flex items-center justify-between shrink-0 z-40 md:hidden">
            <!-- Hamburger Menu Button di Kiri -->
            <button
                @click="mobileSidebarOpen = true"
                type="button"
                class="p-2 rounded-xl text-[#163428] hover:bg-gray-200/70 transition cursor-pointer"
                aria-label="Buka Menu Navigasi"
            >
                <x-lucide-menu class="w-6 h-6 text-[#163428]"/>
            </button>

            <!-- Logo & Nama Aplikasi di Kanan -->
            <div class="flex items-center gap-2">
                <span class="font-extrabold text-[#163428] text-base tracking-wider">MAGGOT</span>
                <div class="w-7 h-7 bg-[#163428] rounded-full flex items-center justify-center text-white font-bold text-xs shadow-xs">
                    M
                </div>
            </div>
        </header>

        <!-- Sidebar Component (Desktop Sidebar & Mobile Drawer) -->
        <div class="no-print">
            <livewire:sidebar />
        </div>

        <!-- Main Content Area: Scrollable & Responsive Padding -->
        <main class="flex-1 overflow-auto">
            <div class="p-4 sm:p-6 md:p-(--size-42) w-full min-w-0 md:min-w-[1140px] box-border shrink-0">
                {{ $slot }}
            </div>
        </main>

        <!-- Custom Global Confirmation Dialog UI -->
        <div class="no-print">
            <livewire:confirm-dialog />
        </div>

        @livewireScripts
    </body>
</html>
