# Banco de preguntas — MCQ_CLNICA_1.xlsx

Este archivo es la **fuente de verdad** del banco de preguntas y flashcards clínicas de Choice.

## ¿Qué contiene?

- **~3.230 preguntas MCQ** (Multiple Choice) de Neumonología, Cardiología y Nefrología
- **~328 flashcards clínicas** para repaso rápido
- Cada pregunta incluye: enunciado, 4 opciones, respuesta correcta, justificaciones detalladas
- Organizado por especialidad → tema → sección

## ¿Cómo se usa?

### Opción 1: Comando Artisan (recomendado para desarrollo)

```bash
php artisan questions:import
# o apuntando a un archivo específico:
php artisan questions:import database/imports/MCQ_CLNICA_1.xlsx
# para forzar reimport completo:
php artisan questions:import --fresh
```

El comando es **idempotente**: si agregás preguntas nuevas al xlsx, detecta cuáles faltan y las importa (estado guardado en `storage/app/import_state.json`).

### Opción 2: Seeder (recomendado para deploy automatizado)

```bash
php artisan db:seed --class=QuestionsSeeder
```

Usa el mismo comando internamente.

### Opción 3: Migrate + Seed completo

```bash
php artisan migrate:fresh --seed
```

⚠️ **Cuidado**: `--fresh` borra TODA la base de datos. Solo usar en desarrollo.

## ¿Cómo agregar preguntas nuevas?

1. Editá `MCQ_CLNICA_1.xlsx` y agregá las filas nuevas (no modifiques las existentes para no romper el `code_number`).
2. Ejecutá `php artisan questions:import` → importa solo las nuevas.
3. Commit del xlsx actualizado y push.

## Hojas soportadas

El comando detecta automáticamente el formato:

- **Hoja "Notes"** (formato enriquecido con justificaciones expandidas)
- **Hoja "Hoja 1"** (formato clásico)

Si tu archivo tiene otra hoja, pasala con `--sheet=Nombre`.

## Estado de import

El archivo `storage/app/import_state.json` guarda:

- Última fila procesada
- Último ID de pregunta registrado
- Archivo y hoja usados
- Fecha del último import

**Importante**: este archivo NO se commitea (está en `.gitignore`). Cada deploy empieza desde la fila 1, pero el `updateOrCreate` por `code_number` hace que las preguntas existentes no se dupliquen.
