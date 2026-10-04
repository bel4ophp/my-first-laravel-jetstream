<?php

namespace App\Http\Controllers;

use App\Actions\Jetstream\ProvisionTeamMember;
use App\Enums\TeamRole;
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
use Laravel\Jetstream\Contracts\DeletesUsers;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        // Only the admin reaches this screen (UserPolicy), so the list is unscoped.
        $users = User::query()
            ->with('currentTeam')
            ->when(
                $request->string('search')->trim()->value(),
                fn (Builder $query, string $search) => $query->where(
                    // A leading wildcard cannot use an index. Acceptable at
                    // this scale; revisit with a fulltext index if the users
                    // table grows. The typed text is escaped so % and _ are
                    // searched for literally rather than acting as wildcards.
                    fn (Builder $match) => $match->whereLike('name', '%'.$this->escapeLike($search).'%')
                        ->orWhereLike('email', '%'.$this->escapeLike($search).'%')
                )
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users', compact('users'));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $teams = Team::where('personal_team', false)->get();
        $roles = Role::whereIn('key', TeamRole::assignableBy(Auth::user()))->get();

        return view('users.create', compact('teams', 'roles'));
    }

    public function store(StoreUserRequest $request, ProvisionTeamMember $provisioner): RedirectResponse
    {
        $validated = $request->validated();

        $provisioner->provision(
            Team::findOrFail($validated['team_id']),
            $validated['name'],
            $validated['email'],
            $validated['role'],
        );

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

    public function destroy(User $user, DeletesUsers $deleter): RedirectResponse
    {
        $this->authorize('delete', $user);

        // The same action as Jetstream's own account deletion: team_user has no
        // foreign keys and tokens are polymorphic, so a bare delete() would
        // leave both behind.
        $deleter->delete($user);

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
