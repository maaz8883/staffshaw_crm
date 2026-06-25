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
            'teamHead:id,name,email',
            'subTeamHead:id,name,email',
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