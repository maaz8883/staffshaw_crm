<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    private string $disk   = 'local';
    private string $folder = 'backups';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! auth()->user()?->hasRole('Admin')) abort(403);
            return $next($request);
        });
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $files = collect(Storage::disk($this->disk)->files($this->folder))
            ->map(function (string $path) {
                $name = basename($path);
                $size = Storage::disk($this->disk)->size($path);
                $time = Storage::disk($this->disk)->lastModified($path);

                return [
                    'name'         => $name,
                    'size_human'   => $this->humanSize($size),
                    'created_at'   => date('d M Y H:i', $time),
                    'download_url' => route('admin.backup.download', ['file' => $name]),
                    'delete_url'   => route('admin.backup.destroy', ['file' => $name]),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json($files);
    }

    public function store(): RedirectResponse
    {
        $db   = config('database.connections.mysql');
        $host = $db['host'];
        $port = $db['port'];
        $name = $db['database'];
        $user = $db['username'];
        $pass = $db['password'];

        $storePath = storage_path("app/{$this->folder}");
        if (! is_dir($storePath)) {
            mkdir($storePath, 0755, true);
        }

        $sqlFile = $storePath . DIRECTORY_SEPARATOR . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $gzFile  = $sqlFile . '.gz';

        // Resolve mysqldump — try PATH first, then common Laragon location
        $mysqldump = $this->resolveMysqldump();

        if (! $mysqldump) {
            return back()->withErrors(['backup' => 'mysqldump not found. Please ensure MySQL bin directory is in your PATH.'])->withFragment('backup');
        }

        // Build command — dump to plain .sql first (avoids Windows pipe issues)
        $passArg = $pass ? '-p' . $pass : '';
        $cmd = sprintf(
            '"%s" --host=%s --port=%s --user=%s %s --single-transaction --routines --triggers %s > "%s" 2>&1',
            $mysqldump,
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            $passArg,
            escapeshellarg($name),
            $sqlFile
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($sqlFile) || filesize($sqlFile) < 20) {
            if (file_exists($sqlFile)) unlink($sqlFile);
            $errMsg = implode(' ', $output);
            return back()->withErrors(['backup' => "Backup failed: {$errMsg}"])->withFragment('backup');
        }

        // Gzip via PHP (no dependency on system gzip)
        $gz = gzopen($gzFile, 'wb9');
        $fh = fopen($sqlFile, 'rb');
        while (! feof($fh)) {
            gzwrite($gz, fread($fh, 524288)); // 512 KB chunks
        }
        fclose($fh);
        gzclose($gz);
        unlink($sqlFile); // remove plain .sql

        return back()->with('success', 'Backup created: ' . basename($gzFile))->withFragment('backup');
    }

    public function download(string $file): StreamedResponse
    {
        $path = "{$this->folder}/{$file}";
        abort_unless(Storage::disk($this->disk)->exists($path), 404);

        return Storage::disk($this->disk)->download($path, $file);
    }

    public function destroy(string $file): RedirectResponse
    {
        $path = "{$this->folder}/{$file}";
        if (Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }

        return back()->with('success', 'Backup deleted.')->withFragment('backup');
    }

    private function resolveMysqldump(): ?string
    {
        // 1. Check if it's in PATH
        $which = PHP_OS_FAMILY === 'Windows'
            ? shell_exec('where mysqldump 2>nul')
            : shell_exec('which mysqldump 2>/dev/null');

        if ($which && trim($which)) {
            return trim(explode("\n", $which)[0]);
        }

        // 2. Common Laragon paths
        $candidates = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [];

        // 3. Common XAMPP / WAMP paths
        $candidates = array_merge($candidates, [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ]);

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }
}
