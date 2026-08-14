<?php

namespace App\Livewire\Admin\Access;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = ''; // '' | active | suspended

    // ----- Add / edit modal (manage users only) -----
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $fName = '';

    public string $fEmail = '';

    public string $fPhone = '';

    public string $fPassword = '';

    public string $fPin = '';

    public string $fStatus = 'active';

    /** @var array<int, string> role names */
    public array $fRoles = [];

    // ----- Reset Credentials modal (manage users OR reset credentials) -----
    public bool $showResetForm = false;

    public ?int $resettingId = null;

    public string $resettingName = '';

    public string $rPassword = '';

    public string $rPin = '';

    /**
     * Set once a credentials save (either modal) actually changed a
     * password/PIN — the modal switches to a plaintext confirmation panel
     * instead of just closing, since neither value can be read back once
     * hashed. Cleared whenever a modal is (re)opened.
     */
    public ?array $savedCredentials = null; // ['name' => ..., 'password' => ?string, 'pin' => ?string]

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && ($user->hasRole('super-admin') || $user->can('manage users') || $user->can('reset credentials')),
            403
        );
    }

    /** Full account management (create, edit, roles, status, delete) — narrower than being able to reach this screen at all. */
    private function canManageUsers(): bool
    {
        $user = auth()->user();

        return $user->hasRole('super-admin') || $user->can('manage users');
    }

    private function canResetCredentials(): bool
    {
        $user = auth()->user();

        return $this->canManageUsers() || $user->can('reset credentials');
    }

    public function updatedSearch(): void {}

    public function setStatus(string $s): void
    {
        $this->statusFilter = $this->statusFilter === $s ? '' : $s;
    }

    public function openCreate(): void
    {
        abort_unless($this->canManageUsers(), 403);

        $this->reset(['editingId', 'fName', 'fEmail', 'fPhone', 'fPassword', 'fPin', 'fRoles', 'savedCredentials']);
        $this->fStatus = 'active';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless($this->canManageUsers(), 403);

        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->fName = $user->name;
        $this->fEmail = $user->email;
        $this->fPhone = (string) $user->phone;
        $this->fStatus = $user->status ?: 'active';
        $this->fPassword = '';
        $this->fPin = '';
        $this->fRoles = $user->getRoleNames()->all();
        $this->savedCredentials = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->canManageUsers(), 403);

        $data = $this->validate([
            'fName' => ['required', 'string', 'max:120'],
            'fEmail' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($this->editingId)],
            'fPhone' => ['nullable', 'string', 'max:40'],
            'fStatus' => ['required', Rule::in(['active', 'suspended'])],
            'fPassword' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'fPin' => ['nullable', 'digits:4'],
            'fRoles' => ['array'],
            'fRoles.*' => [Rule::exists('roles', 'name')],
        ], [], [
            'fName' => 'name', 'fEmail' => 'email', 'fPhone' => 'phone', 'fPassword' => 'password', 'fPin' => 'PIN',
        ]);

        // Only a super-admin may grant the super-admin role (no privilege escalation).
        if (in_array('super-admin', $this->fRoles, true) && ! auth()->user()->hasRole('super-admin')) {
            abort(403, 'Only a super-admin can assign the super-admin role.');
        }

        // A PIN alone identifies a staffer at tablet sign-in (no email
        // typed) — two active accounts sharing one would make that lookup
        // ambiguous.
        if (! empty($data['fPin']) && User::findActiveByPin($data['fPin'], excludeUserId: $this->editingId)) {
            $this->addError('fPin', 'This PIN is already in use by another staff member.');

            return;
        }

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $user->name = $data['fName'];
        $user->email = $data['fEmail'];
        $user->phone = $data['fPhone'] ?: null;
        $user->status = $data['fStatus'];
        if (! empty($data['fPassword'])) {
            $user->password = $data['fPassword']; // hashed via the model cast
        }
        if (! empty($data['fPin'])) {
            $user->pin = $data['fPin']; // hashed via the model cast
        }
        if (! $this->editingId) {
            $user->email_verified_at = now();
        }
        $user->save();

        $user->syncRoles($this->fRoles);

        session()->flash('access_status', 'Account “'.$user->name.'” saved.');

        // A password/PIN was just set — neither can be read back once
        // hashed, so this is the only chance to hand it to whoever's
        // relaying it to the staffer.
        if (! empty($data['fPassword']) || ! empty($data['fPin'])) {
            $this->savedCredentials = [
                'name' => $user->name,
                'password' => $data['fPassword'] ?: null,
                'pin' => $data['fPin'] ?: null,
            ];

            return;
        }

        $this->showForm = false;
    }

    public function toggleStatus(int $id): void
    {
        abort_unless($this->canManageUsers(), 403);

        $user = User::findOrFail($id);
        abort_if($user->id === auth()->id(), 403, 'You can’t change your own status.');

        $user->status = $user->status === 'active' ? 'suspended' : 'active';
        $user->save();
        session()->flash('access_status', $user->name.' is now '.$user->status.'.');
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManageUsers(), 403);

        $user = User::findOrFail($id);

        abort_if($user->id === auth()->id(), 403, 'You can’t delete your own account.');

        if ($user->hasRole('super-admin') && User::role('super-admin')->count() <= 1) {
            abort(403, 'At least one super-admin must remain.');
        }

        $user->delete();
        session()->flash('access_status', 'Account deleted.');
    }

    /**
     * Reset Credentials — a Manager's own narrow lane into this screen:
     * change a staffer's tablet password/PIN without touching their name,
     * email, roles, or status (those stay behind {@see canManageUsers()}).
     */
    public function openReset(int $id): void
    {
        abort_unless($this->canResetCredentials(), 403);

        $user = User::findOrFail($id);
        $this->resettingId = $user->id;
        $this->resettingName = $user->name;
        $this->rPassword = '';
        $this->rPin = '';
        $this->savedCredentials = null;
        $this->resetValidation();
        $this->showResetForm = true;
    }

    /** Fills the password field with a fresh random one — the manager doesn't have to invent one. */
    public function generatePassword(): void
    {
        abort_unless($this->canResetCredentials(), 403);

        // Readable-but-strong: no ambiguous look-alike characters (0/O, 1/l/I).
        $this->rPassword = Str::password(10, symbols: false, numbers: true, letters: true, spaces: false);
    }

    /** Fills the PIN field with a fresh random 4-digit code no other active staffer already holds. */
    public function generatePin(): void
    {
        abort_unless($this->canResetCredentials(), 403);

        do {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::findActiveByPin($pin, excludeUserId: $this->resettingId));

        $this->rPin = $pin;
    }

    public function saveReset(): void
    {
        abort_unless($this->canResetCredentials(), 403);
        abort_unless($this->resettingId, 404);

        $data = $this->validate([
            'rPassword' => ['nullable', 'string', 'min:8'],
            'rPin' => ['nullable', 'digits:4'],
        ], [], [
            'rPassword' => 'password', 'rPin' => 'PIN',
        ]);

        if (empty($data['rPassword']) && empty($data['rPin'])) {
            $this->addError('rPassword', 'Set a new password, a new PIN, or both.');

            return;
        }

        if (! empty($data['rPin']) && User::findActiveByPin($data['rPin'], excludeUserId: $this->resettingId)) {
            $this->addError('rPin', 'This PIN is already in use by another staff member.');

            return;
        }

        $user = User::findOrFail($this->resettingId);
        if (! empty($data['rPassword'])) {
            $user->password = $data['rPassword'];
        }
        if (! empty($data['rPin'])) {
            $user->pin = $data['rPin'];
        }
        $user->save();

        session()->flash('access_status', $user->name.'’s credentials were updated.');

        $this->savedCredentials = [
            'name' => $user->name,
            'password' => $data['rPassword'] ?: null,
            'pin' => $data['rPin'] ?: null,
        ];
    }

    public function closeModals(): void
    {
        $this->showForm = false;
        $this->showResetForm = false;
        $this->savedCredentials = null;
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
            'canManageUsers' => $this->canManageUsers(),
        ])->layout('components.admin.app', [
            'title' => 'Users & Staff',
            'subtitle' => 'Manage portal accounts and their roles',
        ]);
    }
}
