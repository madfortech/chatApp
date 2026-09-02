<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VideoMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoMatchController extends Controller
{
    /**
     * Start video matching.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        // Current user's old active match close karo
        VideoMatch::where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->where('status', 'active')
        ->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $matchedUser = $this->findAvailableUser($user);

        if (!$matchedUser) {
            return response()->json([
                'matched_user_id' => null,
                'status' => 'waiting',
            ]);
        }

        $match = VideoMatch::create([
            'user_one_id' => $user->id,
            'user_two_id' => $matchedUser->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        return response()->json([
            'matched_user_id' => $matchedUser->id,
            'match_id' => $match->id,
            'status' => 'matched',
        ]);
    }

    /**
     * Find next video user.
     */
    public function next(Request $request): JsonResponse
    {
        $user = $request->user();

        // Current call end karo
        VideoMatch::where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->where('status', 'active')
        ->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        $matchedUser = $this->findAvailableUser($user);

        if (!$matchedUser) {
            return response()->json([
                'matched_user_id' => null,
                'status' => 'waiting',
            ]);
        }

        $match = VideoMatch::create([
            'user_one_id' => $user->id,
            'user_two_id' => $matchedUser->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        return response()->json([
            'matched_user_id' => $matchedUser->id,
            'match_id' => $match->id,
            'status' => 'matched',
        ]);
    }

    /**
     * Leave video matching.
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();

        VideoMatch::where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->where('status', 'active')
        ->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Find a random user who is not currently in another call.
     */
    private function findAvailableUser(User $currentUser): ?User
    {
        $busyUserIds = VideoMatch::query()
            ->where('status', 'active')
            ->get([
                'user_one_id',
                'user_two_id',
            ])
            ->flatMap(function (VideoMatch $match) {
                return [
                    $match->user_one_id,
                    $match->user_two_id,
                ];
            })
            ->unique()
            ->values()
            ->all();

        $busyUserIds = array_values(
            array_diff(
                $busyUserIds,
                [$currentUser->id]
            )
        );

        return User::query()
            ->where('id', '!=', $currentUser->id)
            ->when(
                !empty($busyUserIds),
                function ($query) use ($busyUserIds) {
                    $query->whereNotIn('id', $busyUserIds);
                }
            )
            ->inRandomOrder()
            ->first();
    }
}