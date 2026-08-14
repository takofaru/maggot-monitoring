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
    <body class="bg-(--bg-colour) flex h-screen overflow-hidden">

        <!-- Sidebar Anda sekarang akan TERKUNCI alias diam di tempat! -->
        <livewire:sidebar />

        <!-- Karena main punya overflow-y-auto, HANYA area ini yang akan bisa di-scroll -->
        <main class="flex-1 overflow-auto">
            <div class="p-(--size-42) min-w-max w-full relative box-border">
                {{ $slot }}
            </div>
        </main>

        @livewireScripts
    </body>
</html>
