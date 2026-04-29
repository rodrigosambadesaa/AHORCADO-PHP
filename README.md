# Ahorcado PHP Docker

Reimplementacion completa en PHP (sin Angular), dockerizada con Apache.

## Ejecutar

```bash
docker compose up -d --build
```

Abrir: http://localhost:18082

## Caracteristicas

- Juego del ahorcado en PHP con sesion.
- Diccionario grande en espanol (`data/spanish-words.json`).
- Normalizacion de acentos/diacriticos para validar intentos.
- Cabeceras de seguridad y restriccion de metodos HTTP.
- Contenedor endurecido (`read_only`, `tmpfs`, `no-new-privileges`).
