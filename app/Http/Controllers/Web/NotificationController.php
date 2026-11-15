<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_if($request->user()->hasRole(Role::SecurityAuditor), 403);
        $notifications = $request->user()->notifications()->latest()->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, string $notification)
    {
        abort_if($request->user()->hasRole(Role::SecurityAuditor), 403);
        $record = $request->user()->notifications()->whereKey($notification)->first();
        abort_unless($record, 403);
        $record->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    public function readAll(Request $request)
    {
        abort_if($request->user()->hasRole(Role::SecurityAuditor), 403);
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Your notifications are marked as read.');
    }
}
