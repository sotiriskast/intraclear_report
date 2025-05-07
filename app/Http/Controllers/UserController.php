<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

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
        $users = User::with('roles')->paginate(10);
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|min:3',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required',
        ]);

        try {
            // Convert role to integer since your repository implementation expects an int
            $roleId = (int)$validated['role'];

            $user = $this->userRepository->createUser(
                $validated['name'],
                $validated['email'],
                $validated['password'],
                $roleId
            );

            $this->logger->log('info', 'User created successfully', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return redirect()->route('admin.users.index')
                ->with('message', 'User created successfully.');
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
        $user->load('roles');
        $roles = Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|min:3',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'nullable|min:8',
            'role' => 'required',
        ]);

        try {
            // Convert role to integer since your repository implementation expects an int
            $roleId = (int)$validated['role'];

            $this->userRepository->updateUser(
                $user,
                $validated['name'],
                $validated['email'],
                $validated['password'], // This will be null if password field is empty
                $roleId
            );

            $this->logger->log('info', 'User updated successfully', [
                'user_id' => $user->id,
                'email' => $validated['email']
            ]);

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

            $this->userRepository->deleteUser($user);

            $this->logger->log('info', 'User deleted successfully', [
                'user_id' => $userId,
                'email' => $email
            ]);

            return redirect()->route('admin.users.index')
                ->with('message', 'User deleted successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation exceptions specifically (like attempts to delete last super-admin)
            $this->logger->log('error', 'Validation error when deleting user: '.$e->getMessage(), [
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', $e->validator->getMessageBag()->first());
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error deleting user: '.$e->getMessage(), [
                'user_id' => $user->id
            ]);

            return redirect()->route('admin.users.index')
                ->with('error', $e->getMessage());
        }
    }
}
