<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\DashboardOverview;
use App\Livewire\ObservationLogManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect Halaman Utama (/) ke Login
Route::get('/', function () {
<<<<<<< HEAD
    return view('welcome');
});
Route::get('login', function () {
    return view('portal');
=======
    return redirect()->route('login');
>>>>>>> b4d663f (Simpan perubahan lokal sebelum pull)
});

// Halaman Login
Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
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

// Route Terproteksi (WAJIB LOGIN)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardOverview::class)->name('dashboard');
    Route::get('/catatan', ObservationLogManager::class)->name('catatan');

    // Placeholder Menu Sidebar
    Route::get('/laporan', fn() => view('reports.index'))->name('laporan');
    Route::get('/perangkat', fn() => view('devices.index'))->name('perangkat');
    Route::get('/manajemen-akun', fn() => view('users.index'))->name('users');
});