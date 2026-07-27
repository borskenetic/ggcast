<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class AdminShell
{
    /**
     * @return array<string, mixed>|null
     */
    public static function authUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->fullName(),
            'email' => $user->email,
            'role' => $user->role,
            'isAdmin' => $user->role === 'admin',
            'initials' => $user->initials(),
            'avatarUrl' => null,
        ];
    }

    /**
     * Props consumed by the React admin shell on Blade pages.
     *
     * @return array<string, mixed>
     */
    public static function pageProps(Request $request): array
    {
        $user = $request->user();

        return [
            'auth' => [
                'user' => self::authUser($user),
            ],
            'routeName' => $request->route()?->getName(),
            'adminActivity' => null,
            'branding' => Branding::forShell(),
        ];
    }
}
