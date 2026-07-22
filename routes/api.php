<?php

use App\Http\Controllers\Api\BrandBriefFormController;
use App\Http\Controllers\Api\BriefSubmissionController;
use App\Http\Controllers\Api\ClientBriefController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Orbit brief form submissions (Bearer / X-Orbit-Api-Key)
Route::middleware('orbit.brief.api')->prefix('v1')->group(function () {
    Route::get('clients/{client}/brief-forms', [ClientBriefController::class, 'index'])
        ->whereNumber('client')
        ->name('api.clients.brief-forms.index');
    Route::get('sales/{sale}/client-brief-forms', [ClientBriefController::class, 'indexForLegacySale'])
        ->whereNumber('sale')
        ->name('api.sales.client-brief-forms.index');
    Route::get('clients/{client}/brief-submissions/{submission}/files/{file}', [ClientBriefController::class, 'downloadAttachment'])
        ->whereNumber(['client', 'submission', 'file'])
        ->name('api.clients.brief-files.download');
    Route::get('clients/{client}/brief-forms/{form}/document', [ClientBriefController::class, 'downloadDocument'])
        ->whereNumber(['client', 'form'])
        ->name('api.clients.brief-documents.download');
    Route::get('brand-brief-forms/resolve', [BrandBriefFormController::class, 'resolve']);
    Route::get('brand-brief-forms/{form}/schema', [BrandBriefFormController::class, 'schema'])
        ->whereNumber('form');
    Route::get('sales/{sale}/brief-submission', [BriefSubmissionController::class, 'showForSale']);
    Route::get('sales/{sale}/brief-submissions/{briefType}', [BriefSubmissionController::class, 'show'])
        ->where('briefType', '[a-z0-9_-]+');
    Route::post('sales/{sale}/brief-submissions', [BriefSubmissionController::class, 'store']);
    Route::post('sales/{sale}/brief-upload', [BriefSubmissionController::class, 'upload']);
});

// Public API - no token required
Route::prefix('v1')->group(function () {
    // Teams - read only
    Route::get('teams', [TeamController::class, 'index']);
    Route::get('teams/{team}', [TeamController::class, 'show']);

    // Roles - dropdown
    Route::get('roles', [RoleController::class, 'index']);

    // Companies - dropdown
    Route::get('companies', [CompanyController::class, 'index']);

    // User registration - public
    Route::post('users', [UserController::class, 'store']);
});

// Protected API - token required
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // Teams CRUD
    Route::post('teams', [TeamController::class, 'store']);
    Route::put('teams/{team}', [TeamController::class, 'update']);
    Route::patch('teams/{team}', [TeamController::class, 'update']);
    Route::delete('teams/{team}', [TeamController::class, 'destroy']);

    // Users CRUD
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::put('users/{user}', [UserController::class, 'update']);
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::delete('users/{user}', [UserController::class, 'destroy']);

    // Leads CRUD
    Route::get('leads', [LeadController::class, 'index']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::get('leads/{lead}', [LeadController::class, 'show']);
    Route::put('leads/{lead}', [LeadController::class, 'update']);
    Route::patch('leads/{lead}', [LeadController::class, 'update']);
    Route::delete('leads/{lead}', [LeadController::class, 'destroy']);
    Route::patch('leads/{lead}/assign', [LeadController::class, 'assign']);
});
