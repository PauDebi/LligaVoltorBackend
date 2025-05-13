<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Flight;
use App\Models\Like;
use Illuminate\Http\Request;

class Social extends Controller
{
    public function comment(Request $request)
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        $user = $request->user();
        $flight_id = $request->route('flight');

        $flight = Flight::findOrFail($flight_id);
        if (!$flight) {
            return response()->json(['message' => 'Flight not found'], 404);
        }
        $comment = Comment::create([
            'user_id' => $user->id,
            'flight_id' => $flight->id,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Comment added successfully',
            'comment' => $comment,
        ]);
    }

    public function like(Request $request)
    {
        $user = $request->user();
        $flight_id = $request->route('flight');

        $flight = Flight::findOrFail($flight_id);
        if (!$flight) {
            return response()->json(['message' => 'Flight not found'], 404);
        }

        $like = Like::where('user_id', $user->id)
            ->where('flight_id', $flight_id)
            ->first();

        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Like removed successfully']);
        } else {
            Like::create([
                'user_id' => $user->id,
                'flight_id' => $flight->id,
            ]);
            return response()->json([
                'message' => 'Flight liked successfully',
            ]);
        }
    }
}
