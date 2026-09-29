<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user The route is behind auth, so this is never null. */
        $user = Auth::user();

        $users = User::query()
            // Mirrors UserPolicy::ownsATeamOf() so the list shows exactly the
            // users its row actions will allow. The previous condition keyed
            // off isTeamManager(), which is false for an owner — they hold no
            // team_user row — so the list came back unscoped.
            ->unless($user->is_admin, fn (Builder $query) => $query->whereHas(
                'teams',
                fn (Builder $teams) => $teams->whereIn('teams.id', $user->ownedTeams()->select('teams.id'))
            ))
            ->when(
                $request->string('search')->trim()->value(),
                fn (Builder $query, string $search) => $query->where(
                    // A leading wildcard cannot use an index. Acceptable at
                    // this scale; revisit with a fulltext index if the users
                    // table grows.
                    fn (Builder $match) => $match->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                )
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $teams = Team::where('personal_team', false)->get();
        $roles = Role::all();

        return view('users.create', compact('teams', 'roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt(Str::random(16)),
        ]);

        // Assign user to selected team
        if ($validated['team_id'] ?? null) {
            $selectedTeam = Team::find($validated['team_id']);
            if ($selectedTeam) {
                $roleKey = $validated['role'];
                $selectedTeam->users()->attach($user, ['role' => $roleKey]);

                // Use Jetstream’s built-in helper
                $user->switchTeam($selectedTeam);
            }
        }

        // Generate password reset token
        $token = Password::createToken($user);

        // Send password reset notification
        $user->sendPasswordResetNotification($token);

        return redirect()->route('users.index')->with('success', 'User created successfully. Password reset link sent to their email.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
