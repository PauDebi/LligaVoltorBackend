<?php

namespace App\Http\Helpers;

use DateTime;
use Exception;

class IGCParser2
{
    // Constantes para el cálculo de distancia y giro
    private const THRESHOLD = 25; // grados
    private const SENSIBILITY = 40; // cada cuantos puntos se calcula el giro
    /**
     * Parsea un archivo IGC y extrae:
     *  - altitud máxima (GPS)
     *  - distancia total entre puntos de giro importantes
     *  - hora de despegue y aterrizaje
     *  - tipo de aeronave
     *
     * @param string $filePath Ruta al archivo .igc
     * @return array [
     *    'max_altitude'    => float,  // en metros
     *    'total_distance'  => float,  // en kilómetros
     *    'takeoff_time'    => string, // 'HH:MM:SS'
     *    'landing_time'    => string, // 'HH:MM:SS'
     *    'aircraft_type'   => string|null
     * ]
     * @throws Exception Si el archivo no existe o no puede leerse.
     */
    static function parseIGC(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo IGC no existe: $filePath");
        }
        if (!is_readable($filePath)) {
            throw new Exception("No se puede leer el archivo IGC: $filePath");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $flightDate = null;      // DateTime de la fecha de vuelo
        $aircraftType = null;    // String del tipo de planeador
        $track = [];             // Lista de puntos ['time'=>'HHMMSS','lat'=>float,'lon'=>float,'gpsAlt'=>int]

        foreach ($lines as $line) {
            // Header – fecha
            if (preg_match('/^HFDTEDATE(\d{2})(\d{2})(\d{2})/', $line, $matches)) {
                $yy = $matches[3];
                $mm = $matches[2];
                $dd = $matches[1];
                $year = intval($yy) < 70 ? '20' . $yy : '19' . $yy;  // 70 es el corte estándar
                $flightDate = DateTime::createFromFormat('Y-m-d', "$year-$mm-$dd");
                if (!$flightDate) {
                    throw new Exception("Error al parsear la fecha del vuelo: $year-$mm-$dd");
                }
                continue;
            }


            // Header – tipo de aeronave (según spec puede variar la etiqueta)
            if (strpos($line, 'HFGTY') === 0 || stripos($line, 'GLIDERTYPE') !== false) {
                // Ejemplo: HFGTYGLIDERTYPE:ASK-21
                $parts = preg_split('/[:\s]+/', $line, 2);
                if (isset($parts[1])) {
                    $aircraftType = trim($parts[1]);
                }
                continue;
            }
            // B-record: BHHMMSSDDMMmmmNDDDMMmmmE...AAAAA PPPPP
            if (isset($line[0]) && $line[0] === 'B') {
                $time = substr($line, 1, 6);
                // lat: grados(2)/minutos(2)/dec-min(3)/N/S
                $latDeg = intval(substr($line, 7, 2));
                $latMin = floatval(substr($line, 9, 2) . '.' . substr($line, 11, 3));
                $latHem = substr($line, 14, 1);
                $lat = $latDeg + $latMin / 60.0;
                if ($latHem === 'S') $lat = -$lat;
                // lon: grados(3)/minutos(2)/dec-min(3)/E/W
                $lonDeg = intval(substr($line, 15, 3));
                $lonMin = floatval(substr($line, 18, 2) . '.' . substr($line, 20, 3));
                $lonHem = substr($line, 23, 1);
                $lon = $lonDeg + $lonMin / 60.0;
                if ($lonHem === 'W') $lon = -$lon;
                // gps altitude (bytes 30–34)
                $gpsAlt = intval(substr($line, 30, 5));

                $track[] = [
                    'time' => $time,
                    'lat' => $lat,
                    'lon' => $lon,
                    'gpsAlt' => $gpsAlt,
                ];
            }
        }

        if (count($track) < 2) {
            throw new Exception("Archivo IGC inválido o sin suficientes datos B-record." .
                " Fecha: $flightDate");
        }

        // 1) Altitud máxima
        $maxAlt = 0;
        foreach ($track as $pt) {
            if ($pt['gpsAlt'] > $maxAlt) {
                $maxAlt = $pt['gpsAlt'];
            }
        }

        // Auxiliares para distancia y detección de giro
        function haversine($lat1, $lon1, $lat2, $lon2) //Formula de Haversine (distancia entre dos puntos en la esfera)
        {
            $R = 6357; // km radio de la tierra
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
            return $R * 2 * asin(min(1, sqrt($a)));
        }

        function bearing($lat1, $lon1, $lat2, $lon2)
        {
            $dLon = deg2rad($lon2 - $lon1);
            $φ1 = deg2rad($lat1);
            $φ2 = deg2rad($lat2);
            $y = sin($dLon) * cos($φ2);
            $x = cos($φ1) * sin($φ2) - sin($φ1) * cos($φ2) * cos($dLon);
            $brng = rad2deg(atan2($y, $x));
            return ($brng + 360) % 360;
        }

        // 2) Encuentra “puntos de giro importantes” (cambio > 30°)
        $turnpoints = [0]; // incluir el primer punto


        for ($i = 1; $i < count($track) - 1; $i += self::SENSIBILITY) {
            $b1 = bearing(
                $track[$i - 1]['lat'], $track[$i - 1]['lon'],
                $track[$i]['lat'], $track[$i]['lon']
            );
            $b2 = bearing(
                $track[$i]['lat'], $track[$i]['lon'],
                $track[$i + 1]['lat'], $track[$i + 1]['lon']
            );

            $diff = self::angleDiff($b1, $b2);

            if ($diff > self::THRESHOLD) {
                $turnpoints[] = $i;
            }
        }
        $turnpoints[] = count($track) - 1; // incluir el último

        // 3) Distancia total sumando solo entre turnpoints
        $totalDist = 0.0;
        for ($j = 0; $j < count($turnpoints) - 1; $j++) {
            $p = $turnpoints[$j];
            $q = $turnpoints[$j + 1];
            $totalDist += haversine(
                $track[$p]['lat'], $track[$p]['lon'],
                $track[$q]['lat'], $track[$q]['lon']
            );
        }

        // 4) Horas de despegue y aterrizaje
        $first = $track[0]['time'];
        $last = $track[count($track) - 1]['time'];
        // Creamos DateTime para formatear
        $takeoffDT = DateTime::createFromFormat('Hisu', $first . '000000');
        $landingDT = DateTime::createFromFormat('Hisu', $last . '000000');

        return [
            'max_altitude' => $maxAlt,
            'total_distance' => round($totalDist, 3),   // km con 3 decimales
            'takeoff_time' => $takeoffDT->format('Y-m-d H:i:s'),
            'landing_time' => $landingDT->format('Y-m-d H:i:s'),
            'aircraft_type' => $aircraftType,
        ];
    }

    private static function angleDiff($a, $b) {
        $diff = abs($a - $b) % 360;
        return ($diff > 180) ? 360 - $diff : $diff;
    }

}
