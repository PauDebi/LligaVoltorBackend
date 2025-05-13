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
        $flights = Flight::with('user')->where('is_private', false)->orderByDesc('points')->get();
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

        if ($request->input('is_private') == null) {
            $request->merge(['is_private' => false]);
        }

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
            'is_private' => $request->input('is_private'),
            'category' => $request->input('category'),
        ]);

        // Return the response
        return response()->json([
            'status' => 200,
            'flight' => $flight->load('user'),
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

    public function getFlightsFromUser(Request $request): JsonResponse
    {
        $user_id = $request->route('user_id');
        if ($request->user()->id == $user_id) {
            return response()->json([
                'status' => 200,
                'flights' => Flight::where('user_id', $user_id)->with('user')->get(),
            ]);
        }
        return response()->json([
            'status' => 200,
            'flights' => Flight::where('user_id', $user_id)
                ->where('is_private', false)
                ->with('user')
                ->get(),
        ]);
    }

    public function getIgcFile(Request $request)
    {
        $flightPath = "igc_flights/" . $request->route('flight_path');
        $flight = Flight::where('igc_file', $flightPath)->first();
        if (!$flight->is_private || $flight->user_id == $request->user()->id) {
            return response()->download(storage_path('app/private/igc_flights/' . $request->route('flight_path')));
        }
        return response()->json(
            ['error' => 'File not found',
            'flight_path' => $flightPath]
            , 404);
    }
}
