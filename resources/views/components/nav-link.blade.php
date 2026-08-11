@php
    // Mengecek apakah URL saat ini cocok dengan nama route yang diberikan.
    // Menambahkan tanda bintang (*) berguna agar sub-halaman (seperti detail/edit)
    // tetap membuat menu induknya terlihat aktif.
    $isActive = request()->routeIs($route . '*');
@endphp

<a href="{{ route($route) }}"
   {{-- Directive @class akan otomatis menambahkan class jika kondisinya true --}}
   {{ $attributes->class([
       'flex gap-(--size-10) px-(--size-26) py-(--size-16) rounded-(--size-16) font-semibold border-[1.5px]',
       'bg-(--prime-colour) text-(--fg-colour) border-(--prime-colour) font-semibold' => $isActive,
       'text-(--text-colour) border-(--bg-colour) hover:bg-(--fg-colour) hover:border-(--outline-colour)' => !$isActive
   ]) }}>

    <!-- Tempat untuk Icon (bisa SVG, FontAwesome, atau Emoji) -->
    @if($icon)
        <x-dynamic-component :component="'lucide-' . $icon" class="w-(--size-26)"/>
    @endif

    <!-- Teks Menu -->
    <span class="whitespace-nowrap">{{ $slot }}</span>
</a>
