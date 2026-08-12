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
    return redirect()->route('login');
});

// Halaman Portal Login
Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard.index');
    }
    return view('portal');
})->name('login');

// Eksekusi Login
Route::post('/login', function (Request $request) {
    $request->validate([
        'username' => 'required',
        'password' => 'required',
    ]);

    $user = User::where('username', $request->username)->first();

    if ($user && Hash::check($request->password, $user->password_hash)) {
        Auth::login($user);
        $request->session()->regenerate();
        return redirect()->intended('/dashboard');
    }

    return back()->withErrors([
        'username' => 'Username atau password yang Anda masukkan salah.',
    ])->onlyInput('username');
});

// Eksekusi Logout
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');


/*
|--------------------------------------------------------------------------
| Menu Application Routes (Protected with Auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    // Memberikan nama 'dashboard.index' dan alias 'dashboard'
    Route::get('/dashboard', fn() => view('dashboard'))
        ->name('dashboard.index');

    // Route alias 'dashboard' agar tidak error jika dipanggil via route('dashboard')
    Route::get('/dashboard-alias', fn() => redirect()->route('dashboard.index'))
        ->name('dashboard');

    Route::get('/maintenance', fn() => view('maintenance'))->name('maintenance.index');
    Route::get('/reports', fn() => view('reports'))->name('reports.index');

    // Group Khusus Role Admin
    Route::middleware(['can:admin-only'])->group(function () {
        Route::get('/settings', fn() => view('settings'))->name('settings.index');
        Route::get('/account', fn() => view('account'))->name('account.index');
    });

});