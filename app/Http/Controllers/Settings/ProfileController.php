<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\RetireUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Close the user's account. The row is retired rather than deleted: what
     * they registered and approved stays readable, under nobody's name. Tools
     * they owned pass to their department manager, or to an administrator.
     */
    public function destroy(ProfileDeleteRequest $request, RetireUser $retire): RedirectResponse
    {
        $user = $request->user();

        // Nobody could approve anything afterwards, and nobody could hand the
        // role back out either.
        abort_if(
            $user->isAdmin() && User::query()->admins()->count() === 1,
            422,
            '最後のシステム管理者はアカウントを閉じられません。先に別の管理者を任命してください。',
        );

        Auth::logout();

        $retire->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
