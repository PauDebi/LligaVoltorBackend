FROM laravelsail/php80-composer

# Actualiza y agrega build-essential para gcc y herramientas de compilación
RUN apt-get update && apt-get install -y build-essential \
    && rm -rf /var/lib/apt/lists/*

# Si quieres puedes agregar más configuraciones aquí
