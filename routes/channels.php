<?php

use App\Models\Team;
use App\Support\AuthScope;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Live Trello changes for a team's boards — same access rule as the Trello board page
// (team members, plus members of its sub-teams).
Broadcast::channel('trello.team.{teamId}', function ($user, $teamId) {
    $teamId = (int) $teamId;
    if (AuthScope::canAccessTeam($teamId)) {
        return true;
    }

    return Team::query()
        ->where('parent_team_id', $teamId)
        ->whereIn('id', AuthScope::accessibleTeamIds())
        ->exists();
});
