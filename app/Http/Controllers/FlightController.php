<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostFlightRequest;
use App\Models\Flight;
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
     */
    public function store(PostFlightRequest $request)
    {
        $result = $request->file('igc_file')->store('igc_flights');
        return response()->json([
            'status' => 200,
            'result' => $result
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
}
