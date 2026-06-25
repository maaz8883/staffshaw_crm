<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(): JsonResponse
    {
        $users = User::with([
            'role:id,name',
            'team:id,name',
            'subTeam:id,name',
            'subTeamHead',
            'company:id,name',
        ])->latest()->get();

        return response()->json([
            'status' => true,
            'message' => 'Users fetched successfully',
            'data' => $users,
        ], 200);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'team_id' => 'nullable|exists:teams,id',
            'sub_team_id' => 'nullable|exists:teams,id',
            'sub_team_head_id' => 'nullable|exists:sub_team_heads,id',
            'account_status' => 'nullable|in:active,pending,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
            'company_id' => $request->company_id,
            'team_id' => $request->team_id,
            'sub_team_id' => $request->sub_team_id,
            'sub_team_head_id' => $request->sub_team_head_id,
            'account_status' => $request->account_status ?? User::ACCOUNT_ACTIVE,
        ]);

        // Load relationships
        $user->load([
            'role:id,name',
            'team:id,name',
            'subTeam:id,name',
            'subTeamHead',
            'company:id,name',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'role:id,name',
            'team:id,name',
            'subTeam:id,name',
            'subTeamHead',
            'company:id,name',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User fetched successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'company_id' => 'nullable|exists:companies,id',
            'team_id' => 'nullable|exists:teams,id',
            'sub_team_id' => 'nullable|exists:teams,id',
            'sub_team_head_id' => 'nullable|exists:sub_team_heads,id',
            'account_status' => 'nullable|in:active,pending,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = [
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'company_id' => $request->company_id,
            'team_id' => $request->team_id,
            'sub_team_id' => $request->sub_team_id,
            'sub_team_head_id' => $request->sub_team_head_id,
        ];

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->password);
        }

        if ($request->filled('account_status')) {
            $payload['account_status'] = $request->account_status;
        }

        $user->update($payload);

        // Load relationships
        $user->load([
            'role:id,name',
            'team:id,name',
            'subTeam:id,name',
            'subTeamHead',
            'company:id,name',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): JsonResponse
    {
        $userName = $user->name;
        $user->delete();

        return response()->json([
            'status' => true,
            'message' => "User '{$userName}' deleted successfully",
        ], 200);
    }
}
