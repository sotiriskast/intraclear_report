<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Merchant;
use App\Repositories\UserRepository;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        ];

        // Add conditional validation rules based on user type
        if ($request->user_type === 'admin') {
            $rules['role'] = 'required|exists:roles,id';
        } else if ($request->user_type === 'merchant') {
            $rules['merchant_id'] = 'required|exists:merchants,id';
            $rules['password_confirmation'] = 'required|same:password';
        }

        $validated = $request->validate($rules);

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

                $this->logger->log('info', 'Admin user created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);

                return redirect()->route('admin.users.index')
                    ->with('message', 'Admin user created successfully.');
            } else {
                // For merchant users, create with merchant_id and assign merchant role
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'user_type' => 'merchant',
                    'merchant_id' => $validated['merchant_id'],
                ]);

                // Assign merchant role
                $merchantRole = Role::findByName('merchant');
                $user->assignRole($merchantRole);

                $this->logger->log('info', 'Merchant user created successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'merchant_id' => $validated['merchant_id']
                ]);

                return redirect()->route('admin.users.index')
                    ->with('message', 'Merchant user created successfully.');
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error creating user: '.$e->getMessage(), [
                'email' => $validated['email']
            ]);

            return redirect()->back()
                ->with('error', 'Error creating user: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the user
     */
    public function edit(User $user)
    {
        $user->load('roles', 'merchant');
        $roles = Role::all();
        $merchants = Merchant::active()->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'merchants'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        // Base validation for all user types
        $rules = [
            'name' => 'required|min:3',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'user_type' => 'required|in:admin,merchant',
        ];

        // Add conditional validation rules based on user type
        if ($request->user_type === 'admin') {
            $rules['role'] = 'required|exists:roles,id';
        } else if ($request->user_type === 'merchant') {
            $rules['merchant_id'] = 'required|exists:merchants,id';
        }

        // Password is optional during update
        $rules['password'] = 'nullable|min:8';

        // Only require password confirmation if password is provided
        if ($request->filled('password') && $request->user_type === 'merchant') {
            $rules['password_confirmation'] = 'required|same:password';
        }

        $validated = $request->validate($rules);

        try {
            if ($validated['user_type'] === 'admin') {
                // For admin users, use the repository
                $roleId = (int)$validated['role'];

                // Update user_type if it's changed
                if ($user->user_type !== 'admin') {
                    $user->update(['user_type' => 'admin', 'merchant_id' => null]);
                }

                $this->userRepository->updateUser(
                    $user,
                    $validated['name'],
                    $validated['email'],
                    $validated['password'] ?? null,
                    $roleId
                );

                $this->logger->log('info', 'Admin user updated successfully', [
                    'user_id' => $user->id,
                    'email' => $validated['email']
                ]);
            } else {
                // For merchant users, update with merchant_id
                $updateData = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'user_type' => 'merchant',
                    'merchant_id' => $validated['merchant_id'],
                ];

                // Only update password if provided
                if (isset($validated['password'])) {
                    $updateData['password'] = Hash::make($validated['password']);
                }

                $user->update($updateData);

                // Ensure user has merchant role
                $merchantRole = Role::findByName('merchant');
                $user->syncRoles([$merchantRole]);

                $this->logger->log('info', 'Merchant user updated successfully', [
                    'user_id' => $user->id,
                    'email' => $validated['email'],
                    'merchant_id' => $validated['merchant_id']
                ]);
            }

            return redirect()->route('admin.users.index')
                ->with('message', 'User updated successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating user: '.$e->getMessage(), [
                'user_id' => $user->id
            ]);

            return redirect()->back()
                ->with('error', 'Error updating user: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        try {
            $email = $user->email;
            $userId = $user->id;
            $userType = $user->user_type;

            $this->userRepository->deleteUser($user);

            $this->logger->log('info', 'User deleted successfully', [
                'user_id' => $userId,
                'email' => $email,
                'user_type' => $userType
            ]);

            return redirect()->route('admin.users.index')
                ->with('message', 'User deleted successfully.');
        } catch (ValidationException $e) {
            return redirect()->route('admin.users.index')
                ->with('error', $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error deleting user: '.$e->getMessage(), [
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', 'Error deleting user: '.$e->getMessage());
        }
    }
}
