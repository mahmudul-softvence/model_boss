<?php

namespace App\Http\Controllers\Backend;

use App\Enums\LiveStatus;
use App\Events\LiveStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\CoinTransaction;
use App\Models\GameMatch;
use Carbon\Carbon;
use App\Http\Resources\LiveStatusResource;
use App\Models\CheckLiveStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function earnings(Request $request)
    {
        $year = $request->year;

        $data = CoinTransaction::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->where('user_id', 1)
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total','month')
            ->toArray();

        $months = [];

        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => $data[$i] ?? 0
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Earnings retrieved successfully',
            'data' => $months
        ]);
    }


    public function recentStreams()
    {
        $matches = GameMatch::where('confirmation_status', 1)
            ->orderByDesc('id')
            ->take(4)
            ->get();

        $result = [];

        foreach ($matches as $match) {

            $total = CoinTransaction::where('user_id', 1)
                ->where('reference', 'like', '%#' . $match->match_no)
                ->sum('amount');

            $result[] = [
                'match_no' => $match->match_no,
                'total_earnings' => $total
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Recent match earnings retrieved successfully',
            'data' => $result
        ]);
    }

}
