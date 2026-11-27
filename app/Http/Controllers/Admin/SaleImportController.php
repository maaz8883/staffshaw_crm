<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\SaleImportService;
use App\Support\AuthScope;
use App\Support\Outbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SaleImportController extends Controller
{
    public function show(): View
    {
        $this->authorizeImport();

        return view('admin.sales.import', [
            'brands' => AuthScope::brandsForDropdown(),
            'headers' => SaleImportService::TEMPLATE_HEADERS,
        ]);
    }

    public function template(SaleImportService $importer): Response
    {
        $this->authorizeImport();

        $csv = $importer->templateCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sales-import-template.csv"',
        ]);
    }

    public function store(Request $request, SaleImportService $importer): RedirectResponse
    {
        $this->authorizeImport();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Upload a CSV file (Excel → Save As → CSV).',
        ]);

        // Tag events as 'import' so CompanyOS automations don't notify for historical rows.
        $result = Outbox::withContext('import', fn () => $importer->import($request->file('file'), Auth::user()));

        $msg = "Imported {$result['created']} sale(s).";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} row(s) skipped.";
        }
        $warnings = $result['warnings'] ?? [];
        if (count($warnings) > 0) {
            $msg .= ' Some rows need follow-up (see warnings).';
        }

        return redirect()
            ->route('admin.sales.import')
            ->with($result['created'] > 0 ? 'success' : 'error', $msg)
            ->with('import_errors', $result['errors'])
            ->with('import_warnings', $warnings)
            ->with('import_created', $result['created'])
            ->with('import_skipped', $result['skipped']);
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

        // Finance cannot import. Admins may import historical data even though they cannot create single sales.
        abort_if($user->hasRole(Role::FINANCE) && ! $user->hasRole([Role::ADMIN, Role::MANAGER]), 403);
    }
}
