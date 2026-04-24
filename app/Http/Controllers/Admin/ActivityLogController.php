<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = UserActivityLog::with('user')->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $logs  = $query->paginate(30)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);
        $types = [
            UserActivityLog::TYPE_LOGIN,
            UserActivityLog::TYPE_LOGOUT,
            UserActivityLog::TYPE_SALE_CREATED,
            UserActivityLog::TYPE_SALE_UPDATED,
            UserActivityLog::TYPE_SALE_DELETED,
            UserActivityLog::TYPE_PPC_ADDED,
            UserActivityLog::TYPE_PPC_DELETED,
        ];

        return view('admin.activity_logs.index', compact('logs', 'users', 'types'));
    }
}
