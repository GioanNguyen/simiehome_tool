<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    /**
     * Admin-created accounts are marked verified immediately — there's no
     * outbound mail configured for this internal tool, so a self-service
     * "verify your email" step would just be a dead end.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:'.implode(',', User::ROLES)],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
        ]);

        // Not mass-assignable on purpose (verification status shouldn't be
        // settable through any form input) — set directly instead.
        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('admin.users.index')->with('status', "Đã tạo tài khoản cho {$validated['name']}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // An admin locking themselves out of the only account that can
        // grant admin access is the one mistake this screen can't let
        // happen silently — they have to ask another admin to do it, or
        // change it directly in the database.
        if ($user->id === $request->user()->id) {
            return redirect()
                ->route('admin.users.index')
                ->withErrors(['role' => 'Không thể tự đổi vai trò của chính mình. Nhờ một admin khác đổi giúp.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', User::ROLES)],
        ]);

        $user->update(['role' => $validated['role']]);

        return redirect()->route('admin.users.index')->with('status', "Đã cập nhật vai trò của {$user->name}.");
    }
}
