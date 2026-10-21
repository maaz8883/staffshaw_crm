<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;

class TeamController extends Controller
{
    public function index()
    {
        $teams = Team::with([
            'company:id,name',
            // Public endpoint: names only, never staff emails
            'teamHead:id,name',
            'subTeamHead:id,name',
            'parentTeam:id,name',
            'subTeams:id,parent_team_id,name',
        ])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Teams fetched successfully',
            'data' => $teams,
        ], 200);
    }
}