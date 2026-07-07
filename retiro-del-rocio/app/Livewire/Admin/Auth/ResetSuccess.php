<?php

namespace App\Livewire\Admin\Auth;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.admin.guest')]
class ResetSuccess extends Component
{
    public string $name = '';

    public string $email = '';

    /**
     * Only reachable immediately after a successful password reset.
     */
    public function mount(): void
    {
        $email = (string) session('password_reset_success_email', '');

        if ($email === '') {
            $this->redirectRoute('admin.login', navigate: true);

            return;
        }

        // Consume the one-time success flag.
        session()->forget('password_reset_success_email');

        $this->email = $email;
        $this->name = User::where('email', $email)->value('name') ?? 'Admin';
    }

    public function render()
    {
        return view('admin.auth.reset-success');
    }
}
