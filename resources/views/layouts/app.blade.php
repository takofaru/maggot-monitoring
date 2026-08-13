<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="bg-[#FAF9F6] flex min-h-screen">
        <livewire:sidebar />

        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
