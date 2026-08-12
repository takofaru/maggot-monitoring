<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan dan Analisis - Maggot Monitoring</title>
    @vite('resources/css/app.css')
    @livewireStyles
</head>
<body class="bg-[#F8F9FA] flex min-h-screen">

    <!-- Sidebar Component (Blade Component) -->
    <x-sidebar />

    <!-- Main Content (Livewire Component) -->
    <main class="flex-1 overflow-y-auto">
        @livewire('reports-manager')
    </main>

    @livewireScripts
</body>
</html>