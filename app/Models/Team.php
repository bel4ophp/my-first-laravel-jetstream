<?php

namespace App\Models;

use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    /**
     * IDs of everyone on the team, its owner included.
     *
     * The single definition of "who is on this team". Jetstream's users()
     * relation covers only the team_user pivot, so it silently omits the owner
     * and must not be used for scoping.
     *
     * @return Collection<int, int>
     */
    public function memberIds(): Collection
    {
        return $this->allUsers()->pluck('id');
    }

    /**
     * The team's manager, if one has been assigned.
     *
     * A team has at most one manager. That invariant is enforced when roles are
     * assigned (AddTeamMember, InviteTeamMember, UpdateTeamMemberRole and
     * StoreUserRequest) and is what makes leave approval routing unambiguous.
     */
    public function manager(): ?User
    {
        return User::getTeamManager($this->id);
    }

    /**
     * Whether the team already has a manager, ignoring the given user.
     *
     * Pass the member being promoted so re-saving an existing manager's role
     * doesn't trip the check against themselves.
     */
    public function hasManagerBesides(?int $exceptUserId = null): bool
    {
        return $this->users()
            ->wherePivot('role', TeamRole::Manager->value)
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }
}
