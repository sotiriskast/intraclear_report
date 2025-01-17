<?php

use App\Livewire\RoleManagement;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;

Route::get('/register', function () {
    abort(404); // Or redirect to a specific page
});
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    });

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
Route::middleware(['auth:web'])->group(function () {
    Route::get('/admin/users', UserManagement::class)->name('admin.users')->middleware(['can:manage-users']);
    Route::get('/admin/roles', RoleManagement::class)->name('admin.roles')->middleware(['can:manage-roles']);
});


