# 📋 Plan de Desarrollo: Plataforma Web & PWA de Estudio Médico (Choice)

Plataforma orientada a estudiantes de medicina para la práctica intensiva de preguntas tipo *multiple choice*, preparación para exámenes de cátedras y residencias médicas.

---

## 1. 🎯 Visión del Producto y Modelo de Acceso

Inspirada en plataformas líderes del sector médico como **UWorld** y **Amboss**, adaptada al contexto local (preguntas de Cátedras 1, 2 y 3, Residencias):
- **Estudio activo con justificación inmediata**: No solo saber si está bien o mal, sino entender el porqué fisiopatológico y clínico con las justificaciones detalladas que ya tiene el banco.
- **Banco de Errores dinámico**: Permitir al estudiante repetir y dominar específicamente los conceptos que falló.
- **Micro-hábito y Gamificación**: Sistema de rachas diarias y rachas de respuestas correctas consecutivas para mantener la constancia.
- **Accesibilidad PWA**: Experiencia nativa en móviles (iOS/Android) y escritorio sin necesidad de publicar en App Store/Play Store inicialmente.
- **Modelo Freemium con Cooldown de 24 Horas**:
  - **Acceso Gratuito**: 10 preguntas disponibles. Una vez respondidas las 10 preguntas, se bloquea el acceso con un contador regresivo de **24 horas** de espera para liberar las siguientes 10 preguntas.
  - **Acceso Premium (Ilimitado)**: Acceso sin restricciones a las 3.602 preguntas, sin tiempos de espera.
  - **Pasarela de Pago**: Por ahora **no se integrará la pasarela de pago real**. La arquitectura, base de datos, modales informativos y botones quedarán preparados para conectarse fácilmente más adelante (Mercado Pago / Stripe).

---

## 2. 💡 Ideas de Alto Impacto para Estudiantes de Medicina
*(Lo que marca la diferencia entre una app común y una herramienta indispensable)*

1. **Modo "Banco de Fallos" (Smart Error Bank)**:
   - Toda pregunta errada va automáticamente al banco de revisión.
   - El alumno puede filtrar por: *"Preguntas que fallé hoy"*, *"Preguntas que he fallado más de 2 veces"*.
   - Cuando acierta la pregunta errada en dos sesiones sucesivas, sale del banco de fallos.
2. **Contador Regresivo de Desbloqueo (24h Cooldown)**:
   - Al agotar las 10 preguntas gratuitas, un widget dinámico muestra: *"Próximas 10 preguntas en: 18h 32m 14s"* junto con el botón promocional *"Pasar a Premium Ilimitado"*.
3. **Filtro por Cátedras y Fuentes**:
   - Al tener en el banco identificadores como `04_CAT2_Cardiopatía...`, los alumnos de Cátedra 2 o Cátedra 3 pueden entrenar específicamente con preguntas tomadas en su propia cursada.
4. **Subrayador de Enunciados y Descarte de Opciones**:
   - Capacidad de tachar opciones que el estudiante sabe que son falsas (con un clic/icono de tachado).
   - Resaltado de palabras clave en el enunciado (ej. *"mujer de 45 años con oliguria..."*).
5. **Notas Personales y Guardar en Favoritos**:
   - Cada pregunta permite agregar una nota rápida personal para fijar reglas nemotécnicas.
6. **Estadísticas de Puntos Ciegos (Radar de Rendimiento)**:
   - Gráfico visual: ej. *"Cardiología: 85% de acierto"*, *"Nefrología: 42% (Reforzar Síndrome Nefrótico)"*.
7. **Modo Oscuro (Dark Mode)**:
   - Fundamental para estudiantes de medicina que estudian durante guardias o de noche.
8. **Recordatorios y Notificaciones Push (PWA)**:
   - Notificación diaria: *"🔥 ¡Tus 10 preguntas de hoy ya están disponibles! No pierdas tu racha"*.
9. **Simulacros de Examen Cronometrados (Futuro)**:
   - Modo de 50 o 100 preguntas con reloj regresivo para simular el examen de residencia real.

---

## 3. 🏗️ Arquitectura Tecnológica

Aprovechando el stack ya configurado en el proyecto:

