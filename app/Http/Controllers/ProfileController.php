<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesIdentity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use ManagesIdentity;

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'phone' => $user->phone,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
            'avatarPresets' => User::avatarPresets(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->normalizeIdentity($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => $this->phoneRules($user->id),
            'email' => $this->emailRules($user->id),
            ...$this->avatarRules(),
        ], $this->identityMessages());

        $user->fill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
        ])->save();
        $this->applyAvatar($request, $user);

        return back()->with('success', 'Đã cập nhật hồ sơ.');
    }
}
