<?php

namespace App\Http\Controllers;

use App\Http\Helpers\IGCParser2;
use App\Http\Requests\GetUserFlightRequest;
use App\Http\Requests\PostFlightRequest;
use App\Models\Flight;
use DateTime;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class FlightController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $flights = Flight::all();
        return response()->json([
            'status' => 200,
            'flights' => $flights
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @throws Exception
     */
    public function store(PostFlightRequest $request): JsonResponse
    {
        //Store the file
        $storedFilePath = $request->file('igc_file')->storeAs('igc_flights', uniqid() . '.igc');

        //Parse the file
        $parsedData = IGCParser2::parseIGC($request->file('igc_file')->getRealPath());

        //Create a DB entry
        $flight = Flight::create([
            'igc_file' => $storedFilePath,
            'user_id' => $request->user()->id,
            'max_altitude' => $parsedData['max_altitude'],
            'distance' => $parsedData['total_distance'],
            'points' => $parsedData['total_distance'],
            'takeoff_time' => $parsedData['takeoff_time'],
            'landing_time' => $parsedData['landing_time'],
            'glider-type' => $parsedData['aircraft_type'],
            'category' => $request->input('category'),
        ]);

        // Return the response
        return response()->json([
            'status' => 200,
            'flight' => $flight
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function getFlightsFromUser(GetUserFlightRequest $request): JsonResponse
    {
        $user_id = $request->get('user_id');
        if ($request->user()->id === $user_id) {
            return Flight::find($user_id, "user_id");
        }
        return Flight::where('user_id', $user_id)
            ->where('is_private', false)
            ->get();
    }
}
