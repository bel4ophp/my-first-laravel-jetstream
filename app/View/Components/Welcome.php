<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * The dashboard's welcome card.
 */
class Welcome extends Component
{
    /**
     * Team owners (in practice, the admin) see every team's stats; everyone
     * else gets their own card. Worked out here rather than queried from the
     * template.
     */
    public bool $isOwner;

    public function __construct()
    {
        $this->isOwner = (bool) Auth::user()?->ownedTeams()->exists();
    }

    public function render(): View
    {
        return view('components.welcome');
    }
}
