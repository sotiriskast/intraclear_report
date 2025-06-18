<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\User;
use App\Http\Requests\StoreMerchantUserRequest;
use App\Http\Requests\UpdateMerchantUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MerchantUserController extends Controller
{
    /**
     * Display a listing of merchant users.
     */
    public function index(Request $request)
    {
        $query = User::with(['merchant'])
            ->where('user_type', 'merchant')
            ->latest();

        // Filter by merchant if provided
        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->merchant_id);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('merchant', function($merchantQuery) use ($search) {
                        $merchantQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $merchantUsers = $query->paginate(15);
        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.merchant-users.index', compact('merchantUsers', 'merchants'));
    }

    /**
     * Show the form for creating a new merchant user.
     */
    public function create()
    {
        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.merchant-users.create', compact('merchants'));
    }

    /**
     * Store a newly created merchant user in storage.
     */
    public function store(StoreMerchantUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'user_type' => 'merchant',
            'merchant_id' => $request->merchant_id,
        ]);

        // Assign merchant role
        $merchantRole = Role::findByName('merchant');
        $user->assignRole($merchantRole);

        return redirect()
            ->route('admin.merchant-users.index')
            ->with('success', 'Merchant user created successfully.');
    }

    /**
     * Display the specified merchant user.
     */
    public function show(User $merchantUser)
    {
        // Ensure this is a merchant user
        if ($merchantUser->user_type !== 'merchant') {
            abort(404);
        }

        $merchantUser->load('merchant');

        return view('admin.merchant-users.show', compact('merchantUser'));
    }

    /**
     * Show the form for editing the specified merchant user.
     */
    public function edit(User $merchantUser)
    {
        // Ensure this is a merchant user
        if ($merchantUser->user_type !== 'merchant') {
            abort(404);
        }

        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.merchant-users.edit', compact('merchantUser', 'merchants'));
    }

    /**
     * Update the specified merchant user in storage.
     */
    public function update(UpdateMerchantUserRequest $request, User $merchantUser)
    {
        // Ensure this is a merchant user
        if ($merchantUser->user_type !== 'merchant') {
            abort(404);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'merchant_id' => $request->merchant_id,
        ];

        // Only update password if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $merchantUser->update($updateData);

        return redirect()
            ->route('admin.merchant-users.index')
            ->with('success', 'Merchant user updated successfully.');
    }

    /**
     * Remove the specified merchant user from storage.
     */
    public function destroy(User $merchantUser)
    {
        // Ensure this is a merchant user
        if ($merchantUser->user_type !== 'merchant') {
            abort(404);
        }

        $merchantUser->delete();

        return redirect()
            ->route('admin.merchant-users.index')
            ->with('success', 'Merchant user deleted successfully.');
    }
}
