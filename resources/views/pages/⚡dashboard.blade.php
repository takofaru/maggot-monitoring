<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<div class="space-y-(--size-26)">
    <h1 class="text-(--prime-colour) text-(length:--size-42) font-bold">Dashboard</h1>
    <div class="inline-flex gap-(--size-10)">
        <div class="inline-flex gap-(--size-10)">
            <x-lucide-refresh-cw class="w-(--size-16)"/>
            Siklus ke: {{}}
        </div>
        <div class="inline-flex gap-(--size-10)">
            <x-lucide-calendar class="w-(--size-16)"/>
        </div>
        <div class="inline-flex gap-(--size-10)">
            <x-lucide-move-up-right class="w-(--size-16)"/>
        </div>
    </div>
</div>
