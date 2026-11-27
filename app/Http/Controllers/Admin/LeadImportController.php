<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\LeadImportService;
use App\Support\AuthScope;
use App\Support\Outbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeadImportController extends Controller
{
    public function show(): View
    {
        $this->authorizeImport();

        return view('admin.leads.import', [
            'brands' => AuthScope::brandsForDropdown(),
            'headers' => LeadImportService::TEMPLATE_HEADERS,
        ]);
    }

    public function template(LeadImportService $importer): Response
    {
        $this->authorizeImport();

        return response($importer->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leads-import-template.csv"',
        ]);
    }

    public function store(Request $request, LeadImportService $importer): RedirectResponse
    {
        $this->authorizeImport();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Upload a CSV file (Excel → Save As → CSV).',
        ]);

        // Tag events as 'import' so CompanyOS automations don't notify for historical rows.
        $result = Outbox::withContext('import', fn () => $importer->import($request->file('file'), Auth::user()));

        $msg = "Imported {$result['created']} lead(s).";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} row(s) skipped.";
        }
        $warnings = $result['warnings'] ?? [];
        if (count($warnings) > 0) {
            $msg .= ' Some rows need follow-up (see warnings).';
        }

        return redirect()
            ->route('admin.leads.import')
            ->with($result['created'] > 0 ? 'success' : 'error', $msg)
            ->with('import_errors', $result['errors'])
            ->with('import_warnings', $warnings);
    }

    private function authorizeImport(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->hasRole([
            Role::ADMIN,
            Role::MANAGER,
            Role::AGENT,
            Role::PROJECT_MANAGER,
        ]), 403);
    }
}
