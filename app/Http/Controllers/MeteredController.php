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

        $request->validate([
            'match_id' => [
                'required',
                'integer',
            ],
        ]);

        /*
         * Find the active match and make sure
         * the current user belongs to it.
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
         * Metered room must exist.
         */
        if (empty($match->metered_room)) {
            return response()->json([
                'success' => false,
                'message' => 'Metered room is not configured for this match.',
            ], 422);
        }

        $appName = config('services.metered.app_name');
        $secretKey = config('services.metered.secret_key');

        if (empty($appName) || empty($secretKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Metered configuration is missing.',
            ], 500);
        }

        /*
         * Token expiration.
         *
         * Keep the token valid for the duration of this call.
         */
        $expirationDate = now()
            ->addHours(2)
            ->toIso8601String();

        /*
         * Generate Metered access token.
         *
         * IMPORTANT:
         * Secret key stays on Laravel server.
         */
        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post(
                    'https://' . $appName .
                    '.metered.live/api/v1/token?secretKey=' .
                    urlencode($secretKey),
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
         * Metered returned an error.
         */
        if (!$response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Metered token generation failed.',
                'metered_status' => $response->status(),
                'metered_response' => $response->json(),
            ], 502);
        }

        $meteredData = $response->json();

        /*
         * Metered token.
         *
         * Depending on API response, token may be returned
         * under token or accessToken.
         */
        $accessToken =
            $meteredData['token']
            ?? $meteredData['accessToken']
            ?? null;

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Metered did not return an access token.',
            ], 502);
        }

        /*
         * Return only information frontend needs.
         *
         * NEVER return the Metered secret key.
         */
        return response()->json([
            'success' => true,

            'match_id' => $match->id,

            'matched_user_id' =>
                (int) $match->user_one_id === (int) $user->id
                    ? $match->user_two_id
                    : $match->user_one_id,

            'roomName' => $match->metered_room,

            'roomURL' =>
                $appName . '.metered.live/' .
                $match->metered_room,

            'accessToken' => $accessToken,

            'userName' =>
                $user->name
                ?? ('User ' . $user->id),

            'expirationDate' => $expirationDate,
        ]);
    }
}