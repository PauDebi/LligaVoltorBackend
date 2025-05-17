#!/bin/sh

echo "🛠 Compilando xc_score..."
if [ -f ./c_files/xc_score.c ]; then
    gcc -o /usr/local/bin/xc_score ./c_files/xc_score.c -lm && echo "✅ xc_score compilado correctamente"
else
    echo "⚠️  Archivo xc_score.c no encontrado. Saltando compilación."
fi
