<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'is_admin',
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
            'is_admin' => 'boolean',
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


    /**
     * The display label for this user's role on their primary team.
     *
     * Memoised: resolving it costs two or three queries, and it is read inside
     * the team-members loop, where every iteration asks the same signed-in user
     * for the same answer. Caching is safe because the value only changes when
     * team membership does, which cannot happen mid-request.
     */
    protected function roleName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->is_admin) {
                    return 'Administrator';
                }

                $team = $this->primaryTeam();

                if (! $team) {
                    return 'n/a';
                }

                $role = $this->teamRole($team);

                if (! $role) {
                    return 'n/a';
                }

                // Jetstream reports a team's owner as the synthetic "owner"
                // role; this app presents that as the administrator label.
                $key = $role->key === TeamRole::Owner->value
                    ? TeamRole::Admin->value
                    : $role->key;

                return Jetstream::findRole($key)?->name ?? 'n/a';
            }
        )->shouldCache();
    }

    /**
     * The team a user is presented as belonging to: the first they joined, or
     * failing that the first non-personal team they own.
     */
    private function primaryTeam(): ?Team
    {
        return $this->teams()->oldest('created_at')->first()
            ?? $this->ownedTeams()->where('personal_team', false)->oldest('created_at')->first();
    }

    /**
     * Check if the user holds the manager role on any team.
     */
    public function isTeamManager(): bool
    {
        return $this->teams()->wherePivot('role', TeamRole::Manager->value)->exists();
    }

    /**
     * The manager of the given team, or null when none is assigned.
     *
     * A team has at most one manager, so the ordering only matters for data
     * that predates that constraint; it keeps the result stable either way.
     */
    public static function getTeamManager(int $teamId): ?User
    {
        return User::whereHas('teams', function ($query) use ($teamId) {
            $query->where('teams.id', $teamId)
                ->where('team_user.role', TeamRole::Manager->value);
        })->orderBy('id')->first();
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }
}
