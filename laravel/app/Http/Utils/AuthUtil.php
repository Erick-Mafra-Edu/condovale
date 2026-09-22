<?php

namespace App\Http\Utils;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Support\Facades\Auth;

/**
 * Typed accessors for the session guard.
 *
 * Auth::guard() is typed as the Guard contract, whose user() returns
 * Authenticatable instead of the model, which hides real errors behind false
 * "undefined method" warnings. The guard name is always explicit: relying on
 * the default guard makes the behaviour depend on the environment file.
 */
class AuthUtil
{
    public static function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = Auth::guard('web');

        return $guard;
    }

    public static function user(): ?User
    {
        /** @var User|null $user */
        $user = self::guard()->user();

        return $user;
    }

    /**
     * Id of the authenticated user, used as the author of every log entry.
     */
    public static function id(): ?int
    {
        return self::user()?->id;
    }
}