- **Backend**: Laravel 12 (PHP 8.2+)
  - **API**: Laravel Sanctum para autenticación por tokens SPA / PWA.
  - **Base de Datos**: MySQL con índices optimizados para filtrado por especialidad, tema y estado de respuesta.
  - **Control de Cuota Diaria**: Middleware o servicio que contabiliza las 10 preguntas diarias del usuario gratuito y calcula el tiempo restante de las 24 horas.
  - **Suscripciones**: Estructura de base de datos lista (roles `free` y `premium`, fechas de expiración). Por ahora activable manualmente desde panel o simulador de prueba.
  - **Comando de Importación**: Script Artisan que procesa las 3.602 preguntas del Google Sheet directamente a la base de datos normalizada.
- **Frontend**: Vue 3 (Composition API, `<script setup>`) + Vite 7 + TypeScript
  - **Estado Global**: Pinia (`useAuthStore`, `useQuizStore`, `useStreakStore`, `useQuotaStore`).
  - **Rutas**: Vue Router 4.
  - **Estilos & UI**: Tailwind CSS v4 con diseño móvil-first optimizado para interacción táctil rápida.
  - **PWA**: `vite-plugin-pwa` para Service Worker, caching offline y soporte de instalación en pantalla de inicio.

---

## 4. 🗄️ Modelo de Base de Datos Propuesto

### Tablas Principales:
1. **`users`**:
   - `id`, `name`, `email`, `password`, `is_premium` (boolean, default false), `premium_until` (datetime nullable).
2. **`user_daily_quotas`**:
   - `id`, `user_id`
   - `questions_answered_today` (contador de 0 a 10)
   - `quota_reset_at` (timestamp que marca cuándo se cumplen las 24 hs para volver a tener 10 preguntas)
3. **`specialties`** (Especialidades):
   - `id`, `code` (ej. `03_NEFROLOGIA`), `name` (ej. `Nefrología`), `icon`, `color`.
4. **`topics`** (Temas):
   - `id`, `specialty_id`, `name` (ej. `Síndrome nefrítico`), `code`.
5. **`sections`** (Secciones):
   - `id`, `name` (ej. `Fisiopatología`, `Clínica`, `Diagnóstico`, `Tratamiento`).
6. **`questions`**:
   - `id`, `code_number` (1 a 3602)
   - `specialty_id`, `topic_id`, `section_id`
   - `statement` (Enunciado)
   - `options` (JSON con las opciones: `[{ key: 'a', text: '...' }, ...]`)
   - `correct_answer` (`a`, `b`, `c`, `d`)
   - `correct_justification` (Texto explicativo de la respuesta correcta)
   - `incorrect_justification` (Texto explicativo de las opciones descartadas)
   - `source`, `catedra`, `source_justification`
7. **`user_answers`** (Historial de respuestas):
   - `id`, `user_id`, `question_id`
   - `selected_answer` (`a`, `b`, `c`, `d`)
   - `is_correct` (boolean)
   - `time_spent_seconds`
   - `created_at`
8. **`user_streaks`** (Rachas):
   - `user_id`
   - `current_daily_streak` (Días consecutivos activos)
   - `max_daily_streak`
   - `last_activity_date`
   - `current_correct_streak` (Racha actual de respuestas correctas en la sesión)
   - `max_correct_streak`
9. **`user_bookmarks`** (Guardadas / Favoritos):
   - `user_id`, `question_id`, `notes`
10. **`subscription_plans`** (Estructura lista para cuando se conecte la pasarela):
    - `id`, `name`, `price`, `interval_days`, `is_active`

---

## 5. 🚀 Plan de Implementación por Fases

### 🟢 Fase 1: Estructura de Datos e Importador de Preguntas ✅ [COMPLETADA]
- **Migraciones creadas**: `specialties`, `topics`, `sections`, `questions`, `flashcards`, `user_answers`, `user_streaks`, `user_bookmarks`, `user_daily_quotas`.
- **Modelos Eloquent**: `Question`, `Flashcard`, `Specialty`, `Topic`, `Section`, `User`, `UserAnswer`, `UserStreak`, `UserBookmark`, `UserDailyQuota`.
- **Resultados de la importación (`php artisan questions:import`)**:
  - **Preguntas MCQ completas**: **3.230** preguntas interactivas.
  - **Flashcards clínicas**: **328** tarjetas de memoria.
  - **Especialidades**: 3 activas (Neumonología, Cardiología, Nefrología) | **Temas**: 48 | **Secciones**: 16.

---

