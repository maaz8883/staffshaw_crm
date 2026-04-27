<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        $types = [
            UserActivityLog::TYPE_LOGIN,
            UserActivityLog::TYPE_LOGOUT,
            UserActivityLog::TYPE_SALE_CREATED,
            UserActivityLog::TYPE_SALE_UPDATED,
            UserActivityLog::TYPE_SALE_DELETED,
            UserActivityLog::TYPE_PPC_ADDED,
            UserActivityLog::TYPE_PPC_DELETED,
            UserActivityLog::TYPE_SMTP_UPDATED,
            UserActivityLog::TYPE_OTP_UPDATED,
            UserActivityLog::TYPE_BACKUP_CREATED,
            UserActivityLog::TYPE_BACKUP_DELETED,
        ];

        return view('admin.activity_logs.index', compact('users', 'types'));
    }

    public function datatable(Request $request): JsonResponse
    {
        $query = UserActivityLog::with('user')->select('user_activity_logs.*');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        return DataTables::eloquent($query)
            ->addColumn('user_name', fn (UserActivityLog $log) => 
                $log->user ? '<a href="' . route('admin.users.show', $log->user_id) . '">' . e($log->user->name) . '</a>' : '<span class="text-muted">Deleted</span>'
            )
            ->addColumn('type_badge', fn (UserActivityLog $log) =>
                '<span class="badge bg-' . $log->typeBadgeClass() . '">' . e(str_replace('_', ' ', ucfirst($log->type))) . '</span>'
            )
            ->addColumn('location', fn (UserActivityLog $log) =>
                $log->country ? e($log->country . ($log->city ? ', ' . $log->city : '')) : '<span class="text-muted">-</span>'
            )
            ->editColumn('user_agent', fn (UserActivityLog $log) =>
                $log->user_agent ? '<span title="' . e($log->user_agent) . '">' . e(\Illuminate\Support\Str::limit($log->user_agent, 50)) . '</span>' : '-'
            )
            ->editColumn('created_at', fn (UserActivityLog $log) => $log->created_at->format('d M Y H:i'))
            ->orderColumn('created_at', 'user_activity_logs.created_at $1')
            ->rawColumns(['user_name', 'type_badge', 'location', 'user_agent'])
            ->toJson();
    }
}
