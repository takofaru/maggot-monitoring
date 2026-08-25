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

        // URL tujuan:
        // - jika $href diisi, prioritaskan sebagai target
        // - jika $route adalah nama route valid, gunakan route($route)
        // - jika $route bukan nama route, perlakukan sebagai kandidat URL/path lalu validasi
        $targetUrl = '';
        if (!empty($this->href)) {
            $targetUrl = $this->href;
        } elseif (!empty($this->route)) {
            $targetUrl = Route::has($this->route) ? route($this->route) : $this->route;
        }
        $this->href = $this->sanitizeTargetUrl($targetUrl);

        // Tentukan apakah link sedang aktif
        if ($active !== null) {
            $this->isActive = $active;
        } elseif (!empty($this->route) && Route::has($this->route)) {
            $this->isActive = request()->routeIs($this->route . '*');
        } elseif (!empty($this->href) && $this->href !== '#' && preg_match('/^https?:\/\//i', $this->href) === 1) {
            $this->isActive = request()->fullUrlIs($this->href . '*') || request()->url() === $this->href;
        }
    }

    private function sanitizeTargetUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '#';
        }

        // Cegah format scheme salah seperti https:/ atau http:/ yang akan jadi /https:/ di browser.
        if (preg_match('/^[a-z][a-z0-9+\-.]*:\/(?!\/)/i', $value) === 1) {
            return '#';
        }

        // Absolute URL valid (http/https) diperbolehkan.
        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            return $value;
        }

        // Jika memiliki scheme tapi bukan URL valid, tolak.
        if (preg_match('/^[a-z][a-z0-9+\-.]*:/i', $value) === 1) {
            return '#';
        }

        // Relative path/query/fragment sederhana diperbolehkan.
        if (str_starts_with($value, '//') || preg_match('/\s/', $value) === 1) {
            return '#';
        }

        return $value;
    }
};
?>

<div class="w-full rounded-(--size-16) border-[1.5px] transition-all {{ $isActive ? 'text-(--fg-colour) bg-(--prime-colour) border-hidden' : 'text-(--text-colour) hover:bg-(--bg-colour) border-(--bg2-colour) hover:border-(--outline-colour) hover:border-solid' }}">
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
