#include <stdio.h>
#include <stdlib.h>
#include <math.h>
#include <string.h>

#define R 6371.0

typedef struct {
    double lat;
    double lon;
    int gpsAlt;
} Point;

double deg2rad(double deg) {
    return deg * M_PI / 180.0;
}

double haversine(double lat1, double lon1, double lat2, double lon2) {
    double dLat = deg2rad(lat2 - lat1);
    double dLon = deg2rad(lon2 - lon1);
    lat1 = deg2rad(lat1);
    lat2 = deg2rad(lat2);

    double a = sin(dLat / 2) * sin(dLat / 2) +
               cos(lat1) * cos(lat2) *
               sin(dLon / 2) * sin(dLon / 2);

    return R * 2 * asin(sqrt(a));
}

int main(int argc, char *argv[]) {
    if (argc < 3) {
        fprintf(stderr, "Uso: %s track.csv bonus.csv\n", argv[0]);
        return 1;
    }

    FILE *ftrack = fopen(argv[1], "r");
    FILE *fbonus = fopen(argv[2], "r");

    if (!ftrack) {
        fprintf(stderr, "No se pudo abrir el archivo de track\n");
        return 1;
    }

    Point *points = malloc(sizeof(Point) * 20000);
    int count = 0;
    char line[256];

    fgets(line, sizeof(line), ftrack); // Saltar encabezado
    while (fgets(line, sizeof(line), ftrack)) {
        double lat, lon;
        int alt;
        char time[10];
        sscanf(line, "%[^,],%lf,%lf,%d", time, &lat, &lon, &alt);
        points[count++] = (Point){lat, lon, alt};
    }
    fclose(ftrack);

    // Calcular distancia máxima
    double maxDist = 0.0;
    int startIdx = 0, endIdx = 0;
    for (int i = 0; i < count - 1; i++) {
        for (int j = i + 1; j < count; j++) {
            double d = haversine(points[i].lat, points[i].lon, points[j].lat, points[j].lon);
            if (d > maxDist) {
                maxDist = d;
                startIdx = i;
                endIdx = j;
            }
        }
    }

    // Bonus
    double bonus = 0;
    double multiplier = 1.0;

    if (fbonus) {
        while (fgets(line, sizeof(line), fbonus)) {
            char tipo;
            double cantidad, blat, blon, radio;
            sscanf(line, "%c,%lf,%lf,%lf,%lf", &tipo, &cantidad, &blat, &blon, &radio);

            for (int i = 0; i < count; i++) {
                double dist = haversine(points[i].lat, points[i].lon, blat, blon);
                if (dist <= radio) {
                    if (tipo == 'P') bonus += cantidad;
                    if (tipo == 'M') multiplier += cantidad;
                    break;
                }
            }
        }
        fclose(fbonus);
    }

    double total = (maxDist + bonus) * multiplier;

    printf("{\"score_km\": %.3f, \"points\": %.3f, \"start_index\": %d, \"end_index\": %d}\n",
        maxDist, total, startIdx, endIdx);

    free(points);
    return 0;
}
