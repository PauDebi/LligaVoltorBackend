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
        $flights = Flight::with(['user', 'likes.user', 'comments.user'])->where('is_private', false)->orderByDesc('points')->get();
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
        $randomName = uniqid();
        $filename = $randomName . '.igc';
        //Store the file
        $storedFilePath = $request->file('igc_file')->storeAs('igc_flights', $filename);


        //Parse the file
        $parsedData = IGCParser2::parseIGC(
            $request->file('igc_file')->getRealPath(),
            $randomName
        );



        if ($request->input('is_private') == null) {
            $request->merge(['is_private' => false]);
        }

        $bonusPath = storage_path('app/private/bonus_points.csv');

        $csvContent = null;
        if (file_exists($bonusPath)) {
            $csvContent = file_get_contents($bonusPath);
        }

        //Create a DB entry
        $flight = Flight::create([
            'igc_file' => $storedFilePath,
            'user_id' => $request->user()->id,
            'max_altitude' => $parsedData['max_altitude'],
            'distance' => $parsedData['total_distance'],
            'points' => $parsedData['total_points'],
            'takeoff_time' => $parsedData['takeoff_time'],
            'landing_time' => $parsedData['landing_time'],
            'glider-type' => $parsedData['aircraft_type'],
            'is_private' => $request->input('is_private'),
            'category' => $request->input('category'),
            'punctuation_info' => $csvContent
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
    public function update(Request $request)
    {
        $request->validate([
            'is_private' => 'boolean',
            'category' => 'in:open,sport,club,tandem',
        ]);
        if ($request->user() == null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $flight = Flight::findOrFail($request->route('flight_id'));
        if ($request->user()->id != $flight->user_id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        if ($request->has('is_private')) {
            $flight->is_private = $request->input('is_private');
        }
        if ($request->has('category')) {
            $flight->category = $request->input('category');
        }
        return response()->json([
            'status' => 200,
            'flight' => $flight,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $flight = Flight::findOrFail($id);
        if ($flight->user_id != request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $flight->delete();
        return response()->json([
            'status' => 200,
            'message' => 'Flight deleted successfully',
        ]);
    }

    public function getFlightsFromUser(Request $request): JsonResponse
    {
        $user_id = $request->route('user_id');
        if ($request->user()->id == $user_id) {
            return response()->json([
                'status' => 200,
                'flights' => Flight::where('user_id', $user_id)->with(['user', 'comments', 'likes'])->get(),
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

    public function getFile(Request $request)
    {
        $igcFilePath = "";
        if (str_ends_with($request->route("flight_path"), '.csv')) {
            $igcFilePath = str_replace('.csv', '.igc', $request->route("flight_path"));
        } else {
            $igcFilePath = $request->route("flight_path");
        }

        $flight = Flight::where('igc_file', "igc_flights/" . $igcFilePath)->first();
        if (!$flight) {
            return response()->json(
                ['error' => 'File not found',
                'flight_path' => $request->route('flight_path'),
                'igc_file_path' => "igc_flights/" . $igcFilePath]
                , 404);
        }
        $flightPath = "";
        if (str_ends_with($request->route('flight_path'), '.igc')) {
            $flightPath = "igc_flights/" . $request->route('flight_path');
        }
        if (str_ends_with($request->route('flight_path'), '.csv')) {
            $flightPath = "csv_flights/" . $request->route('flight_path');
        }
        if (!$flight->is_private || $flight->user_id == $request->user()->id) {
            return response()->download(storage_path('app/private/' . $flightPath));
        }
        return response()->json(
            ['error' => 'File not found',
            'flight_path' => $flightPath]
            , 404);
    }
}
