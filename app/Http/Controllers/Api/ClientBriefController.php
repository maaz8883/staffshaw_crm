<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrandBriefForm;
use App\Models\BriefSubmission;
use App\Models\Client;
use App\Models\Sale;
use App\Services\ClientBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClientBriefController extends Controller
{
    public function __construct(
        private readonly ClientBriefService $briefService
    ) {}

    public function index(Client $client): JsonResponse
    {
        return response()
            ->json($this->briefService->forClient($client))
            ->header('Cache-Control', 'no-store, private');
    }

    public function indexForLegacySale(Sale $sale): JsonResponse
    {
        abort_unless($sale->client_id && $sale->client, 404, 'The sale is not linked to a CRM client.');

        return $this->index($sale->client);
    }

    public function downloadAttachment(
        Client $client,
        BriefSubmission $submission,
        int $file
    ): Response {
        $submission->loadMissing('sale');

        abort_unless(
            $submission->sale && (int) $submission->sale->client_id === (int) $client->id,
            404
        );

        $resolved = $this->briefService->fileForSubmission($submission, $file);
        abort_if($resolved === null, 404, 'Brief file not found.');

        return Storage::disk('public')->download($resolved['_path'], $resolved['name']);
    }

    public function downloadDocument(Client $client, BrandBriefForm $form): Response
    {
        $belongsToClient = $client->sales()
            ->where('is_draft', false)
            ->where('brand_id', $form->brand_id)
            ->exists();

        abort_unless($belongsToClient, 404);

        $document = $form->resolveDocumentPath();
        abort_if($document === null, 404, 'Brief document not found.');

        return response()->download($document['path'], $document['filename']);
    }
}
