<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('login', function () {
    return view('portal');
});

Route::get('dashboard', function () { return view('dashboard'); })->name('dashboard.index');
Route::get('maintenance', function () { return view('maintenance'); })->name('maintenance.index');
Route::get('reports', function () { return view('reports'); })->name('reports.index');
Route::get('settings', function () { return view('settings'); })->name('settings.index');
Route::get('account', function () { return view('account'); })->name('account.index');
