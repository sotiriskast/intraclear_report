<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Merchant;
use App\Repositories\UserRepository;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    protected $userRepository;
    protected $logger;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(UserRepository $userRepository, DynamicLogger $logger)
    {
        $this->userRepository = $userRepository;
        $this->logger = $logger;
    }

    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::with('roles', 'merchant')->paginate(10);
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles = Role::all();
        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'merchants'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        // Base validation for all user types
        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'user_type' => 'required|in:admin,merchant',
            'active' => 'boolean',
        ];

        // Add conditional validation rules based on user type
        if ($request->user_type === 'admin') {
            $rules['role'] = 'required|exists:roles,id';
        } else if ($request->user_type === 'merchant') {
            $rules['merchant_id'] = 'required|exists:merchants,id';
            $rules['password_confirmation'] = 'required|same:password';
        }

        $validated = $request->validate($rules);
        $validated['active'] = $validated['active'] ?? true; // Default to active

        try {
            if ($validated['user_type'] === 'admin') {
                // For admin users, use the repository
                $roleId = (int)$validated['role'];

                $user = $this->userRepository->createUser(
                    $validated['name'],
                    $validated['email'],
                    $validated['password'],
                    $roleId
                );

                // Update active status if provided
                if (isset($validated['active'])) {
                    $user->update(['active' => $validated['active']]);
                }

                $this->logger->log('info', 'Admin user created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'active' => $user->active
                ]);

                return redirect()->route('admin.users.index')
                    ->with('message', 'Admin user created successfully.');

            } else if ($validated['user_type'] === 'merchant') {
                // For merchant users, create directly
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'user_type' => $validated['user_type'],
                    'merchant_id' => $validated['merchant_id'],
                    'active' => $validated['active'],
                ]);

                $this->logger->log('info', 'Merchant user created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'merchant_id' => $user->merchant_id,
                    'active' => $user->active
                ]);

                return redirect()->route('admin.users.index')
                    ->with('message', 'Merchant user created successfully.');
            }

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to create user', [
                'error' => $e->getMessage(),
                'email' => $validated['email']
            ]);

            return back()->withInput()
                ->with('error', 'Failed to create user. Please try again.');
        }
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'merchants'));
    }

    /**
     * Update the specified user in storage
     */
    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => 'required|min:3',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'password' => 'nullable|min:8',
            'user_type' => 'required|in:admin,merchant',
            'active' => 'boolean',
        ];

        if ($request->user_type === 'admin') {
            $rules['role'] = 'required|exists:roles,id';
        } else if ($request->user_type === 'merchant') {
            $rules['merchant_id'] = 'required|exists:merchants,id';
            if ($request->filled('password')) {
                $rules['password_confirmation'] = 'required|same:password';
            }
        }

        $validated = $request->validate($rules);

        try {
            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'user_type' => $validated['user_type'],
                'active' => $validated['active'] ?? $user->active,
            ];

            // Update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            // Handle merchant_id
            if ($validated['user_type'] === 'merchant') {
                $updateData['merchant_id'] = $validated['merchant_id'];
            } else {
                $updateData['merchant_id'] = null;
            }

            $user->update($updateData);

            // Handle role assignment for admin users
            if ($validated['user_type'] === 'admin' && isset($validated['role'])) {
                $role = Role::findById($validated['role']);
                $user->syncRoles([$role]);
            } else {
                $user->syncRoles([]);
            }

            $this->logger->log('info', 'User updated successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'active' => $user->active
            ]);

            return redirect()->route('admin.users.index')
                ->with('message', 'User updated successfully.');

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to update user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->withInput()
                ->with('error', 'Failed to update user. Please try again.');
        }
    }

    /**
     * Remove the specified user from storage
     */
    public function destroy(User $user)
    {
        try {
            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return back()->with('error', 'You cannot delete your own account.');
            }

            $this->logger->log('info', 'User deleted', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            $user->delete();

            return redirect()->route('admin.users.index')
                ->with('message', 'User deleted successfully.');

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to delete user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Failed to delete user. Please try again.');
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(User $user): JsonResponse
    {
        try {
            // Prevent self-deactivation
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.'
                ], 403);
            }

            $oldStatus = $user->active;
            $user->toggleStatus();

            // Log the action
            $this->logger->log('info', $user->active ? 'User activated' : 'User deactivated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_status' => $oldStatus,
                'new_status' => $user->active,
                'changed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => $user->active ? 'User activated successfully.' : 'User deactivated successfully.',
                'status' => $user->active,
                'status_text' => $user->status_text,
                'status_badge_color' => $user->status_badge_color
            ]);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to toggle user status', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status. Please try again.'
            ], 500);
        }
    }

    /**
     * Activate user
     */
    public function activate(User $user): JsonResponse
    {
        try {
            if ($user->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already active.'
                ], 400);
            }

            $user->activate();

            $this->logger->log('info', 'User activated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'changed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully.',
                'status' => true,
                'status_text' => 'Active',
                'status_badge_color' => 'bg-green-100 text-green-800'
            ]);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to activate user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user. Please try again.'
            ], 500);
        }
    }

    /**
     * Deactivate user
     */
    public function deactivate(User $user): JsonResponse
    {
        try {
            // Prevent self-deactivation
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.'
                ], 403);
            }

            if (!$user->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already inactive.'
                ], 400);
            }

            $user->deactivate();

            $this->logger->log('info', 'User deactivated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'changed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully.',
                'status' => false,
                'status_text' => 'Inactive',
                'status_badge_color' => 'bg-red-100 text-red-800'
            ]);

        } catch (\Exception $e) {
            $this->logger->log('error', 'Failed to deactivate user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user. Please try again.'
            ], 500);
        }
    }
}
