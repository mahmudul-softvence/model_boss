<?php

namespace App\Events;

use App\Models\GameMatch;
use App\Models\PlayerVote;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotingStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $matchData;

    public function __construct(GameMatch $match)
    {
        $playerOneVotes = PlayerVote::where('match_id', $match->id)
            ->where('voted_player_id', $match->player_one_id)
            ->sum('total_vote');

        $playerTwoVotes = PlayerVote::where('match_id', $match->id)
            ->where('voted_player_id', $match->player_two_id)
            ->sum('total_vote');

        $this->matchData = [
            'match_id' => $match->id,
            'match_no' => $match->match_no,

            'player_one' => [
                'id' => $match->playerOne->id,
                'name' => $match->playerOne->name,
                'image' => $match->playerOne->image_url,
                'total_votes' => $playerOneVotes ? $playerOneVotes : 0,
            ],

            'player_two' => [
                'id' => $match->playerTwo->id,
                'name' => $match->playerTwo->name,
                'image' => $match->playerTwo->image_url,
                'total_votes' => $playerTwoVotes ? $playerTwoVotes : 0,
            ],

            'vote_start_time' => $match->vote_start_time,
            'voting_time' => $match->voting_time,
        ];
    }

    public function broadcastOn()
    {
        return new Channel('match.'.$this->matchData['match_id']);
    }

    public function broadcastAs()
    {
        return 'voting.started';
    }
}