### 📌 Registro de Importación Incremental (`MCQ_CLNICA_1.xlsx`)
El sistema está configurado para leer y continuar automáticamente sobre el nuevo archivo:
- **Archivo fuente activo**: `MCQ_CLNICA_1.xlsx` (Hoja `Notes`).
- **Última fila procesada**: **Fila 1500**
- **Último ID registrado**: **ID 1499**
- **Archivo de estado persistente**: `storage/app/import_state.json`
- **Comportamiento al agregar nuevas preguntas**:
  Cuando agregues nuevas filas en `MCQ_CLNICA_1.xlsx` a partir de la fila 1501, simplemente ejecutás:
  ```bash
  php artisan questions:import
  ```
  El comando detectará automáticamente las nuevas filas y empezará desde la **fila 1501**, incorporando las nuevas preguntas sin duplicar las anteriores ni alterar el progreso de los estudiantes.

---

### 🟡 Fase 2: Autenticación y Control de Cuota (10 preguntas / 24 hs)
- Registro y login con Laravel Sanctum.
- Middleware en Laravel para validar el límite diario:
  - Si el usuario es `is_premium == true`: acceso ilimitado.
  - Si es gratuito: permite responder hasta 10 preguntas. Al llegar a 10, retorna código de espera con los segundos restantes para el reseteo.
- Vistas en Vue: Login, Registro, Modal informativo de cuota agotada con cuenta regresiva.

### 🟠 Fase 3: Motor de Estudio y Preguntas (Core)
- **Pantalla de Selección**:
  - Seleccionar Especialidad (Nefrología, Cardiología, Neumonología).
  - Seleccionar Modo:
    - *Modo Estudio Libre* (por tema o especialidad).
    - *Modo Banco de Fallos* (solo preguntas que el alumno respondió mal con anterioridad).
    - *Modo Favoritas / Repaso*.
- **Interfaz de Pregunta (Quiz View)**:
  - Tarjeta limpia con enunciado grande y legible.
  - Botones de opciones `a`, `b`, `c`, `d` con respuesta táctil.
  - Botón de descartar/tachar opciones que se descartan.
  - Al responder:
    - **Correcta**: Opción verde, sonido de acierto, animación de racha (+1 🔥), y acordeón desplegable con la justificación completa.
    - **Incorrecta**: Opción roja, resalte en verde de la correcta, sonido sutil de error, reinicio de racha de respuestas seguidas, y apertura inmediata de la justificación clínica (explicando la correcta y el error de las demás).
  - Botón "Siguiente Pregunta" o atajos de teclado en PC.

### 🟣 Fase 4: Gamificación y Sistema de Rachas
- **Racha Diaria (Daily Streak)**:
  - Se incrementa si el alumno responde al menos sus 10 preguntas en el día.
  - Contador de fuego 🔥 visible en la barra superior.
- **Racha de Aciertos Seguidos**:
  - Contador dinámico en pantalla durante la sesión (*"¡3 correctas seguidas!"*, *"¡Combo x5!"*).
- **Banco de Errores Activo**:
  - Sección donde se listan las preguntas falladas con opción de reintentarlas hasta sacarlas bien.

### 🔵 Fase 5: Estructura de Suscripción (Preparación sin pasarela activa)
- Vistas de precios y planes informativos (ej. Mensual, Semestral).
- Modal "Desbloquear Ilimitado" cuando se agotan las 10 preguntas diarias.
- Panel interno para que un administrador pueda marcar usuarios como `is_premium` manualmente o mediante código de activación/cupón.
- *(La pasarela real con Mercado Pago / Stripe queda lista a nivel arquitectura para conectarse en un solo paso cuando se decida).*

### ⚪ Fase 6: Progressive Web App (PWA)
- Configuración de `vite-plugin-pwa` con `manifest.webmanifest`:
  - Ícono de la aplicación médica, tema de color y splash screen.
  - Instalación con 1 clic en iPhone ("Agregar a pantalla de inicio") y Android.
- Service Worker para navegación fluida e interfaz rápida.

---

## 6. 📅 Próximos Pasos para Iniciar

1. **Crear las migraciones de base de datos** (`specialties`, `topics`, `questions`, `user_answers`, `user_streaks`, `user_daily_quotas`).
2. **Ejecutar la importación de las 3.602 preguntas** desde el dataset verificado.
3. **Construir la pantalla de Quiz interactivo y el control de 10 preguntas / 24hs**.
