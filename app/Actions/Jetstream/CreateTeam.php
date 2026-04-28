<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Laravel\Jetstream\Contracts\CreatesTeams;
use Laravel\Jetstream\Events\AddingTeam;
use Laravel\Jetstream\Jetstream;

class CreateTeam implements CreatesTeams
{
    /**
     * Validate and create a new team for the given user.
     *
     * @param  array<string, string>  $input
     */
    public function create(User $user, array $input): Team
    {
        Gate::forUser($user)->authorize('create', Jetstream::newTeamModel());

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('createTeam');

        AddingTeam::dispatch($user);

        if($user->isTeamManager()) {
            // Create the team directly
            $team = Team::create([
                'user_id' => $user->id, // still mark who created it
                'name' => $input['name'],
                'personal_team' => false,
            ]);
    
            // Attach the current user with a custom role instead of 'owner'
            $team->users()->attach($user, ['role' => $user->isTeamManager() ? 'manager' : 'admin']);
    
            // Switch the user into this team
            $user->switchTeam($team);
    
            return $team;
        }

        $user->switchTeam($team = $user->ownedTeams()->create([
            'name' => $input['name'],
            'personal_team' => false,
        ]));

        return $team;
    }
}
