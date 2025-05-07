<?php

namespace App\Http\Controllers;

use App\Repositories\RoleRepository;
use App\Services\DynamicLogger;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected $roleRepository;
    protected $logger;

    /**
     * Constructor to inject dependencies
     */
    public function __construct(RoleRepository $roleRepository, DynamicLogger $logger)
    {
        $this->roleRepository = $roleRepository;
        $this->logger = $logger;
    }

    /**
     * Display a listing of roles
     */
    public function index()
    {
        $roles = Role::with('permissions')->paginate(10);
        $permissions = Permission::all();

        return view('admin.roles.index', compact('roles', 'permissions'));
    }

    /**
     * Show the form for creating a new role
     */
    public function create()
    {
        $permissions = Permission::all();
        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        try {
            $role = $this->roleRepository->createRole(
                $validated['name'],
                $validated['permissions'] ?? []
            );

            $this->logger->log('info', 'Role created successfully', [
                'role_id' => $role->id,
                'name' => $role->name
            ]);

            return redirect()->route('admin.roles.index')
                ->with('message', 'Role created successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error creating role: '.$e->getMessage(), [
                'name' => $validated['name']
            ]);

            return redirect()->back()
                ->with('error', 'Error creating role: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the role
     */
    public function edit(Role $role)
    {
        $role->load('permissions');
        $permissions = Permission::all();
        $selectedPermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'selectedPermissions'));
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,'.$role->id],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        try {
            $this->roleRepository->updateRole(
                $role,
                $validated['name'],
                $validated['permissions'] ?? []
            );

            $this->logger->log('info', 'Role updated successfully', [
                'role_id' => $role->id,
                'name' => $validated['name']
            ]);

            return redirect()->route('admin.roles.index')
                ->with('message', 'Role updated successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error updating role: '.$e->getMessage(), [
                'role_id' => $role->id
            ]);

            return redirect()->back()
                ->with('error', 'Error updating role: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Role $role)
    {
        try {
            $this->roleRepository->deleteRole($role);

            $this->logger->log('info', 'Role deleted successfully', [
                'role_id' => $role->id,
                'name' => $role->name
            ]);

            return redirect()->route('admin.roles.index')
                ->with('message', 'Role deleted successfully.');
        } catch (\Exception $e) {
            $this->logger->log('error', 'Error deleting role: '.$e->getMessage(), [
                'role_id' => $role->id
            ]);

            return redirect()->route('admin.roles.index')
                ->with('error', $e->getMessage());
        }
    }
}
