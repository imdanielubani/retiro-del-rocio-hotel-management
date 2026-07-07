<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    /**
     * Log the admin user out of the application.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Surface a one-time toast on the login screen.
        $request->session()->flash('toast', [
            'type' => 'success',
            'message' => 'You have been signed out successfully.',
        ]);

        return redirect()->route('admin.login');
    }
}
