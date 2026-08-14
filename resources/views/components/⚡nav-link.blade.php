<?php

use Livewire\Component;
use Illuminate\Support\Facades\Route;

new class extends Component
{
    public string $route = '';
    public string $href = '';
    public string $icon = '';
    public bool $isActive = false;

    public function mount(string $route = '', string $href = '', string $icon = '', ?bool $active = null)
    {
        $this->route = $route;
        $this->href = $href;
        $this->icon = $icon;

        // Tentukan URL tujuan: jika $route adalah nama route yang valid, gunakan route($route)
        $targetUrl = $this->href;
        if (empty($targetUrl) && !empty($this->route)) {
            $targetUrl = Route::has($this->route) ? route($this->route) : $this->route;
        }
        $this->href = $targetUrl ?: '#';

        // Tentukan apakah link sedang aktif
        if ($active !== null) {
            $this->isActive = $active;
        } elseif (!empty($this->route) && Route::has($this->route)) {
            $this->isActive = request()->routeIs($this->route . '*');
        } elseif (!empty($this->href) && $this->href !== '#') {
            $this->isActive = request()->fullUrlIs($this->href . '*') || request()->url() === $this->href;
        }
    }
};
?>

<div class="rounded-(--size-16) border-[1.5px] transition-all {{ $isActive ? 'text-(--fg-colour) bg-(--prime-colour) border-hidden' : 'text-(--text-colour) hover:bg-(--bg-colour) border-(--bg2-colour) hover:border-(--outline-colour) hover:border-solid' }}">
    @php
        $iconName = $icon ? (str_starts_with($icon, 'lucide-') ? $icon : 'lucide-' . $icon) : null;
    @endphp

    <a href="{{ $href }}" class="flex items-center gap-(--size-10) px-(--size-26) py-(--size-16) font-medium text-(length:--size-16)">
        @if($iconName)
            <x-dynamic-component :component="$iconName" class="w-(--size-26)" />
        @endif
        <span>{{ $slot }}</span>
    </a>
</div>
