<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()->with(['brand', 'assignedUser', 'team']);

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        $leads = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($leads);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'brand_id'    => 'nullable|exists:brands,id',
            'source'      => 'nullable|string|max:100',
            'status'      => 'required|in:' . implode(',', Lead::STATUSES),
            'assigned_to' => 'nullable|exists:users,id',
            'team_id'     => 'nullable|exists:teams,id',
            'notes'       => 'nullable|string',
        ]);

        $lead = Lead::query()->create($validated);
        $lead->load(['brand', 'assignedUser', 'team']);

        return response()->json($lead, 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load(['brand', 'assignedUser', 'team']);

        return response()->json($lead);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'brand_id'    => 'nullable|exists:brands,id',
            'source'      => 'nullable|string|max:100',
            'status'      => 'sometimes|required|in:' . implode(',', Lead::STATUSES),
            'assigned_to' => 'nullable|exists:users,id',
            'team_id'     => 'nullable|exists:teams,id',
            'notes'       => 'nullable|string',
        ]);

        $lead->update($validated);
        $lead->load(['brand', 'assignedUser', 'team']);

        return response()->json($lead);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return response()->json(['message' => 'Lead deleted successfully.']);
    }

    public function assign(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $lead->update($validated);
        $lead->load('assignedUser');

        return response()->json($lead);
    }
}
