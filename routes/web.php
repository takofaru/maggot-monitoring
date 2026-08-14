<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

// Guest Routes
Route::middleware(['guest'])->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

// Logout Route
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Menu Application Routes (Protected with Auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Memberikan nama 'dashboard.index' dan alias 'dashboard'
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard.index');
    Route::livewire('/observation', 'pages::observation')->name('observation.index');
    Route::livewire('/reports', 'pages::reports')->name('reports.index');
    Route::livewire('/settings', 'pages::settings')->name('settings.index');
    Route::livewire('/account', 'pages::account')->name('account.index');
});
