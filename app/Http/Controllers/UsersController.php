<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = User::query();

        // Check if the current user is an admin in their current team
        /** @var \Laravel\Jetstream\HasTeams $user */
        $user = Auth::user();
        $isManager = $user->isTeamManager();
        if ($isManager) {
            // Non-admins: restrict to members of their teams
            $query->whereHas('teams', function ($q) use ($user) {
                $q->whereIn('teams.id', $user->teams->pluck('id'))
                ->where('team_user.role', '!=', 'admin'); // exclude admins;
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->paginate(10);

        return view('users', compact('users'));
    }

    public function create()
    {
        $teams = Team::where('personal_team', false)->get();
        $roles = Role::all();
        return view('users.create', compact('teams', 'roles'));
    }

    public function store(StoreUserRequest $request)
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

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
