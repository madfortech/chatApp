<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\VideoMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoMatchController extends Controller
{
    /**
     * Start video matching.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        // Current user's old active match close karo
        $this->endCurrentMatch($user);

        $matchedUser = $this->findAvailableUser($user);

        if (!$matchedUser) {
            return response()->json([
                'matched_user_id' => null,
                'match_id' => null,
                'metered_room' => null,
                'status' => 'waiting',
            ]);
        }

        // Same room name dono matched users ke liye use hoga.
        $roomName = $this->createRoomName();

        $match = VideoMatch::create([
            'user_one_id' => $user->id,
            'user_two_id' => $matchedUser->id,
            'status' => 'active',
            'metered_room' => $roomName,
            'started_at' => now(),
        ]);

        return response()->json([
            'matched_user_id' => $matchedUser->id,
            'match_id' => $match->id,
            'metered_room' => $roomName,
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
        $this->endCurrentMatch($user);

        $matchedUser = $this->findAvailableUser($user);

        if (!$matchedUser) {
            return response()->json([
                'matched_user_id' => null,
                'match_id' => null,
                'metered_room' => null,
                'status' => 'waiting',
            ]);
        }

        // New match ke liye new Metered room
        $roomName = $this->createRoomName();

        $match = VideoMatch::create([
            'user_one_id' => $user->id,
            'user_two_id' => $matchedUser->id,
            'status' => 'active',
            'metered_room' => $roomName,
            'started_at' => now(),
        ]);

        return response()->json([
            'matched_user_id' => $matchedUser->id,
            'match_id' => $match->id,
            'metered_room' => $roomName,
            'status' => 'matched',
        ]);
    }

    /**
     * Leave video matching.
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->endCurrentMatch($user);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Get current active match.
     *
     * Is endpoint se frontend ko existing match ka
     * Metered room mil sakta hai.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $match = VideoMatch::query()
            ->where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->latest('id')
            ->first();

        if (!$match) {
            return response()->json([
                'matched' => false,
                'match_id' => null,
                'matched_user_id' => null,
                'metered_room' => null,
            ]);
        }

        $matchedUserId =
            (int) $match->user_one_id === (int) $user->id
                ? $match->user_two_id
                : $match->user_one_id;

        return response()->json([
            'matched' => true,
            'match_id' => $match->id,
            'matched_user_id' => $matchedUserId,
            'metered_room' => $match->metered_room,
            'status' => $match->status,
        ]);
    }

    /**
     * End current active match.
     */
    private function endCurrentMatch(User $user): void
    {
        VideoMatch::where(function ($query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        })
        ->where('status', 'active')
        ->update([
            'status' => 'ended',
            'ended_at' => now(),
        ]);
    }

    /**
     * Create unique Metered room name.
     */
    private function createRoomName(): string
    {
        return 'yahoo-' . Str::lower(Str::random(32));
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