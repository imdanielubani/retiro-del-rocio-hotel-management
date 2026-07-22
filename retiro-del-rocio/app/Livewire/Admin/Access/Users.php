<?php

namespace App\Livewire\Admin\Access;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    public string $search = '';
    public string $roleFilter = '';
    public string $statusFilter = ''; // '' | active | suspended

    // ----- Add / edit modal -----
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $fName = '';
    public string $fEmail = '';
    public string $fPhone = '';
    public string $fPassword = '';
    public string $fStatus = 'active';

    /** @var array<int, string> role names */
    public array $fRoles = [];

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasRole('super-admin') || $user->can('manage users')),
            403
        );
    }

    public function updatedSearch(): void {}

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'fName', 'fEmail', 'fPhone', 'fPassword', 'fRoles']);
        $this->fStatus = 'active';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->fName = $user->name;
        $this->fEmail = $user->email;
        $this->fPhone = (string) $user->phone;
        $this->fStatus = $user->status ?: 'active';
        $this->fPassword = '';
        $this->fRoles = $user->getRoleNames()->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fEmail' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->editingId)],
            'fPhone' => ['nullable', 'string', 'max:40'],
            'fStatus' => ['required', Rule::in(['active', 'suspended'])],
            'fPassword' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'fRoles' => ['array'],
            'fRoles.*' => [Rule::exists('roles', 'name')],
        ], [], [
            'fName' => 'name', 'fEmail' => 'email', 'fPhone' => 'phone', 'fPassword' => 'password',
        ]);

        // Only a super-admin may grant the super-admin role (no privilege escalation).
        if (in_array('super-admin', $this->fRoles, true) && ! auth()->user()->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can assign the super-admin role.');
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User();
        $user->name = $data['fName'];
        $user->email = $data['fEmail'];
        $user->phone = $data['fPhone'] ?: null;
        $user->status = $data['fStatus'];
        if (! empty($data['fPassword'])) {
            $user->password = $data['fPassword']; // hashed via the model cast
        }
        if (! $this->editingId) {
            $user->email_verified_at = now();
        }
        $user->save();

        $user->syncRoles($this->fRoles);

        $this->showForm = false;
        session()->flash('access_status', 'Account “'.$user->name.'” saved.');
    }

    public function toggleStatus(int $id): void
    {
        $user = User::findOrFail($id);
        abort_if($user->id === auth()->id(), 403, 'You can’t change your own status.');

        $user->status = $user->status === 'active' ? 'suspended' : 'active';
        $user->save();
        session()->flash('access_status', $user->name.' is now '.$user->status.'.');
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        abort_if($user->id === auth()->id(), 403, 'You can’t delete your own account.');

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            abort(403, 'At least one super-admin must remain.');
        }

        $user->delete();
        session()->flash('access_status', 'Account deleted.');
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->roleFilter, fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $this->roleFilter)))
            ->orderBy('name')
            ->get();

        $roles = Role::orderBy('name')->get();

        $stats = [
            ['label' => 'Total Users', 'value' => User::count(), 'sub' => 'All accounts', 'accent' => '#f38c00'],
            ['label' => 'Active', 'value' => User::where('status', 'active')->count(), 'sub' => 'Can sign in', 'accent' => '#16a34a'],
            ['label' => 'Suspended', 'value' => User::where('status', 'suspended')->count(), 'sub' => 'Access revoked', 'accent' => '#dc2626'],
        ];

        return view('admin.access.users', [
            'users' => $users,
            'roles' => $roles,
            'stats' => $stats,
        ])->layout('components.admin.app', [
            'title' => 'Users & Staff',
            'subtitle' => 'Manage portal accounts and their roles',
        ]);
    }
}
