<?php

namespace App\Http\Controllers\Backend;

use App\Enums\LiveStatus;
use App\Events\LiveStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveStatusResource;
use App\Models\CheckLiveStatus;
use App\Models\CoinTransaction;
use App\Models\GameMatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $live_status = CheckLiveStatus::get();

        $data = [
            'live_status' => LiveStatusResource::collection($live_status),
        ];

        return $this->sendResponse($data);
    }

    public function change_live_status(Request $request)
    {
        $request->validate([
            'platform_name' => 'required|string',
            'status' => 'required|string|in:live,pause,stop',
            'mode' => 'nullable|string|in:landscape,portrait',
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
        $year = $request->year ?? now()->year;

        $data = CoinTransaction::select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('SUM(amount) as total')
        )
            ->where('user_id', 1)
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->pluck('total', 'month')
            ->toArray();

        $months = [];

        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => $data[$i] ?? 0,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Earnings retrieved successfully',
            'data' => $months,
        ]);
    }

    public function recentStreams()
    {
        $matches = GameMatch::where('confirmation_status', 1)
            ->whereNotNull('winner_id')
            ->orderByDesc('id')
            ->take(4)
            ->get();

        $result = [];

        foreach ($matches as $match) {

            $total = CoinTransaction::where('user_id', 1)
                ->where('reference', 'like', '%#'.$match->match_no)
                ->sum('amount');

            $minutesAgo = (int) round($match->updated_at->diffInRealMinutes(now(), true));

            if ($minutesAgo >= 60) {
                $hours = intdiv($minutesAgo, 60);
                $minutes = $minutesAgo % 60;

                $time = $hours.' hr';
                if ($minutes > 0) {
                    $time .= ' '.$minutes.' min';
                }
                $time .= ' ago';
            } else {
                $time = $minutesAgo.' min ago';
            }

            $result[] = [
                'match_no' => $match->match_no,
                'total_earnings' => number_format($total, 2),
                'end_time' => $time,
            ];
        }

        return response()->json([
            'status' => true,
            'message' => 'Recent match earnings retrieved successfully',
            'data' => $result,
        ]);
    }

    public function runningMatches(Request $request)
    {
        $perPage = $request->per_page ?? 10;

        $query = GameMatch::with([
            'game:id,name',
            'playerOne:id,name,image',
            'playerTwo:id,name,image',
            'winner:id,name,image',
        ])
            ->whereIn('type', ['upcoming', 'live']);

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->game_id);
        }

        if ($request->filled('player_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('player_one_id', $request->player_id)
                    ->orWhere('player_two_id', $request->player_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {

                $q->where('match_no', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhereHas('game', function ($gameQuery) use ($search) {
                        $gameQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('playerOne', function ($playerQuery) use ($search) {
                        $playerQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('playerTwo', function ($playerQuery) use ($search) {
                        $playerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $matches = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Matches retrieved successfully',
            'data' => $matches->items(),
            'meta' => [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
                'prev' => $matches->currentPage() > 1,
                'next' => $matches->hasMorePages(),
            ],
        ]);
    }
}
