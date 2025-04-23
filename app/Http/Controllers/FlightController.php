<?php

namespace App\Http\Controllers;

use App\Http\Helpers\IGCParser2;
use App\Http\Requests\PostFlightRequest;
use App\Models\Flight;
use DateTime;
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
     * @throws \Exception
     */
    public function store(PostFlightRequest $request)
    {
        $storedFilePath = $request->file('igc_file')->storeAs('igc_flights', uniqid() . '.igc');
        $parsedData = IGCParser2::parseIGC($request->file('igc_file')->getRealPath());
        //$parsedData = $this->extractFlightData($request);
        $maxAltitude = $parsedData['max_altitude'];
        $distance = $parsedData['total_distance'];
        $takeoffTime = $parsedData['takeoff_time'];
        $landingTime = $parsedData['landing_time'];
        $gliderType = $parsedData['aircraft_type'];

        // Guardar el archivo IGC

        $flight = Flight::create([
            'igc_file' => $storedFilePath,
            'user_id' => $request->user()->id,
            'max_altitude' => $maxAltitude,
            'distance' => $distance,
            'points' => 0,
            'takeoff_time' => $takeoffTime?? now(),
            'landing_time' => $landingTime?? now(),
            'glider-type' => $gliderType,
        ]);

        // Retornar la respuesta
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

    private function extractFlightData($request)
    {
        // Leer y analizar el archivo IGC
        $igcParser = new IGCParser(file_get_contents($request->file('igc_file')->getRealPath()));
        $parsedData = $igcParser->parse(true, true);

        // Extraer los datos necesarios
        $maxAltitude = $parsedData['track']['maxaltitude'] ?? null;
        $distance = $parsedData['track']['distance'] ?? null;

        // Obtener la fecha del vuelo desde los metadatos y convertirla al formato correcto
        $flightDate = null;
        if (!empty($parsedData['metadata']['date'])) {
            $date = DateTime::createFromFormat('dmy', $parsedData['metadata']['date']);
            $flightDate = $date ? $date->format('Y-m-d') : null;
        }

        // Calcular takeoff_time y landing_time como DATETIME
        $takeoffTime = null;
        if ($flightDate && isset($parsedData['route']['takeoff']['h'], $parsedData['route']['takeoff']['m'], $parsedData['route']['takeoff']['s'])) {
            $takeoffTime = sprintf(
                '%s %02d:%02d:%02d',
                $flightDate,
                $parsedData['route']['takeoff']['h'],
                $parsedData['route']['takeoff']['m'],
                $parsedData['route']['takeoff']['s']
            );
        }

        $landingTime = null;
        if ($flightDate && isset($parsedData['route']['landing']['h'], $parsedData['route']['landing']['m'], $parsedData['route']['landing']['s'])) {
            $landingTime = sprintf(
                '%s %02d:%02d:%02d',
                $flightDate,
                $parsedData['route']['landing']['h'],
                $parsedData['route']['landing']['m'],
                $parsedData['route']['landing']['s']
            );
        }

        $gliderType = $parsedData['metadata']['glider-type'] ?? null;

        return [
            'maxAltitude' => $maxAltitude,
            'distance' => $distance,
            'flightDate' => $flightDate,
            'takeoffTime' => $takeoffTime,
            'landingTime' => $landingTime,
            'gliderType' => $gliderType,
        ];
    }
}
