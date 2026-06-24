<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    /**
     * Get all companies for dropdown
     */
    public function index(): JsonResponse
    {
        $companies = Company::select('id', 'name', 'slug', 'logo', 'description')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Companies fetched successfully',
            'data' => $companies,
        ], 200);
    }
}
