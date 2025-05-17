<?php

namespace App\Http\Controllers;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function getOpen(): JsonResponse
    {
        return $this->getLeaderboard(['open', 'sport', 'club', 'tandem']);
    }

    public function getSport()
    {
        return $this->getLeaderboard(['sport', 'club', 'tandem']);
    }

    public function getClub()
    {
        return $this->getLeaderboard(['club', 'tandem']);
    }

    public function getTandem()
    {
        return $this->getLeaderboard(['tandem']);
    }

    public function getPunctuationCsv(Request $request)
    {
        $user = $request->user();
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized, only admin users. Your user: '.$user], 403);
        }

        $punctuationCsv = storage_path('app/private/bonus_points.csv');

        if (!file_exists($punctuationCsv)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->download($punctuationCsv, 'bonus_points.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function postPunctuationCsv(Request $request)
    {
        $user = $request->user();
        if (!$user->is_admin) {
            return response()->json(['error' => 'Unauthorized, only admin users. Your user: '.$user], 403);
        }
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);
        $csvFile = $request->file('csv_file');
        $destinationPath = storage_path('app/private');
        $csvFile->move($destinationPath, 'bonus_points.csv');
        return response()->json(['message' => 'File uploaded successfully', 'file_path' => $destinationPath]);
    }


    private function getLeaderboard(array $categories): JsonResponse
    {
        $categoryFilter = empty($categories) ? '' : 'WHERE flights.category IN (' . implode(',', array_map(fn($c) => "'$c'", $categories)) . ')';

        $results = DB::select("
        WITH ranked_flights AS (
            SELECT
                flights.user_id,
                flights.points,
                ROW_NUMBER() OVER (PARTITION BY flights.user_id ORDER BY flights.points DESC) AS rn
            FROM flights
            $categoryFilter
            AND flights.is_private = 0
        )
        SELECT
            ranked_flights.user_id,
            SUM(ranked_flights.points) AS top_5_points
        FROM ranked_flights
        WHERE ranked_flights.rn <= 5
        GROUP BY ranked_flights.user_id
        ORDER BY top_5_points DESC
    ");

        $userIds = collect($results)->pluck('user_id')->all();

        // Cargar usuarios con sus datos
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // Combinar resultados
        $final = collect($results)->map(function ($row) use ($users) {
            return [
                'user' => $users[$row->user_id],
                'points' => $row->top_5_points,
            ];
        });

        return response()->json($final);
    }
}
