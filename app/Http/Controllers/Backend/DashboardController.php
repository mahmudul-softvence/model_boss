<?php

namespace App\Http\Controllers\Backend;

use App\Enums\LiveStatus;
use App\Events\LiveStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveStatusResource;
use App\Models\CheckLiveStatus;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $live_status = CheckLiveStatus::get();

        $data = [
            'live_status' => LiveStatusResource::collection($live_status)
        ];

        return $this->sendResponse($data);
    }


    public function change_live_status(Request $request)
    {
        $request->validate([
            'platform_name' => 'required|string',
            'status' => 'required|string|in:live,pause,stop',
            'mode' => 'nullable|string|in:landscape,portrait'
        ]);

        $liveStatus = CheckLiveStatus::firstOrCreate(
            ['platform_name' => $request->platform_name]
        );

        $updateData = ['platform_live_status' => $request->status];

        if ($request->status === LiveStatus::LIVE->value) {
            $updateData['live_started_at'] = now();
            $updateData['live_stopped_at'] = null;
        } elseif ($request->status === LiveStatus::STOP->value) {
            $updateData['live_stopped_at'] = now();
        }

        if ($request->filled('mode')) {
            $updateData['mode'] = $request->mode;
        }

        $liveStatus->update($updateData);

        broadcast(new LiveStatusUpdated($liveStatus))->toOthers();

        return $this->sendResponse(LiveStatusResource::make($liveStatus), 'Live status updated successfully.');
    }
}
