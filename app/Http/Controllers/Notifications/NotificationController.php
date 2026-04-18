<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function notifications(Request $request)
    {
        $notifications = $request->user()->notifications()->get();

        return $this->sendResponse($notifications);
    }

    public function read_notifications(Request $request, $id)
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return $this->sendResponse();
    }

    public function delete_notifications(Request $request, $id)
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return $this->sendResponse([], 'Notification Deleted Successfully');
    }

    public function delete_all_notifications(Request $request, $id)
    {
        $request->user()->notifications()->delete();

        return $this->sendResponse([], 'All Notification Deleted Successfully');
    }
}
