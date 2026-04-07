<?php

namespace App\Auth;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Satisfies Laravel's default `users` auth provider. Real users are in Firestore (customers, admins, drivers).
 */
class FrameworkUser extends Authenticatable
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
