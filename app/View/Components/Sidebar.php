<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\Support\Facades\Auth;

class Sidebar extends Component
{
    public bool $isAdmin = false;

    public function __construct()
    {
        $user = Auth::user();
        if ($user) {
            // Cek role user dari database
            $this->isAdmin = isset($user->role) && strtolower($user->role) === 'admin';
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.sidebar');
    }
}