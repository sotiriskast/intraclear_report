<?php

use App\Livewire\RoleManagement;
use App\Livewire\UserManagement;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});
Route::middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/admin/users', UserManagement::class)->name('admin.users');
    Route::get('/admin/roles', RoleManagement::class)->name('admin.roles');
});
