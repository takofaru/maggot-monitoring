<div class="flex flex-col justify-between bg-(--bg-colour) h-screen border-e-[1.5px]
    border-(--outline-colour) sticky top-0"
>
    <div class="flex flex-col gap-(--size-26) px-(--size-26) py-(--size-42)">
        Logo
        <div class="flex flex-col gap-(--size-16)">
            <x-nav-link route="dashboard.index" icon="layout-dashboard">
                Dashboard
            </x-nav-link>
            <x-nav-link route="maintenance.index" icon="notebook-text">
                Catatan Pemeliharaan
            </x-nav-link>
            <x-nav-link route="reports.index" icon="chart-column">
                Laporan dan Analisis
            </x-nav-link>
            <x-nav-link route="settings.index" icon="bolt">
                Pengaturan Perangkat
            </x-nav-link>
            <x-nav-link route="account.index" icon="square-user-round">
                Manajemen Akun
            </x-nav-link>
        </div>
    </div>
    <div>Profile</div>
</div>
