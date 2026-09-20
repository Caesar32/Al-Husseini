<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Admin Panel Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    Route::get('/starter', function () {
        return view('admin.starter');
    })->name('starter');

    Route::get('/login', function () {
        return view('admin.auth.login');
    })->name('login');

    Route::get('/register', function () {
        return view('admin.auth.register');
    })->name('register');

    Route::get('/404', function () {
        return view('admin.errors.404');
    })->name('error.404');
});

