<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    /**
     * Get all roles for dropdown
     */
    public function index(): JsonResponse
    {
        // $roles = Role::select('id', 'name')
        //     ->orderBy('name')
        //     ->get();

        $roles = Role::select('id', 'name')
        ->whereNotIn('name', ['Admin', 'Manager'])
        ->orderBy('name')
        ->get();

        return response()->json([
            'status' => true,
            'message' => 'Roles fetched successfully',
            'data' => $roles,
        ], 200);
    }
}
