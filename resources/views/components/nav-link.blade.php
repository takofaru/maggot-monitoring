@props(['active'])

@php
$classes = ($active ?? false)
            ? 'flex items-center gap-3 px-4 py-3 rounded-2xl font-bold text-sm bg-[#1A382B] text-white transition-all'
            : 'flex items-center gap-3 px-4 py-3 rounded-2xl font-bold text-sm text-gray-600 hover:bg-gray-100 transition-all';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>