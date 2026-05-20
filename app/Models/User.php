<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
// use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Jetstream\Jetstream;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn () => collect(explode(' ', $this->name))
                ->map(fn ($segment) => strtoupper(substr($segment, 0, 1)))
                ->join('')
        );
    }


    protected function roleName(): Attribute
    {
        $firstTeam = $this->teams()->orderBy('created_at', 'asc')->first();
        $role = $this->teamRole($firstTeam);

        if($role->key === 'owner') {
            $role->key = 'admin';
        }

        return Attribute::make(
            get: fn () => $role
                ? Jetstream::findRole($role->key)->name
                : 'n/a'
        );
    }

    /**
     * Check if the user is a manager in the given team.
     */
    public function isTeamManager(): bool
    {
        $firstTeam = $this->teams()->orderBy('created_at', 'asc')->first();

        if (! $firstTeam) {
            return false;
        }

        $role = $this->teamRole($firstTeam);

        return $role && $role->key === 'manager';
    }

    public static function getTeamManager(int $teamId): ?User
    {
        return User::whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.id', $teamId)
                ->where('team_user.role', 'manager');
        })->first();
    }
}
