<?php

use App\Models\Team;
use App\Support\TeamTrelloAccounts;
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

// Live Trello changes for a team's boards — same access rule as the Trello pages
// (own Trello per team, sub-teams without one use their parent's).
Broadcast::channel('trello.team.{teamId}', function ($user, $teamId) {
    $team = Team::find((int) $teamId);

    return $team !== null && TeamTrelloAccounts::userCanUse($team);
});
