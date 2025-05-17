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
static function parseIGC(string $filePath, string $baseName): array
    {
        set_time_limit(300);
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

        // 4) Horas de despegue y aterrizaje
        $first = $track[0]['time'];
        $last = $track[count($track) - 1]['time'];
        // Creamos DateTime para formatear
        $takeoffDT = DateTime::createFromFormat('Hisu', $first . '000000');
        $landingDT = DateTime::createFromFormat('Hisu', $last . '000000');

        $bonusPath = storage_path('app/private/bonus_points.csv');
        $scoreData = self::calculateXCScore($track, $bonusPath);
        self::createCSV($track, $scoreData['start_point'], $scoreData['end_point'], $baseName);

        return [
            'max_altitude' => $maxAlt,
            'total_distance' => $scoreData['score_km'],
            'total_points' => $scoreData['points'],
            'takeoff_time' => $takeoffDT->format('Y-m-d H:i:s'),
            'landing_time' => $landingDT->format('Y-m-d H:i:s'),
            'aircraft_type' => $aircraftType,
        ];
    }

    public static function calculateXCScore(array $track, string $bonusCsvPath = null): array
    {
        $tempTrack = tempnam(sys_get_temp_dir(), 'track_') . '.csv';
        $f = fopen($tempTrack, 'w');
        fputcsv($f, ['Time', 'Latitude', 'Longitude', 'GPS Altitude']);
        foreach ($track as $point) {
            fputcsv($f, [$point['time'], $point['lat'], $point['lon'], $point['gpsAlt']]);
        }
        fclose($f);

        $cmd = escapeshellcmd("/usr/local/bin/xc_score {$tempTrack} {$bonusCsvPath}");
        $output = shell_exec($cmd);
        unlink($tempTrack);

        if (!$output) {
            throw new \Exception("Fallo al ejecutar xc_score.");
        }

        $data = json_decode($output, true);
        if (!$data) {
            throw new \Exception("JSON inválido retornado por xc_score.");
        }

        return [
            'type' => 'open_distance',
            'score_km' => $data['score_km'],
            'points' => $data['points'],
            'start_point' => $track[$data['start_index']],
            'end_point' => $track[$data['end_index']],
        ];
    }

    public static function createCSV(array $track, array $startPoint, array $endPoint, string $baseName): void
    {
        $csvFileName = $baseName . '.csv';
        $csvFilePath = storage_path('app/private/csv_flights/' . $csvFileName);

        if (!file_exists(dirname($csvFilePath))) {
            mkdir(dirname($csvFilePath), 0755, true);
        }

        $file = fopen($csvFilePath, 'w');

        fputcsv($file, ['Time', 'Latitude', 'Longitude', 'GPS Altitude']);
        fputcsv($file, [$startPoint['time'], $startPoint['lat'], $startPoint['lon']]);
        foreach ($track as $point) {
            fputcsv($file, [$point['time'], $point['lat'], $point['lon'], $point['gpsAlt']]);
        }
        fputcsv($file, [$endPoint['time'], $endPoint['lat'], $endPoint['lon']]);

        fclose($file);
    }


}
