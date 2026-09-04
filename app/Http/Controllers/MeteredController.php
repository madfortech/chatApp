<?php

namespace App\Http\Controllers;

use App\Models\VideoMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MeteredController extends Controller
{
    /**
     * Create a Metered access token for an active video match.
     */
    public function token(Request $request): JsonResponse
    {
        $user = $request->user();

        /*
         * Validate match ID.
         */
        $request->validate([
            'match_id' => [
                'required',
                'integer',
            ],
        ]);

        /*
         * Find active match.
         *
         * The current user MUST belong to this match.
         */
        $match = VideoMatch::query()
            ->where('id', $request->integer('match_id'))
            ->where('status', 'active')
            ->where(function ($query) use ($user) {
                $query->where('user_one_id', $user->id)
                    ->orWhere('user_two_id', $user->id);
            })
            ->first();

        if (!$match) {
            return response()->json([
                'success' => false,
                'message' => 'Active video match not found.',
            ], 404);
        }

        /*
         * Every active match must have a Metered room.
         */
        if (empty($match->metered_room)) {
            return response()->json([
                'success' => false,
                'message' => 'Metered room is not configured for this match.',
                'match_id' => $match->id,
            ], 422);
        }

        /*
         * Read Metered configuration from Laravel config.
         *
         * config/services.php:
         *
         * 'metered' => [
         *     'app_name' => env('METERED_APP_NAME'),
         *     'secret_key' => env('METERED_SECRET_KEY'),
         * ],
         */
        $appName = config('services.metered.app_name');
        $secretKey = config('services.metered.secret_key');

        /*
         * IMPORTANT:
         *
         * Do NOT return or log the actual secret key.
         *
         * This diagnostic only tells us whether
         * Laravel can see each value.
         */
        if (empty($appName) || empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Metered configuration is missing.',
                'debug' => [
                    'app_name_present' => !empty($appName),
                    'secret_key_present' => !empty($secretKey),
                ],
            ], 500);
        }

        /*
         * Clean the app name.
         *
         * Expected:
         * chatapp-webrtc
         *
         * NOT:
         * https://chatapp-webrtc.metered.live
         */
        $appName = trim($appName);

        /*
         * Token validity.
         */
        $expirationDate = now()
            ->addHours(2)
            ->toIso8601String();

        /*
         * Metered token endpoint.
         *
         * Secret key remains ONLY on Laravel server.
         */
        $tokenUrl =
            'https://' .
            $appName .
            '.metered.live/api/v1/token?secretKey=' .
            urlencode($secretKey);

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post(
                    $tokenUrl,
                    [
                        'roomName' => $match->metered_room,
                        'expirationDate' => $expirationDate,
                    ]
                );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to Metered.',
            ], 502);
        }

        /*
         * Metered API returned an error.
         *
         * Keep status and response for debugging,
         * but NEVER return our secret key.
         */
        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Metered token generation failed.',
                'metered_status' => $response->status(),
                'metered_response' => $response->json(),
            ], 502);
        }

        /*
         * Decode Metered response.
         */
        $meteredData = $response->json();

        /*
         * Metered access token.
         *
         * Support both possible response field names.
         */
        $accessToken =
            $meteredData['token']
            ?? $meteredData['accessToken']
            ?? null;

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Metered did not return an access token.',
                'metered_response' => $meteredData,
            ], 502);
        }

        /*
         * Determine the other participant.
         */
        $matchedUserId =
            (int) $match->user_one_id === (int) $user->id
                ? $match->user_two_id
                : $match->user_one_id;

        /*
         * Return only frontend-required data.
         *
         * NEVER return:
         * - Metered Secret Key
         * - TURN credential
         * - Laravel environment values
         */
        return response()->json([
            'success' => true,

            'match_id' => $match->id,

            'matched_user_id' => $matchedUserId,

            'roomName' => $match->metered_room,

            'roomURL' =>
                $appName .
                '.metered.live/' .
                $match->metered_room,

            'accessToken' => $accessToken,

            'userName' =>
                $user->name
                ?? ('User ' . $user->id),

            'expirationDate' => $expirationDate,
        ]);
    }
}