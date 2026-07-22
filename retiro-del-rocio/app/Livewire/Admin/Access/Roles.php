<?php

namespace App\Livewire\Admin\Access;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Roles extends Component
{
    /** Roles that can't be renamed or deleted. */
    private const PROTECTED = ['super-admin'];

    public string $search = '';

    // ----- Role add/edit modal -----
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $fName = '';

    /** @var array<int, string> selected permission names */
    public array $fPermissions = [];

    // ----- Add permission modal -----
    public bool $showPermForm = false;
    public string $pName = '';

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasRole('super-admin') || $user->can('manage settings')),
            403
        );
    }

    public function updatedSearch(): void {}

    // ----- Roles -----

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fPermissions']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->editingId = $role->id;
        $this->fName = $role->name;
        $this->fPermissions = $role->permissions->pluck('name')->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $current = $this->editingId ? Role::find($this->editingId) : null;
        $isProtected = $current && in_array($current->name, self::PROTECTED, true);

        $rules = ['fName' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9\- ]+$/i']];
        if (! $isProtected) {
            $rules['fName'][] = Rule::unique('roles', 'name')->ignore($this->editingId);
        }
        $this->validate($rules, [], ['fName' => 'role name']);

        $name = $isProtected
            ? $current->name
            : Str::of($this->fName)->lower()->trim()->replaceMatches('/\s+/', '-')->value();

        $role = $current
            ? tap($current)->update(['name' => $name])
            : Role::create(['name' => $name, 'guard_name' => 'web']);

        // super-admin always holds every permission.
        $role->syncPermissions(
            $role->name === 'super-admin' ? Permission::all() : $this->fPermissions
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->showForm = false;
        session()->flash('access_status', 'Role “'.$role->name.'” saved.');
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);
        abort_if(in_array($role->name, self::PROTECTED, true), 403);
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        session()->flash('access_status', 'Role deleted.');
    }

    /** Select/deselect every permission in a group at once. */
    public function toggleGroup(array $names): void
    {
        $allOn = collect($names)->every(fn ($n) => in_array($n, $this->fPermissions, true));
        $this->fPermissions = $allOn
            ? array_values(array_diff($this->fPermissions, $names))
            : array_values(array_unique([...$this->fPermissions, ...$names]));
    }

    // ----- Permissions -----

    public function openCreatePermission(): void
    {
        $this->reset(['pName']);
        $this->resetValidation();
        $this->showPermForm = true;
    }

    public function savePermission(): void
    {
        $this->validate(
            ['pName' => ['required', 'string', 'max:80', Rule::unique('permissions', 'name')]],
            [],
            ['pName' => 'permission name']
        );

        Permission::create(['name' => trim($this->pName), 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->showPermForm = false;
        session()->flash('access_status', 'Permission added.');
    }

    public function render()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();

        $permissions = Permission::orderBy('name')->get();
        $grouped = $permissions->groupBy(fn (Permission $p) => $this->groupOf($p->name));

        $stats = [
            ['label' => 'Roles', 'value' => Role::count(), 'sub' => 'Defined roles', 'accent' => '#f38c00'],
            ['label' => 'Permissions', 'value' => $permissions->count(), 'sub' => 'Available permissions', 'accent' => '#0891b2'],
            ['label' => 'Portal Users', 'value' => User::count(), 'sub' => 'Accounts', 'accent' => '#7c3aed'],
        ];

        return view('admin.access.roles', [
            'roles' => $roles,
            'grouped' => $grouped,
            'stats' => $stats,
            'protected' => self::PROTECTED,
        ])->layout('components.admin.app', [
            'title' => 'Roles & Permissions',
            'subtitle' => 'Manage roles and what each can access',
        ]);
    }

    private function groupOf(string $name): string
    {
        if (str_contains($name, '.')) {
            return ucfirst(explode('.', $name)[0]);
        }
        if (str_starts_with($name, 'manage ')) {
            return ucfirst(substr($name, 7));
        }

        return 'General';
    }
}
