<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catatan Pemeliharaan - Maggot Monitoring</title>
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="bg-gray-50 flex min-h-screen">

    <x-sidebar />

    <main class="flex-1 overflow-y-auto">
        @livewire('maintenance-manager')
    </main>

    @livewireScripts
</body>
</html>